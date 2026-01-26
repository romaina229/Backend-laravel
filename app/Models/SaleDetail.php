<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'unit_price',
        'subtotal' // Ajouté pour correspondre au frontend
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'quantity' => 'integer'
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Accessors pour correspondre au frontend
    public function getProduitNomAttribute()
    {
        return $this->product ? $this->product->name : 'Produit inconnu';
    }

    public function getProduitCodeBarreAttribute()
    {
        return $this->product ? $this->product->barcode : '';
    }

    public function getUniteAttribute()
    {
        return $this->product ? $this->product->unit : 'unité';
    }

    public function getPrixUnitaireAttribute()
    {
        return $this->unit_price;
    }

    public function getQuantiteAttribute()
    {
        return $this->quantity;
    }

    public function getSousTotalAttribute()
    {
        // Si subtotal n'existe pas en base, on le calcule
        if ($this->subtotal) {
            return $this->subtotal;
        }
        return $this->quantity * $this->unit_price;
    }

    // Method toAPIArray pour formater les données pour le frontend
    public function toAPIArray()
    {
        return [
            'id' => $this->id,
            'product' => $this->product ? [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'barcode' => $this->product->barcode,
                'unit' => $this->product->unit
            ] : null,
            'quantity' => (int) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'subtotal' => (float) $this->subtotal,
            // Pour compatibilité avec ancien frontend
            'produit_nom' => $this->produit_nom,
            'produit_code_barre' => $this->produit_code_barre,
            'unite' => $this->unite,
            'prix_unitaire' => (float) $this->unit_price,
            'sous_total' => (float) $this->sous_total
        ];
    }

    // Scope pour charger les relations
    public function scopeWithProduct($query)
    {
        return $query->with(['product' => function ($q) {
            $q->select(['id', 'name', 'barcode', 'unit', 'price', 'stock_quantity']);
        }]);
    }

    // Événements pour calculer le subtotal automatiquement
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($saleDetail) {
            // Calcule automatiquement le subtotal si vide
            if (!$saleDetail->subtotal) {
                $saleDetail->subtotal = $saleDetail->quantity * $saleDetail->unit_price;
            }
        });
    }
}