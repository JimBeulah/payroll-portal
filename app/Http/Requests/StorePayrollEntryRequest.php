<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollEntryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'employee_id'  => ['required', 'exists:employees,id'],
            'days_present' => ['required', 'integer', 'min:1'],
        ];
    }
}
