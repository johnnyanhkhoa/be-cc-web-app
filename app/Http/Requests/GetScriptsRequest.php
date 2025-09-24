<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetScriptsRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'batchId' => ['required', 'integer', 'exists:tbl_CcBatch,batchId'],
            'daysPastDue' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'batchId.required' => 'Batch ID is required',
            'batchId.exists' => 'The selected batch does not exist',
            'daysPastDue.required' => 'Days past due is required',
            'daysPastDue.min' => 'Days past due cannot be negative',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'batchId' => 'batch ID',
            'daysPastDue' => 'days past due',
        ];
    }
}
