<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCcPhoneCollectionDetailRequest extends FormRequest
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
            // Phone Collection Reference
            'phoneCollectionId' => ['required', 'integer', 'exists:tbl_CcPhoneCollection,phoneCollectionId'],

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

            // Evidence and Documents - CHANGED: Now expects JSON with uploadImageId array
            'reschedulingEvidence' => ['nullable', 'boolean'],
            'uploadDocuments' => ['nullable', 'json'], // Back to JSON
            'uploadImageIds' => ['nullable', 'array'], // NEW: Direct array of image IDs
            'uploadImageIds.*' => ['integer', 'exists:tbl_CcUploadImage,uploadImageId'],

            // Audit
            'createdBy' => ['required', 'integer'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'phoneCollectionId.required' => 'Phone collection ID is required',
            'phoneCollectionId.exists' => 'The selected phone collection does not exist',
            'contactType.in' => 'Contact type must be one of: rpc, tpc, rb',
            'callStatus.in' => 'Call status must be one of: reached, ring, busy, cancelled, power_off, wrong_number, no_contact',
            'callResultId.exists' => 'The selected call result does not exist',
            'standardRemarkId.exists' => 'The selected standard remark does not exist',
            'promisedPaymentDate.after_or_equal' => 'Promised payment date must be today or in the future',
            'dtCallLater.after_or_equal' => 'Call later date must be today or in the future',
            'dtCallEnded.after' => 'Call end time must be after call start time',
            'createdBy.required' => 'Created by is required for audit tracking',
            'uploadImageIds.*.exists' => 'One or more uploaded images do not exist',
        ];
    }
}
