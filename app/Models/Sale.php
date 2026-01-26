<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference',
        'user_id',
        'client_id',
        'total_amount',
        'tax_amount',
        'discount_amount',
        'status',
        'payment_method',
        'payment_reference',
        'notes'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2'
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function details()
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    // Scopes
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    // Attributs calculés
    public function getMontantPayeAttribute()
    {
        // Logique pour calculer le montant déjà payé
        // À adapter selon votre logique métier
        if ($this->status === 'completed') {
            return $this->total_amount;
        }
        return 0;
    }

    public function getResteAPayerAttribute()
    {
        return $this->total_amount - $this->montant_paye;
    }

    // Helpers
    public function calculateTotal()
    {
        return $this->details->sum('subtotal');
    }

    public function addProduct(Product $product, $quantity)
    {
        if ($product->stock_quantity < $quantity) {
            throw new \Exception('Stock insuffisant');
        }

        $subtotal = $product->unit_price * $quantity;

        $detail = SaleDetail::create([
            'sale_id' => $this->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $product->unit_price,
            'subtotal' => $subtotal
        ]);

        // Update stock
        $product->updateStock($quantity, 'out', "Vente #{$this->reference}");

        // Update sale total
        $this->total_amount = $this->calculateTotal();
        $this->save();

        return $detail;
    }

    public function generateInvoice()
    {
        if ($this->invoice) {
            return $this->invoice;
        }

        $invoice = Invoice::create([
            'invoice_number' => 'INV-' . str_pad($this->id, 6, '0', STR_PAD_LEFT),
            'sale_id' => $this->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'total_amount' => $this->total_amount,
            'tax_amount' => $this->tax_amount,
            'status' => 'draft'
        ]);

        return $invoice;
    }

    // Formatage pour API
    public function toApiArray()
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'total_amount' => (float) $this->total_amount,
            'tax_amount' => (float) $this->tax_amount,
            'discount_amount' => (float) $this->discount_amount,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'notes' => $this->notes,
            'created_at' => $this->created_at->toISOString(),
            'montant_paye' => $this->montant_paye,
            'reste_a_payer' => $this->reste_a_payer,
            'client' => $this->client ? [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'telephone' => $this->client->telephone,
                'email' => $this->client->email,
                'address' => $this->client->address,
                'total_achats' => (float) ($this->client->sales()->sum('total_amount') ?? 0)
            ] : null,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email
            ] : null,
            'items' => $this->details->map(function ($detail) {
                return [
                    'id' => $detail->id,
                    'product' => $detail->product ? [
                        'id' => $detail->product->id,
                        'name' => $detail->product->name,
                        'barcode' => $detail->product->barcode,
                        'unit' => $detail->product->unit
                    ] : null,
                    'quantity' => (int) $detail->quantity,
                    'unit_price' => (float) $detail->unit_price,
                    'subtotal' => (float) $detail->subtotal
                ];
            }),
            'invoice' => $this->invoice ? [
                'id' => $this->invoice->id,
                'invoice_number' => $this->invoice->invoice_number,
                'invoice_date' => $this->invoice->invoice_date->toISOString()
            ] : null
        ];
    }
}