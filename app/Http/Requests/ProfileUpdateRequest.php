<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->user()->id),
            ],
            'phone' => ['nullable', 'string', 'max:255'],
            'profile_image' => ['nullable', 'image', 'max:2048'],
            'default_address_line_1' => ['nullable', 'string', 'max:255'],
            'default_address_line_2' => ['nullable', 'string', 'max:255'],
            'default_city' => ['nullable', 'string', 'max:255'],
            'default_province' => ['nullable', 'string', 'max:255'],
            'default_postal_code' => ['nullable', 'string', 'max:255'],
            'default_country' => ['nullable', 'string', 'max:255'],
        ];
    }
}
