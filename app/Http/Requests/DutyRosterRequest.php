<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class DutyRosterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // All users can create duty roster for now
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'agent_auth_user_ids' => ['required', 'array', 'min:1'],
            'agent_auth_user_ids.*' => ['required', 'integer', 'exists:users,authUserId'],
            'createdBy' => ['required', 'integer', 'exists:users,authUserId'], // ✅ THÊM
            'batchId' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'start_date.required' => 'Start date is required',
            'start_date.after_or_equal' => 'Start date must be today or future date',
            'end_date.required' => 'End date is required',
            'end_date.after_or_equal' => 'End date must be after or equal start date',
            'agent_auth_user_ids.required' => 'At least one agent must be selected',
            'agent_auth_user_ids.min' => 'At least one agent must be selected',
            'agent_auth_user_ids.*.exists' => 'Selected agent does not exist',
            'createdBy.required' => 'Created by is required', // ✅ THÊM
            'createdBy.exists' => 'Creator user does not exist', // ✅ THÊM
            'batchId.required' => 'Batch ID is required',  // ← THÊM DÒNG NÀY
            'batchId.integer' => 'Batch ID must be an integer',  // ← THÊM DÒNG NÀY
            'batchId.min' => 'Batch ID must be at least 1',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'agent_auth_user_ids' => 'agents',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        // Simple approach - remove complex validation
        // Date range validation can be handled in Controller if needed
        return $validator;
    }
}
