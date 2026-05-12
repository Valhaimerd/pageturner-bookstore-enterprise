<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;

class BookAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:1000'],
            'conversation_id' => ['nullable', 'integer'],
        ];
    }
}
