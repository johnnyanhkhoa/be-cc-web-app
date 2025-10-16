<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * @method array all($keys = null)
 * @method mixed route($param = null, $default = null)
 */
class GetCollectionLogsRequest extends FormRequest
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
            'contractId' => ['required', 'integer'],
            'from' => ['required', 'date', 'date_format:Y-m-d', 'before_or_equal:to'],
            'to' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * Get data to be validated from the request.
     *
     * @return array
     */
    public function validationData(): array
    {
        return array_merge($this->all(), [
            'contractId' => $this->route('contractId'),
        ]);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contractId.required' => 'Contract ID is required',
            'contractId.integer' => 'Contract ID must be a valid integer',
            'from.required' => 'From date is required',
            'from.date' => 'From date must be a valid date',
            'from.date_format' => 'From date must be in Y-m-d format (e.g., 2024-07-01)',
            'from.before_or_equal' => 'From date must be before or equal to To date',
            'to.required' => 'To date is required',
            'to.date' => 'To date must be a valid date',
            'to.date_format' => 'To date must be in Y-m-d format (e.g., 2025-10-31)',
            'to.after_or_equal' => 'To date must be after or equal to From date',
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
