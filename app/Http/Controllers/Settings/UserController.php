<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreUserAccountRequest;
use App\Http\Requests\Settings\UpdateUserAccountRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/users/index', [
            'users' => User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_HR, User::ROLE_OVERSEER])
                ->orderBy('name')
                ->get(['id', 'name', 'username', 'email', 'role']),
        ]);
    }

    public function store(StoreUserAccountRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());

        AuditLogger::record('user_account.created', $user, $user->name, ['role' => $user->role, 'username' => $user->username]);

        return redirect()->route('users.index')->with('success', 'User account created.');
    }

    public function update(UpdateUserAccountRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        AuditLogger::record('user_account.updated', $user, $user->name, [
            'role' => $user->role,
            'username' => $user->username,
            'password_changed' => isset($data['password']),
        ]);

        return redirect()->route('users.index')->with('success', 'User account updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 403, 'You cannot delete your own account.');

        abort_if(
            $user->role === User::ROLE_ADMIN && User::where('role', User::ROLE_ADMIN)->count() <= 1,
            403,
            'Cannot delete the last remaining admin account.'
        );

        $label = $user->name.' ('.$user->username.')';
        $metadata = ['role' => $user->role];

        $user->delete();

        AuditLogger::record('user_account.deleted', $user, $label, $metadata);

        return redirect()->route('users.index')->with('success', 'User account deleted.');
    }
}
