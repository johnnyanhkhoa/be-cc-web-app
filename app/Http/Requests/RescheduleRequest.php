<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'simulationId' => ['required', 'integer'],
            'lastPaidPaymentId' => ['required', 'integer'],
            'lastPaidPaymentNo' => ['required', 'integer'],
            'rescheduledBy' => ['required', 'string', 'max:255'],
            'reschedulingSource' => ['required', 'string', 'max:255'],
        ];
    }
}
