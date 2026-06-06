<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreManualAttendanceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'employee_id'  => ['required', 'exists:employees,id'],
            'date'         => ['required', 'date'],
            'sw'           => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],
            'ew'           => ['nullable', 'regex:/^\d{1,2}:\d{2}$/'],
            'shift_start'  => ['required', 'regex:/^\d{1,2}:\d{2}$/'],
            'shift_end'    => ['required', 'regex:/^\d{1,2}:\d{2}$/'],
            'note'         => ['nullable', 'string', 'max:255'],
            'is_override'  => ['boolean'],
        ];
    }
}
