<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\Log;
use OpenAI\Client;

class VoiceAssistantService
{
    protected Client $client;

    public function __construct(protected ?string $apiKey = null)
    {
        $this->apiKey = (string) ($apiKey ?? config('services.openai.key'));

        if ($this->apiKey === '') {
            throw new \InvalidArgumentException('OpenAI API key is not configured.');
        }

        $this->client = \OpenAI::client($this->apiKey);
    }

    public function processCommand(string $input, ?string $audioPath = null): array
    {
        if ($audioPath) {
            $response = $this->client->audio()->transcribe([
                'model' => 'whisper-1',
                'file' => fopen($audioPath, 'r'),
                'response_format' => 'verbose_json',
            ]);
            $input = $response->text;
        }

        $messages = [
            ['role' => 'system', 'content' => 'Eres un asistente inteligente para el hogar. Tienes acceso a los dispositivos del usuario. Usa las herramientas proporcionadas para controlar dispositivos o consultar su estado. Siempre responde de manera concisa y amable en español. NO alucines estados de dispositivos, usa SIEMPRE la herramienta get_devices.'],
            ['role' => 'user', 'content' => $input],
        ];

        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_devices',
                    'description' => 'Obtiene la lista de dispositivos, su estado, tipo y ubicación.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'location_filter' => [
                                'type' => 'string',
                                'description' => 'Filtrar por nombre de ubicación (opcional)',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'control_device',
                    'description' => 'Controla un dispositivo (encender, apagar, cambiar brillo).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'device_name' => [
                                'type' => 'string',
                                'description' => 'El nombre aproximado del dispositivo a controlar.',
                            ],
                            'action' => [
                                'type' => 'string',
                                'enum' => ['turn_on', 'turn_off', 'set_brightness'],
                                'description' => 'La acción a realizar.',
                            ],
                            'brightness' => [
                                'type' => 'integer',
                                'description' => 'El nivel de brillo (0-100) si la acción es set_brightness.',
                            ],
                        ],
                        'required' => ['device_name', 'action'],
                    ],
                ],
            ],
        ];

        $response = $this->client->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'tools' => $tools,
            'tool_choice' => 'auto',
        ]);

        $message = $response->choices[0]->message;
        $responseText = $message->content;

        if ($message->toolCalls) {
            $messages[] = $message->toArray();

            foreach ($message->toolCalls as $toolCall) {
                $functionName = $toolCall->function->name;
                $arguments = json_decode($toolCall->function->arguments, true);

                $result = match ($functionName) {
                    'get_devices' => $this->getDevices($arguments['location_filter'] ?? null),
                    'control_device' => $this->controlDevice(
                        $arguments['device_name'],
                        $arguments['action'],
                        $arguments['brightness'] ?? null
                    ),
                    default => ['error' => 'Función desconocida'],
                };

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall->id,
                    'content' => json_encode($result),
                ];
            }

            $finalResponse = $this->client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
            ]);

            $responseText = $finalResponse->choices[0]->message->content;
        }

        // Generate Audio Response
        $audioContent = null;
        try {
            if ($responseText) {
                $audioResponse = $this->client->audio()->speech([
                    'model' => 'tts-1',
                    'input' => $responseText,
                    'voice' => 'nova', // Friendly voice
                ]);

                $audioContent = base64_encode($audioResponse);
            }
        } catch (\Exception $e) {
            Log::error('TTS Error: '.$e->getMessage());
            // Fail silently on audio, still return text
        }

        return [
            'text' => $responseText,
            'transcript' => $input,
            'audio' => $audioContent,
        ];
    }

    public function dispatchTool(string $name, array $arguments): array
    {
        return match ($name) {
            'get_devices' => $this->getDevices($arguments['location_filter'] ?? null),
            'control_device' => $this->controlDevice(
                $arguments['device_name'],
                $arguments['action'],
                $arguments['brightness'] ?? null
            ),
            default => ['error' => 'Función desconocida'],
        };
    }

    protected function getDevices(?string $locationFilter): array
    {
        $query = Device::with(['location', 'area'])->visible();

        if ($locationFilter) {
            $query->whereHas('location', function ($q) use ($locationFilter) {
                $q->where('name', 'like', "%{$locationFilter}%");
            });
        }

        return $query->get()->map(function ($device) {
            return [
                'id' => $device->id,
                'name' => $device->name,
                'status' => $device->status,
                'brightness' => $device->brightness,
                'type' => $device->type,
                'location' => $device->location?->name ?? 'Sin ubicación',
                'area' => $device->area?->name ?? 'Sin área',
            ];
        })->toArray();
    }

    protected function controlDevice(string $deviceName, string $action, ?int $brightness): array
    {
        $device = Device::visible()->where('name', $deviceName)->first();

        if (! $device) {
            $device = Device::visible()->where('name', 'like', "%{$deviceName}%")->first();
        }

        if (! $device) {
            return ['error' => "No encontré ningún dispositivo llamado '{$deviceName}'."];
        }

        switch ($action) {
            case 'turn_on':
                $device->status = 'on';
                break;
            case 'turn_off':
                $device->status = 'off';
                break;
            case 'set_brightness':
                if ($device->type !== 'dimmer') {
                    return ['error' => "El dispositivo '{$device->name}' no soporta ajuste de brillo."];
                }
                $device->brightness = max(0, min(100, $brightness ?? 50));
                $device->status = 'on';
                break;
        }

        $device->save();

        return [
            'success' => true,
            'message' => "Dispositivo '{$device->name}' actualizado.",
            'device_state' => [
                'status' => $device->status,
                'brightness' => $device->brightness,
            ],
        ];
    }
}
