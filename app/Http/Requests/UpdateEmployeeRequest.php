<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'employee_number' => ['nullable', 'string', 'max:50'],
            'gender'          => ['nullable', 'in:Male,Female'],
            'department'      => ['required', 'string', 'max:255'],
            'daily_rate'      => ['required', 'numeric', 'min:0'],
            'shift_start'     => ['required', 'date_format:H:i'],
            'shift_end'       => ['required', 'date_format:H:i'],
        ];
    }
}
