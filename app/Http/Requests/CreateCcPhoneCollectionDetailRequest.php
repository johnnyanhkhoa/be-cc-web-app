<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\TblCcPhoneCollectionDetail;

class CreateCcPhoneCollectionDetailRequest extends FormRequest
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
            // Contact Information
            'contactType' => ['nullable', 'string', 'in:rpc,tpc,rb'],
            'phoneId' => ['nullable', 'integer'],
            'contactDetailId' => ['nullable', 'integer'],
            'contactPhoneNumer' => ['nullable', 'string', 'max:20'],
            'contactName' => ['nullable', 'string', 'max:255'],
            'contactRelation' => ['nullable', 'string', 'max:100'],

            // Call Information
            'callStatus' => ['nullable', 'string', 'in:reached,ring,busy,cancelled,power_off,wrong_number,no_contact'],
            'callResultId' => ['nullable', 'integer', 'exists:tbl_CcCaseResult,caseResultId'],
            'leaveMessage' => ['nullable', 'string'],
            'remark' => ['nullable', 'string'],

            // Payment Information
            'promisedPaymentDate' => ['nullable', 'date', 'after_or_equal:today'],
            'askingPostponePayment' => ['nullable', 'boolean'],

            // Call Timing
            'dtCallLater' => ['nullable', 'date', 'after_or_equal:today'],
            'dtCallStarted' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'dtCallEnded' => ['nullable', 'date_format:Y-m-d H:i:s', 'after:dtCallStarted'],

            // Phone Update
            'updatePhoneRequest' => ['nullable', 'boolean'],
            'updatePhoneRemark' => ['nullable', 'string'],

            // Standard Remark
            'standardRemarkId' => ['nullable', 'integer', 'exists:tbl_CcRemark,remarkId'],
            'standardRemarkContent' => ['nullable', 'string'],

            // Evidence and Documents
            'reschedulingEvidence' => ['nullable', 'boolean'],
            'uploadDocuments' => ['nullable', 'json'],

            // Audit
            'createdBy' => ['required', 'integer'], // Required for tracking
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'contactType.in' => 'Contact type must be one of: rpc, tpc, rb',
            'callStatus.in' => 'Call status must be one of: reached, ring, busy, cancelled, power_off, wrong_number, no_contact',
            'callResultId.exists' => 'The selected call result does not exist',
            'standardRemarkId.exists' => 'The selected standard remark does not exist',
            'promisedPaymentDate.after_or_equal' => 'Promised payment date must be today or in the future',
            'dtCallLater.after_or_equal' => 'Call later date must be today or in the future',
            'dtCallEnded.after' => 'Call end time must be after call start time',
            'createdBy.required' => 'Person created is required for audit tracking',
            'uploadDocuments.json' => 'Upload documents must be a valid JSON format',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'contactPhoneNumer' => 'contact phone number',
            'dtCallLater' => 'call later date',
            'dtCallStarted' => 'call start time',
            'dtCallEnded' => 'call end time',
            'askingPostponePayment' => 'asking postpone payment',
            'updatePhoneRequest' => 'update phone request',
            'reschedulingEvidence' => 'rescheduling evidence',
            'standardRemarkId' => 'standard remark',
            'callResultId' => 'call result',
            'createdBy' => 'person created',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    // public function withValidator($validator)
    // {
    //     $validator->after(function ($validator) {
    //         // Custom validation: If uploadDocuments is provided, validate JSON structure
    //         if ($this->filled('uploadDocuments')) {
    //             $uploadDocuments = $this->get('uploadDocuments');
    //             $documents = json_decode($uploadDocuments, true);
    //             if (json_last_error() !== JSON_ERROR_NONE) {
    //                 $validator->errors()->add('uploadDocuments', 'Invalid JSON format for upload documents');
    //             }
    //         }

    //         // Custom validation: Ensure call timing makes sense
    //         if ($this->filled('dtCallStarted') && $this->filled('dtCallEnded')) {
    //             $startTime = $this->get('dtCallStarted');
    //             $endTime = $this->get('dtCallEnded');

    //             if ($startTime && $endTime && strtotime($endTime) <= strtotime($startTime)) {
    //                 $validator->errors()->add('dtCallEnded', 'Call end time must be after call start time');
    //             }
    //         }
    //     });
    // }
}
