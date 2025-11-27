<?php

use App\Services\OpenAIRealtimeService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

it('creates an ephemeral session with realtime headers and configured model', function (): void {
    Config::set('services.openai.key', 'test-key');
    Config::set('services.openai.realtime_model', 'gpt-test');

    Http::fake([
        'https://api.openai.com/v1/realtime/sessions' => Http::response([
            'model' => 'gpt-test',
            'client_secret' => ['value' => 'secret'],
        ], 200),
    ]);

    $service = new OpenAIRealtimeService;

    $response = $service->createEphemeralSession();

    expect($response)->toMatchArray([
        'model' => 'gpt-test',
        'client_secret' => ['value' => 'secret'],
    ]);

    Http::assertSent(function ($request): bool {
        return $request->hasHeader('Authorization', 'Bearer test-key')
            && $request->hasHeader('OpenAI-Beta', 'realtime=v1')
            && $request['model'] === 'gpt-test'
            && $request['modalities'] === ['audio', 'text'];
    });
});

it('throws when the OpenAI key is missing', function (): void {
    Config::set('services.openai.key', '');
    Config::set('services.openai.realtime_model', 'gpt-test');

    new OpenAIRealtimeService;
})->throws(\InvalidArgumentException::class, 'OpenAI API key is not configured.');
