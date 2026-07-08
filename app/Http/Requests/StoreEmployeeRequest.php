<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'employee_number' => ['nullable', 'string', 'max:50', 'unique:employees,employee_number'],
            'gender' => ['nullable', 'in:Male,Female'],
            'department' => ['nullable', 'string', 'max:255'],
            'daily_rate' => ['required', 'numeric', 'min:0'],
            'shift_start' => ['required', 'date_format:H:i'],
            'shift_end' => ['required', 'date_format:H:i'],
            'is_active' => ['sometimes', 'boolean'],

            // Login account (always created for a new employee).
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }
}
