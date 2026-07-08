<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employee = $this->route('employee');

        return [
            'name' => ['required', 'string', 'max:255'],
            'employee_number' => ['nullable', 'string', 'max:50', Rule::unique('employees', 'employee_number')->ignore($employee)],
            'gender' => ['nullable', 'in:Male,Female'],
            'department' => ['nullable', 'string', 'max:255'],
            'daily_rate' => ['required', 'numeric', 'min:0'],
            'shift_start' => ['required', 'date_format:H:i'],
            'shift_end' => ['required', 'date_format:H:i'],
            'is_active' => ['sometimes', 'boolean'],

            // Login account. Username is required; password is optional (blank = keep current).
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($employee->user_id)],
            'password' => ['nullable', 'string', 'min:6'],
        ];
    }
}
