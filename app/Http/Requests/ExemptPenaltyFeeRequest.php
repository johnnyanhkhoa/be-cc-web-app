<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }

    public function messages(): array
    {
        return [
            'reasonExempted.required' => 'Exemption reason is required',
            'reasonExempted.string' => 'Exemption reason must be a string',
            'reasonExempted.min' => 'Exemption reason must be at least 10 characters',
            'reasonExempted.max' => 'Exemption reason must not exceed 1000 characters',
        ];
    }
}
