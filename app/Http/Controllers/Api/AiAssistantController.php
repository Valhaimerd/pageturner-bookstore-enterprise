<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiAssistantRequest;
use App\Services\Ai\AiAssistantService;

class AiAssistantController extends Controller
{
    public function store(AiAssistantRequest $request, AiAssistantService $assistant)
    {
        $result = $assistant->respond($request->validated('prompt'), $request);

        return response()->json([
            'data' => [
                'answer' => $result['answer'],
                'intent' => $result['intent'],
                'fallback_used' => $result['fallback_used'],
                'recommendations' => $result['recommendations'],
                'interaction_id' => $result['interaction']->id,
            ],
        ]);
    }
}
