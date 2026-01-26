<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Sale;

class InvoiceService
{
    public function generateInvoice(Sale $sale): Invoice
    {
        return Invoice::create([
            'sale_id' => $sale->id,
            'date' => now(),
            'total_amount' => $sale->total_amount
        ]);
    }

    public function getInvoiceContent(Invoice $invoice): array
    {
        $invoice->load(['sale.details.product', 'sale.client', 'sale.user']);

        return [
            'invoice_number' => $invoice->invoice_number,
            'date' => $invoice->date->format('d/m/Y'),
            'client' => $invoice->sale->client,
            'seller' => $invoice->sale->user->name,
            'items' => $invoice->sale->details->map(function ($detail) {
                return [
                    'product' => $detail->product->name,
                    'quantity' => $detail->quantity,
                    'unit_price' => $detail->unit_price,
                    'subtotal' => $detail->subtotal
                ];
            }),
            'total_amount' => $invoice->total_amount,
            'tax_rate' => 5, // À adapter selon la législation
            'tax_amount' => $invoice->total_amount * 0.05,
            'total_with_tax' => $invoice->total_amount * 1.05
        ];
    }
}
