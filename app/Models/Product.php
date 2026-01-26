<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'category_id',
        'unit_price',
        'stock_quantity',
        'unit',
        'alert_threshold',
        'status',
        'description'
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'stock_quantity' => 'decimal:2',
        'alert_threshold' => 'decimal:2'
    ];

    // Relations
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function saleDetails()
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available')
                    ->where('stock_quantity', '>', 0);
    }

    public function scopeLowStock($query)
    {
        return $query->where('stock_quantity', '<=', $this->alert_threshold)
                    ->where('status', 'available');
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('stock_quantity', '<=', 0)
                    ->orWhere('status', 'out_of_stock');
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    // Helpers
    public function updateStock($quantity, $type = 'out', $reason = null)
    {
        $oldQuantity = $this->stock_quantity;

        if ($type === 'out') {
            $this->stock_quantity -= $quantity;
        } else {
            $this->stock_quantity += $quantity;
        }

        // Update status based on stock
        if ($this->stock_quantity <= 0) {
            $this->status = 'out_of_stock';
        } elseif ($this->stock_quantity > 0 && $this->status === 'out_of_stock') {
            $this->status = 'available';
        }

        $this->save();

        // CORRECTION: Créer le mouvement de stock AVEC quantity
        StockMovement::create([
            'product_id' => $this->id,
            'type' => $type,
            'quantity' => $quantity, // AJOUTÉ: Le champ manquant !
            'unit_price' => $this->unit_price,
            'reason' => $reason ?? 'Stock adjustment',
            'user_id' => auth()->id()
        ]);

        return $this;
    }

    public function isLowStock()
    {
        return $this->stock_quantity <= $this->alert_threshold;
    }
}