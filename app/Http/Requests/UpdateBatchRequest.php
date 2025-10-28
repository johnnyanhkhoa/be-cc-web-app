<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'batchId' => ['required', 'integer', 'exists:tbl_CcBatch,batchId'],
            'intensity' => ['required', 'json'],
            'batchActive' => ['nullable', 'boolean'],
            'deactivatedAt' => ['nullable', 'date'],
            'deactivatedBy' => ['nullable', 'integer'],
            'updatedBy' => ['required', 'integer', 'exists:users,authUserId'],
        ];
    }

    public function messages(): array
    {
        return [
            'batchId.required' => 'Batch ID is required',
            'batchId.exists' => 'Batch not found',
            'intensity.required' => 'Intensity is required',
            'intensity.json' => 'Intensity must be valid JSON',
            'updatedBy.required' => 'Updated by is required',
            'updatedBy.exists' => 'User not found',
        ];
    }
}
