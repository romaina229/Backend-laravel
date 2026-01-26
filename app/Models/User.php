<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'telephone',
        'address',
        'avatar',
        'active',
        'last_login',
        'email_verified_at'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'active' => 'boolean',
        'last_login' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Attributs ajoutés
    protected $appends = [
        'role_label',
        'avatar_url',
        'initials',
        'permissions'
    ];

    // Constantes pour les rôles
    const ROLE_ADMIN = 'admin';
    const ROLE_MANAGER = 'gestionnaire';
    const ROLE_CASHIER = 'caissier';

    const ROLES = [
        self::ROLE_ADMIN => 'Administrateur',
        self::ROLE_MANAGER => 'Gestionnaire',
        self::ROLE_CASHIER => 'Caissier'
    ];

    // Permissions par rôle
    const PERMISSIONS = [
        self::ROLE_ADMIN => ['all'],
        self::ROLE_MANAGER => [
            'manage_products', 'manage_stock', 'manage_clients',
            'manage_suppliers', 'view_reports', 'view_sales',
            'view_dashboard', 'export_data'
        ],
        self::ROLE_CASHIER => [
            'create_sales', 'create_transactions', 'view_products',
            'view_clients', 'generate_invoices', 'view_dashboard'
        ]
    ];

    // Ressources gérées par rôle
    const MANAGED_RESOURCES = [
        self::ROLE_ADMIN => ['users', 'products', 'categories', 'sales',
                           'clients', 'suppliers', 'transactions', 'reports', 'settings'],
        self::ROLE_MANAGER => ['products', 'categories', 'clients',
                             'suppliers', 'reports', 'stock'],
        self::ROLE_CASHIER => ['sales', 'transactions', 'invoices']
    ];

    // Relations
    public function sales()
    {
        return $this->hasMany(Sale::class, 'user_id');
    }

    public function mobileTransactions()
    {
        return $this->hasMany(MobileTransaction::class, 'user_id');
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class, 'user_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'user_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('telephone', 'like', "%{$search}%");
        });
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', self::ROLE_ADMIN);
    }

    public function scopeManagers($query)
    {
        return $query->where('role', self::ROLE_MANAGER);
    }

    public function scopeCashiers($query)
    {
        return $query->where('role', self::ROLE_CASHIER);
    }

    // Accessors
    public function getRoleLabelAttribute()
    {
        return self::ROLES[$this->role] ?? 'Inconnu';
    }

    public function getAvatarUrlAttribute()
    {
        if ($this->avatar && file_exists(storage_path('app/public/avatars/' . $this->avatar))) {
            return asset('storage/avatars/' . $this->avatar);
        }

        // Générer une image d'avatar basée sur les initiales
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) .
               '&color=fff&background=' . $this->getRoleColor();
    }

    public function getInitialsAttribute()
    {
        $words = explode(' ', $this->name);
        $initials = '';

        foreach ($words as $word) {
            if (!empty($word)) {
                $initials .= strtoupper($word[0]);
            }
        }

        return substr($initials, 0, 2) ?: 'US';
    }

    public function getPermissionsAttribute()
    {
        return self::PERMISSIONS[$this->role] ?? [];
    }

    public function getCreatedAtFormattedAttribute()
    {
        return $this->created_at->format('d/m/Y H:i');
    }

    public function getLastLoginFormattedAttribute()
    {
        return $this->last_login ? $this->last_login->format('d/m/Y H:i') : 'Jamais';
    }

    public function getRoleColorAttribute()
    {
        return $this->getRoleColor();
    }

    // Mutators
    public function setNameAttribute($value)
    {
        $this->attributes['name'] = ucwords(strtolower($value));
    }

    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = strtolower(trim($value));
    }

    // Helpers
    public function isAdmin()
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isManager()
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isCashier()
    {
        return $this->role === self::ROLE_CASHIER;
    }

    public function hasPermission($permission)
    {
        $permissions = $this->permissions;
        return in_array('all', $permissions) || in_array($permission, $permissions);
    }

    public function getRoleColor()
    {
        return match($this->role) {
            self::ROLE_ADMIN => '#1890ff',    // Bleu
            self::ROLE_MANAGER => '#52c41a',  // Vert
            self::ROLE_CASHIER => '#fa8c16',  // Orange
            default => '#8c8c8c'              // Gris
        };
    }

    public function canManage($resource)
    {
        $allowedResources = self::MANAGED_RESOURCES[$this->role] ?? [];
        return in_array($resource, $allowedResources);
    }

    public function updateLastLogin()
    {
        $this->update(['last_login' => now()]);
        return $this;
    }

    public function toggleStatus()
    {
        $this->update(['active' => !$this->active]);
        return $this->active;
    }

    public function resetPassword($newPassword)
    {
        $this->password = bcrypt($newPassword);
        return $this->save();
    }

    public function changeRole($newRole)
    {
        if (!array_key_exists($newRole, self::ROLES)) {
            throw new \InvalidArgumentException("Rôle invalide: $newRole");
        }

        $this->update(['role' => $newRole]);
        return $this;
    }

    // Validation rules
    public static function validationRules($userId = null)
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $userId,
            'password' => $userId ? 'nullable|min:6' : 'required|min:6',
            'role' => 'required|in:' . implode(',', array_keys(self::ROLES)),
            'telephone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'avatar' => 'nullable|image|max:2048',
            'active' => 'boolean'
        ];
    }

    public static function profileValidationRules($userId)
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $userId,
            'telephone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'current_password' => 'required_with:new_password',
            'new_password' => 'nullable|min:6|confirmed',
            'avatar' => 'nullable|image|max:2048'
        ];
    }

    // Méthodes statiques
    public static function getRoleOptions()
    {
        return self::ROLES;
    }

    public static function getRolePermissions($role)
    {
        return self::PERMISSIONS[$role] ?? [];
    }

    public static function getManagedResources($role)
    {
        return self::MANAGED_RESOURCES[$role] ?? [];
    }

    // Créer des utilisateurs par défaut
    public static function createDefaultUsers()
    {
        if (self::where('email', 'admin@aquagestion.com')->doesntExist()) {
            self::create([
                'name' => 'Administrateur',
                'email' => 'admin@aquagestion.com',
                'password' => bcrypt('password123'),
                'role' => self::ROLE_ADMIN,
                'telephone' => '+22901000000',
                'address' => 'Abomey-Calavi, Bénin',
                'active' => true
            ]);
        }

        if (self::where('email', 'gestionnaire@aquagestion.com')->doesntExist()) {
            self::create([
                'name' => 'Gestionnaire',
                'email' => 'gestionnaire@aquagestion.com',
                'password' => bcrypt('password123'),
                'role' => self::ROLE_MANAGER,
                'telephone' => '+22902000000',
                'active' => true
            ]);
        }

        if (self::where('email', 'caissier@aquagestion.com')->doesntExist()) {
            self::create([
                'name' => 'Caissier',
                'email' => 'caissier@aquagestion.com',
                'password' => bcrypt('password123'),
                'role' => self::ROLE_CASHIER,
                'telephone' => '+22903000000',
                'active' => true
            ]);
        }
    }
}
