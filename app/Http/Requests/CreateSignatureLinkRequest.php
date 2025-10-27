<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSignatureLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'module' => ['required', 'string', 'max:255'],
            'userCreated' => ['required', 'string', 'max:255'],
        ];
    }
}
