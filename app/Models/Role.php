<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'permissions',
        'is_active',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function hasPermission($permission)
    {
        if ($this->isProtectedSystemRole()) {
            return true;
        }

        return in_array($permission, $this->permissions ?? []);
    }

    public function isProtectedSystemRole(): bool
    {
        $name = strtolower(trim((string) $this->name));

        return in_array($name, ['admin', 'super admin', 'superadmin', 'super_admin'], true);
    }

    public function isSuperAdminRole(): bool
    {
        $name = strtolower(trim((string) $this->name));

        return in_array($name, ['super admin', 'superadmin', 'super_admin'], true);
    }
}
