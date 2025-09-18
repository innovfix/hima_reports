<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Orchid\Platform\Models\User as OrchidUser;

class Admin extends OrchidUser
{
    use HasFactory, Notifiable;

    /**
     * Explicit table name (existing table in this DB)
     *
     * @var string
     */
    protected $table = 'admins';

    /**
     * Mass assignable attributes
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'permissions',
        'mobile',
    ];

    /**
     * Hidden attributes
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Attribute casting
     *
     * @var array
     */
    protected $casts = [
        'permissions' => 'array',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    /**
     * Override hasAccess to avoid querying role pivot tables when they don't exist.
     */
    public function hasAccess(string $permit, bool $cache = true): bool
    {
        // If the role pivot table doesn't exist (we're not using Orchid roles),
        // only check the permissions JSON column on the admin record.
        if (! Schema::hasTable('role_users')) {
            $perms = $this->permissions ?? [];

            if (! is_array($perms)) {
                $perms = json_decode($perms, true) ?: [];
            }

            foreach ($perms as $key => $value) {
                if (Str::is($permit, $key) && $value) {
                    return true;
                }
            }

            return false;
        }

        // Otherwise fall back to the default implementation (roles + permissions).
        return parent::hasAccess($permit, $cache);
    }
}


