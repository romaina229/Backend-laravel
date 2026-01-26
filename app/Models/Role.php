<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'description',
        'permissions',
        'active'
    ];

    protected $casts = [
        'permissions' => 'array',
        'active' => 'boolean'
    ];

    // Relation avec les utilisateurs
    public function users()
    {
        return $this->hasMany(User::class);
    }

    // Méthodes utilitaires
    public function hasPermission($permission)
    {
        if (in_array('all', $this->permissions ?? [])) {
            return true;
        }

        return in_array($permission, $this->permissions ?? []);
    }

    public function getPermissionList()
    {
        return $this->permissions ?? [];
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByLabel($query, $label)
    {
        return $query->where('label', $label);
    }

    // Accessors
    public function getPermissionsCountAttribute()
    {
        return count($this->permissions ?? []);
    }
}
