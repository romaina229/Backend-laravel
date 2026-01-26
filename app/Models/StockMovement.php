<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'quantity',
        'type',
        'unit_price',
        'reference',
        'reason',
        'user_id'
    ];

    protected $casts = [
        'date' => 'datetime'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getQuantityAttribute()
    {
        return $this->quantity_in - $this->quantity_out;
    }
}