<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIRealtimeService
{
    public function __construct(
        protected ?string $apiKey = null,
        protected ?string $realtimeModel = null,
    ) {
        $this->apiKey = (string) ($apiKey ?? config('services.openai.key'));
        $this->realtimeModel = (string) ($realtimeModel ?? config('services.openai.realtime_model', 'gpt-realtime-mini-2025-10-06'));

        if ($this->apiKey === '') {
            throw new \InvalidArgumentException('OpenAI API key is not configured.');
        }
    }

    public function createEphemeralSession(): array
    {
        $response = Http::withHeaders([
            'OpenAI-Beta' => 'realtime=v1',
        ])
            ->withToken($this->apiKey)
            ->post('https://api.openai.com/v1/realtime/sessions', [
                'model' => $this->realtimeModel,
                'modalities' => ['audio', 'text'],
                'instructions' => 'Eres un asistente inteligente para el hogar (Smart Home). Tienes acceso a los dispositivos del usuario. Tu objetivo es controlar estos dispositivos y reportar su estado real. Habla español de manera concisa y amable.
                
                IMPORTANTE:
                1. NO alucines estados ni inventes dispositivos; si no conoces un estado o dispositivo, responde que no tienes datos y utiliza la herramienta get_devices para pedir la lista real.
                2. Cuando te pidan cambiar algo (encender, apagar, brillo), usa control_device.
                3. Si el usuario te saluda, saluda brevemente y espera ordenes.
                4. Responde únicamente preguntas relacionadas con el hogar inteligente (Smart Home). Si el usuario pregunta algo ajeno, dile que solo puedes ayudar con lo relacionado al hogar inteligente y pide que reformule.
                5. Todas tus respuestas deben limitarse a lo que puedas verificar usando las herramientas `get_devices` y `control_device`; si no puedes responder con ellas, indica que la consulta no está cubierta por el asistente.',
                'voice' => 'verse',
                'turn_detection' => [
                    'type' => 'server_vad',
                    'threshold' => 0.5,
                    'prefix_padding_ms' => 300,
                    'silence_duration_ms' => 500,
                ],
                'tools' => [
                    [
                        'type' => 'function',
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
                    [
                        'type' => 'function',
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
            ]);

        if ($response->failed()) {
            Log::error('OpenAI Session Creation Failed: '.$response->body());
            throw new \Exception('Failed to create OpenAI session');
        }

        return $response->json();
    }
}
