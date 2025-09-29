<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitiateCallRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'phoneExtension' => ['required', 'string', 'max:10'],
            'phoneNo' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'caseId' => ['required', 'string', 'max:50'],
            'username' => ['required', 'string', 'max:100'],
            'userId' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'phoneExtension.required' => 'Phone extension is required',
            'phoneExtension.max' => 'Phone extension must not exceed 10 characters',
            'phoneNo.required' => 'Phone number is required',
            'phoneNo.regex' => 'Phone number format is invalid',
            'caseId.required' => 'Case ID is required',
            'username.required' => 'Username is required',
            'userId.required' => 'User ID is required',
            'userId.integer' => 'User ID must be an integer',
            'userId.min' => 'User ID must be at least 1',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'phoneExtension' => 'phone extension',
            'phoneNo' => 'phone number',
            'caseId' => 'case ID',
            'username' => 'username',
            'userId' => 'user ID',
        ];
    }
}
