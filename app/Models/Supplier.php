<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $table = 'suppliers';

    protected $fillable = [
        'name',
        'contact_person',
        'telephone',
        'email',
        'address',
        'city',
        'country',

        // Champs métier (frontend)
        'type_produits',
        'delai_livraison',
        'conditions_paiement',
        'evaluation',
        'actif',
        'notes',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'evaluation' => 'integer',
        'delai_livraison' => 'integer',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
