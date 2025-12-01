<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ExemptPenaltyFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reasonExempted' => 'required|string|min:10|max:1000',
            'userExempted' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'reasonExempted.required' => 'Exemption reason is required',
            'reasonExempted.string' => 'Exemption reason must be a string',
            'reasonExempted.min' => 'Exemption reason must be at least 10 characters',
            'reasonExempted.max' => 'Exemption reason must not exceed 1000 characters',
            'userExempted.required' => 'User exempted is required',
            'userExempted.string' => 'User exempted must be a string',
        ];
    }

    // ← THÊM METHOD NÀY
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}
