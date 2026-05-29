<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'employee_number' => ['nullable', 'string', 'max:50', Rule::unique('employees', 'employee_number')->ignore($this->route('employee'))],
            'gender'          => ['nullable', 'in:Male,Female'],
            'department'      => ['required', 'string', 'max:255'],
            'daily_rate'      => ['required', 'numeric', 'min:0'],
            'shift_start'     => ['required', 'date_format:H:i'],
            'shift_end'       => ['required', 'date_format:H:i'],
            'is_active'       => ['sometimes', 'boolean'],
        ];
    }
}
