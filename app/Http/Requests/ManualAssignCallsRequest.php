<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ManualAssignCallsRequest extends FormRequest
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
            'assignedBy' => ['required', 'integer', 'min:1', 'exists:users,authUserId'], // Đổi từ id → authUserId
            'assignTo' => ['required', 'integer', 'min:1', 'exists:users,authUserId'],   // Đổi từ id → authUserId
            'phoneCollectionIds' => ['required', 'array', 'min:1', 'max:1000'],
            'phoneCollectionIds.*' => ['required', 'integer', 'exists:tbl_CcPhoneCollection,phoneCollectionId'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'assignedBy.required' => 'Assigned by is required',
            'assignedBy.exists' => 'The assigned by user does not exist',
            'assignTo.required' => 'Assign to is required',
            'assignTo.exists' => 'The assign to user does not exist',
            'phoneCollectionIds.required' => 'Phone collection IDs are required',
            'phoneCollectionIds.array' => 'Phone collection IDs must be an array',
            'phoneCollectionIds.min' => 'At least one phone collection ID is required',
            'phoneCollectionIds.max' => 'Maximum 1000 phone collections can be assigned at once',
            'phoneCollectionIds.*.required' => 'Each phone collection ID is required',
            'phoneCollectionIds.*.integer' => 'Each phone collection ID must be an integer',
            'phoneCollectionIds.*.exists' => 'One or more phone collection IDs do not exist',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'assignedBy' => 'assigned by',
            'assignTo' => 'assign to',
            'phoneCollectionIds' => 'phone collection IDs',
        ];
    }

    /**
     * Handle a failed validation attempt.
     * IMPORTANT: Override này để trả về JSON thay vì redirect
     */
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
