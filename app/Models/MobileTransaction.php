<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MobileTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference',
        'operator',
        'amount',
        'client_name',
        'client_phone',
        'external_reference',
        'status',
        'type',
        'notes',
        'user_id'
    ];

    protected $casts = [
        'amount' => 'decimal:2'
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeByOperator($query, $operator)
    {
        return $query->where('operator', $operator);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
    }

    // Helpers
    public function generateReference()
    {
        $prefix = strtoupper($this->operator);
        $date = date('Ymd');
        $random = strtoupper(substr(uniqid(), -6));
        
        return "{$prefix}-{$date}-{$random}";
    }

    public function calculateFees()
    {
        return match($this->operator) {
            'MTN' => min(500, max(50, $this->amount * 0.01)),
            'MOOV' => min(400, max(40, $this->amount * 0.009)),
            'CELTIS' => min(450, max(45, $this->amount * 0.0095)),
            default => 0
        };
    }

    public function getNetAmount()
    {
        return $this->amount - $this->calculateFees();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            if (empty($transaction->reference)) {
                $transaction->reference = $transaction->generateReference();
            }
        });
    }
}