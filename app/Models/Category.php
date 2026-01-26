<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'categories';

    protected $fillable = [
        'name',
        'description',
        'couleur',
        'actif'
    ];

    protected $casts = [
        'actif' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $attributes = [
        'couleur' => '#1890ff',
        'actif' => true
    ];

    /**
     * Relation avec les produits
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Scope pour les catégories actives
     */
    public function scopeActive($query)
    {
        return $query->where('actif', true);
    }

    /**
     * Obtenir le nombre de produits
     */
    public function getProductsCountAttribute()
    {
        return $this->products()->count();
    }
}