<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'sale_id',
        'invoice_date',
        'total_amount'
    ];

    protected $casts = [
        'date' => 'date',
        'total_amount' => 'decimal:2'
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            $invoice->invoice_number = 'FAC-' . date('Ymd') . '-' . str_pad(Invoice::count() + 1, 4, '0', STR_PAD_LEFT);
        });
    }
}