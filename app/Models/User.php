<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'username', 'email', 'password', 'role'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_HR = 'hr';

    public const ROLE_EMPLOYEE = 'employee';

    public const ROLE_OVERSEER = 'overseer';

    public const ROLES = [self::ROLE_ADMIN, self::ROLE_HR, self::ROLE_EMPLOYEE, self::ROLE_OVERSEER];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * The employee record linked to this login account (if any).
     */
    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isHr(): bool
    {
        return $this->role === self::ROLE_HR;
    }

    public function isEmployee(): bool
    {
        return $this->role === self::ROLE_EMPLOYEE;
    }

    public function isOverseer(): bool
    {
        return $this->role === self::ROLE_OVERSEER;
    }

    /**
     * Admins and HR can manage payroll, employees, and approve requests.
     */
    public function canManagePayroll(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_HR], true);
    }

    /**
     * Admins, HR, and overseers can view payroll data (overseers are read-only).
     */
    public function canViewPayroll(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_HR, self::ROLE_OVERSEER], true);
    }

    /**
     * Does this user hold any of the given roles?
     */
    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }
}
