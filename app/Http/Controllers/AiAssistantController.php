<?php

namespace App\Http\Controllers;

use App\Http\Requests\AiAssistantRequest;
use App\Services\Ai\AiAssistantService;

class AiAssistantController extends Controller
{
    public function index()
    {
        return view('ai.assistant', [
            'result' => null,
            'prompt' => '',
        ]);
    }

    public function store(AiAssistantRequest $request, AiAssistantService $assistant)
    {
        $prompt = $request->validated('prompt');

        return view('ai.assistant', [
            'result' => $assistant->respond($prompt, $request),
            'prompt' => $prompt,
        ]);
    }
}
