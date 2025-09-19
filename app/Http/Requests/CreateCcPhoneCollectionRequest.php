<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCcPhoneCollectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Adjust based on your auth requirements
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Segment and Contract Info
            'segmentType' => ['required', 'string', 'max:255'],
            'contractId' => ['required', 'integer'],
            'customerId' => ['required', 'integer'],
            'assetId' => ['required', 'integer'],

            // Payment Info
            'paymentId' => ['required', 'integer'],
            'paymentNo' => ['required', 'integer'],
            'dueDate' => ['required', 'date'],

            // Overdue Calculations
            'daysOverdueGross' => ['required', 'integer', 'min:0'],
            'daysOverdueNet' => ['required', 'integer', 'min:0'],
            'daysSinceLastPayment' => ['required', 'integer', 'min:0'],
            'lastPaymentDate' => ['nullable', 'date', 'before_or_equal:today'],

            // Amount Fields
            'paymentAmount' => ['required', 'integer', 'min:0'],
            'penaltyAmount' => ['required', 'integer', 'min:0'],
            'totalAmount' => ['required', 'integer', 'min:0'],
            'amountPaid' => ['required', 'integer', 'min:0'],
            'amountUnpaid' => ['required', 'integer', 'min:0'],

            // Contract Details
            'contractNo' => ['required', 'string', 'max:255'],
            'contractDate' => ['required', 'date'],
            'contractType' => ['required', 'string', 'max:255'],
            'contractingProductType' => ['required', 'string', 'max:255'],

            // Customer Details
            'customerFullName' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'string', 'in:male,female,other'],
            'birthDate' => ['required', 'date', 'before:today'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'segmentType.required' => 'Segment type is required',
            'contractId.required' => 'Contract ID is required',
            'customerId.required' => 'Customer ID is required',
            'assetId.required' => 'Asset ID is required',
            'paymentId.required' => 'Payment ID is required',
            'paymentNo.required' => 'Payment number is required',
            'dueDate.required' => 'Due date is required',
            'daysOverdueGross.min' => 'Days overdue gross cannot be negative',
            'daysOverdueNet.min' => 'Days overdue net cannot be negative',
            'daysSinceLastPayment.min' => 'Days since last payment cannot be negative',
            'lastPaymentDate.before_or_equal' => 'Last payment date cannot be in the future',
            'paymentAmount.min' => 'Payment amount cannot be negative',
            'penaltyAmount.min' => 'Penalty amount cannot be negative',
            'totalAmount.min' => 'Total amount cannot be negative',
            'amountPaid.min' => 'Amount paid cannot be negative',
            'amountUnpaid.min' => 'Amount unpaid cannot be negative',
            'contractNo.required' => 'Contract number is required',
            'contractDate.required' => 'Contract date is required',
            'contractType.required' => 'Contract type is required',
            'contractingProductType.required' => 'Contracting product type is required',
            'customerFullName.required' => 'Customer full name is required',
            'gender.in' => 'Gender must be male, female, or other',
            'birthDate.before' => 'Birth date must be before today',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'segmentType' => 'segment type',
            'contractId' => 'contract ID',
            'customerId' => 'customer ID',
            'assetId' => 'asset ID',
            'paymentId' => 'payment ID',
            'paymentNo' => 'payment number',
            'dueDate' => 'due date',
            'daysOverdueGross' => 'days overdue gross',
            'daysOverdueNet' => 'days overdue net',
            'daysSinceLastPayment' => 'days since last payment',
            'lastPaymentDate' => 'last payment date',
            'paymentAmount' => 'payment amount',
            'penaltyAmount' => 'penalty amount',
            'totalAmount' => 'total amount',
            'amountPaid' => 'amount paid',
            'amountUnpaid' => 'amount unpaid',
            'contractNo' => 'contract number',
            'contractDate' => 'contract date',
            'contractType' => 'contract type',
            'contractingProductType' => 'contracting product type',
            'customerFullName' => 'customer full name',
            'birthDate' => 'birth date',
        ];
    }
}
