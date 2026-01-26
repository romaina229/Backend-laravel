<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;

class StockService
{
    public function updateStock(Product $product, int $quantity, string $type = 'sortie'): void
    {
        $oldQuantity = $product->stock_quantity;
        
        if ($type === 'sortie') {
            $newQuantity = max(0, $oldQuantity - $quantity);
        } else {
            $newQuantity = $oldQuantity + $quantity;
        }
        
        $product->stock_quantity = $newQuantity;
        
        // Mettre à jour le statut
        if ($newQuantity <= 0) {
            $product->status = 'rupture';
        } elseif ($newQuantity <= 10) {
            $product->status = 'seuil_bas';
        } else {
            $product->status = 'disponible';
        }
        
        $product->save();
        
        // Enregistrer le mouvement
        StockMovement::create([
            'product_id' => $product->id,
            'quantity_in' => $type === 'entree' ? $quantity : 0,
            'quantity_out' => $type === 'sortie' ? $quantity : 0,
            'date' => now(),
            'type' => $type === 'sortie' ? 'vente' : 'approvisionnement'
        ]);
    }
    
    public function checkLowStock(): array
    {
        return Product::where('status', 'seuil_bas')
            ->orWhere('status', 'rupture')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'stock' => $product->stock_quantity,
                    'status' => $product->status,
                    'unit_price' => $product->unit_price
                ];
            })
            ->toArray();
    }
}