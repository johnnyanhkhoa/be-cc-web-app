<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'authUserId',
        'email',
        'username',
        'userFullName',
        'isActive',
        'lastLoginAt',
        'createdBy',
        'updatedBy',
        'deletedBy',
        'reasonDeleted',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        // No password stored locally - authentication via Zay Yar
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'isActive' => 'boolean',
        'lastLoginAt' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'deletedAt' => 'datetime',
    ];

    /**
     * The name of the "created at" column.
     *
     * @var string|null
     */
    const CREATED_AT = 'createdAt';

    /**
     * The name of the "updated at" column.
     *
     * @var string|null
     */
    const UPDATED_AT = 'updatedAt';

    /**
     * The name of the "deleted at" column for soft deletes.
     *
     * @var string
     */
    const DELETED_AT = 'deletedAt';

    /**
     * Scope for active users only
     */
    public function scopeActive($query)
    {
        return $query->where('isActive', true);
    }

    /**
     * Check if user is active
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(): void
    {
        $this->update(['lastLoginAt' => now()]);
    }

    // Future relationships
    // public function dutyRosters()
    // {
    //     return $this->hasMany(DutyRoster::class);
    // }

    // public function callAssignments()
    // {
    //     return $this->hasMany(CallAssignment::class);
    // }
}
