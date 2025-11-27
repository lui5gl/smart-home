<?php

namespace App\Http\Controllers;

use App\Services\OpenAIRealtimeService;
use App\Services\VoiceAssistantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VoiceCommandController extends Controller
{
    protected OpenAIRealtimeService $realtimeService;
    protected VoiceAssistantService $voiceService;

    public function __construct(OpenAIRealtimeService $realtimeService, VoiceAssistantService $voiceService)
    {
        $this->realtimeService = $realtimeService;
        $this->voiceService = $voiceService;
    }

    public function createSession(Request $request)
    {
        try {
            $session = $this->realtimeService->createEphemeralSession();
            return response()->json($session);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function executeTool(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'arguments' => 'nullable|array',
        ]);

        $name = $request->input('name');
        $arguments = $request->input('arguments') ?? [];

        try {
            // We use the existing VoiceAssistantService logic because it already interacts with models nicely
            // But we need to expose the methods publicly or through a helper. 
            // Actually, let's just use reflection or a simple switch since the logic is protected there.
            // Better yet, let's make a public dispatcher in VoiceAssistantService or just duplicate the simple mapping here to avoid breaking encapsulation too much.
            // For speed, I will instantiate the service and call a new public method 'dispatchTool' I will add.
            
            $result = $this->voiceService->dispatchTool($name, $arguments);
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error("Tool execution failed: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    // Keep the old handle method for fallback or if user reverts? 
    // The user "wants" the realtime model, so this might replace it, but I'll leave it for now.
}
