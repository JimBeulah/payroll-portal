<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only a user linked to an employee record may submit.
        return $this->user()?->employee !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'needed_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
