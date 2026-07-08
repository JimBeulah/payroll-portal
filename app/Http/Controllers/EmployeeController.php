<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    /**
     * Employee attribute keys (everything except the login account fields).
     */
    private const EMPLOYEE_FIELDS = [
        'name', 'employee_number', 'gender', 'department',
        'daily_rate', 'shift_start', 'shift_end', 'is_active',
    ];

    public function index()
    {
        return Inertia::render('employees/index', [
            'employees' => Employee::with('user:id,username,role')
                ->orderBy('department')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('employees/create');
    }

    public function store(StoreEmployeeRequest $request)
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'password' => $data['password'],
                'role' => User::ROLE_EMPLOYEE,
            ]);

            Employee::create(Arr::only($data, self::EMPLOYEE_FIELDS) + ['user_id' => $user->id]);
        });

        return redirect()->route('employees.index')->with('success', 'Employee created.');
    }

    public function edit(Employee $employee)
    {
        return Inertia::render('employees/edit', ['employee' => $employee]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $employee) {
            $employee->update(Arr::only($data, self::EMPLOYEE_FIELDS));

            $user = $employee->user;

            if (! $user) {
                // Legacy employee with no login yet — create one now.
                $user = User::create([
                    'name' => $data['name'],
                    'username' => $data['username'],
                    'password' => $data['password'] ?? Str::random(12),
                    'role' => User::ROLE_EMPLOYEE,
                ]);
                $employee->update(['user_id' => $user->id]);

                return;
            }

            $user->name = $data['name'];
            $user->username = $data['username'];
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();
        });

        return redirect()->route('employees.index')->with('success', 'Employee updated.');
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'Employee deleted.');
    }
}
