<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'phone',
        'department',
        'is_active',
        'two_factor_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'is_active' => 'boolean',
            'locked_until' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'two_factor_expires_at' => 'datetime',
        ];
    }

    protected $guard_name = 'web';

    public function patientPortalAccounts(): HasMany
    {
        return $this->hasMany(PatientPortalAccount::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function departmentLabel(): string
    {
        return config('department_roles.labels')[$this->department] ?? ucfirst(str_replace('-', ' ', (string) $this->department));
    }

    public function isAssignedToDepartment(string $department): bool
    {
        return $this->department === $department;
    }

    public function hasDepartmentRole(string $department): bool
    {
        $mappedRoles = config('department_roles.mapping')[$department] ?? [];

        if ($this->hasRole('super-admin')) {
            return $department === 'system-administration' || in_array($this->getRoleNames()->first(), $mappedRoles, true);
        }

        return in_array($this->getRoleNames()->first(), $mappedRoles, true) || $this->department === $department;
    }

    public function canAccessDepartments(array $departments): bool
    {
        if ($this->hasRole('super-admin') || $this->hasRole('hospital-manager')) {
            return true;
        }

        $allowedDepartments = array_values(array_filter($departments, static fn ($department) => is_string($department) && $department !== ''));

        if ($allowedDepartments === []) {
            return true;
        }

        if ($this->department) {
            return in_array($this->department, $allowedDepartments, true);
        }

        $roleName = $this->getRoleNames()->first();

        foreach ($allowedDepartments as $department) {
            if (in_array($roleName, config('department_roles.mapping')[$department] ?? [], true)) {
                return true;
            }
        }

        return false;
    }
}
