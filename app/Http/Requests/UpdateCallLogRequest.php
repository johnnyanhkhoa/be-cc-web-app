<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateCallLogRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'caseId' => ['nullable', 'integer'],
            'phoneNo' => ['nullable', 'string', 'max:20'],
            'phoneExtension' => ['nullable', 'string', 'max:10'],
            'userId' => ['nullable', 'numeric'],
            'username' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'caseId.integer' => 'Case ID must be a valid integer',
            'phoneNo.string' => 'Phone number must be a valid string',
            'phoneNo.max' => 'Phone number cannot exceed 20 characters',
            'phoneExtension.string' => 'Phone extension must be a valid string',
            'phoneExtension.max' => 'Phone extension cannot exceed 10 characters',
            'userId.numeric' => 'User ID must be a valid number',
            'username.string' => 'Username must be a valid string',
            'username.max' => 'Username cannot exceed 100 characters',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422));
    }
}
