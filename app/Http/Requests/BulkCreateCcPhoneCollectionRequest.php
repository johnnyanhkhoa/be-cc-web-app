<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkCreateCcPhoneCollectionRequest extends FormRequest
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
            'phone_collections' => ['required', 'array', 'min:1', 'max:1000'], // Limit to 1000 records
            'phone_collections.*.segmentType' => ['required', 'string', 'max:255'],
            'phone_collections.*.contractId' => ['required', 'integer'],
            'phone_collections.*.customerId' => ['required', 'integer'],
            'phone_collections.*.assetId' => ['required', 'integer'],
            'phone_collections.*.paymentId' => ['required', 'integer'],
            'phone_collections.*.paymentNo' => ['required', 'integer'],
            'phone_collections.*.dueDate' => ['required', 'date'],
            'phone_collections.*.daysOverdueGross' => ['required', 'integer', 'min:0'],
            'phone_collections.*.daysOverdueNet' => ['required', 'integer', 'min:0'],
            'phone_collections.*.daysSinceLastPayment' => ['required', 'integer', 'min:0'],
            'phone_collections.*.lastPaymentDate' => ['nullable', 'date', 'before_or_equal:today'],
            'phone_collections.*.paymentAmount' => ['required', 'integer', 'min:0'],
            'phone_collections.*.penaltyAmount' => ['required', 'integer', 'min:0'],
            'phone_collections.*.totalAmount' => ['required', 'integer', 'min:0'],
            'phone_collections.*.amountPaid' => ['required', 'integer', 'min:0'],
            'phone_collections.*.amountUnpaid' => ['required', 'integer', 'min:0'],
            'phone_collections.*.contractNo' => ['required', 'string', 'max:255'],
            'phone_collections.*.contractDate' => ['required', 'date'],
            'phone_collections.*.contractType' => ['required', 'string', 'max:255'],
            'phone_collections.*.contractingProductType' => ['required', 'string', 'max:255'],
            'phone_collections.*.customerFullName' => ['required', 'string', 'max:255'],
            'phone_collections.*.gender' => ['required', 'string', 'in:male,female,other'],
            'phone_collections.*.birthDate' => ['required', 'date', 'before:today'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'phone_collections.required' => 'Phone collections array is required',
            'phone_collections.array' => 'Phone collections must be an array',
            'phone_collections.min' => 'At least 1 phone collection record is required',
            'phone_collections.max' => 'Maximum 1000 phone collection records allowed per request',
            'phone_collections.*.segmentType.required' => 'Segment type is required for all records',
            'phone_collections.*.contractId.required' => 'Contract ID is required for all records',
            'phone_collections.*.customerId.required' => 'Customer ID is required for all records',
            'phone_collections.*.gender.in' => 'Gender must be male, female, or other for all records',
        ];
    }

    /**
     * Configure the validator instance.
     */
    // public function withValidator($validator)
    // {
    //     $validator->after(function ($validator) {
    //         // Sử dụng input() thay vì get()
    //         $phoneCollections = $this->all('phone_collections', []);

    //         // Check for duplicate contractId within the same request
    //         $contractIds = collect($phoneCollections)->pluck('contractId')->filter();
    //         $duplicateContractIds = $contractIds->duplicates();

    //         if ($duplicateContractIds->isNotEmpty()) {
    //             $validator->errors()->add(
    //                 'phone_collections',
    //                 'Duplicate contract IDs found in request: ' . $duplicateContractIds->implode(', ')
    //             );
    //         }
    //     });
    // }
}
