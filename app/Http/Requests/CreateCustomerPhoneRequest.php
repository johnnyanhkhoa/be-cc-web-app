<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\TblCcCustomerPhone;

class CreateCustomerPhoneRequest extends FormRequest
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
            // IDs - all nullable
            'prospectId' => ['nullable', 'integer'],
            'customerId' => ['nullable', 'integer'],
            'householderId' => ['nullable', 'integer'],
            'refereeId' => ['nullable', 'integer'],
            'phoneCollectionId' => ['nullable', 'integer', 'exists:tbl_CcPhoneCollection,phoneCollectionId'],

            // Phone info
            'phoneNo' => ['nullable', 'string', 'max:20'],
            'customerName' => ['nullable', 'string', 'max:255'],

            // Contact details
            'contactType' => ['nullable', 'string', 'in:rpc,tpc,rb'],
            'phoneStatus' => ['nullable', 'string', 'in:active,inactive,wrong,disconnected'],
            'phoneType' => ['nullable', 'string', 'in:mobile,landline'],

            // Flags
            'isPrimary' => ['nullable', 'boolean'],
            'isViber' => ['nullable', 'boolean'],

            // Remark
            'phoneRemark' => ['nullable', 'string'],

            // Audit
            'createdBy' => ['required', 'integer'],
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
            'prospectId.integer' => 'Prospect ID must be a valid integer',
            'customerId.integer' => 'Customer ID must be a valid integer',
            'householderId.integer' => 'Householder ID must be a valid integer',
            'refereeId.integer' => 'Referee ID must be a valid integer',
            'phoneCollectionId.integer' => 'Phone collection ID must be a valid integer',
            'phoneCollectionId.exists' => 'The selected phone collection does not exist',

            'phoneNo.string' => 'Phone number must be a valid string',
            'phoneNo.max' => 'Phone number cannot exceed 20 characters',
            'customerName.string' => 'Customer name must be a valid string',
            'customerName.max' => 'Customer name cannot exceed 255 characters',

            'contactType.in' => 'Contact type must be one of: rpc, tpc, rb',
            'phoneStatus.in' => 'Phone status must be one of: active, inactive, wrong, disconnected',
            'phoneType.in' => 'Phone type must be one of: mobile, landline',

            'isPrimary.boolean' => 'Is primary must be a boolean value',
            'isViber.boolean' => 'Is Viber must be a boolean value',

            'phoneRemark.string' => 'Phone remark must be a valid string',

            'createdBy.required' => 'Created by is required for audit tracking',
            'createdBy.integer' => 'Created by must be a valid integer',
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
