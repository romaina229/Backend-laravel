<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category');

        // Filtres
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhereHas('category', function($q) use ($search) {
                      $q->where('name', 'LIKE', "%{$search}%");
                  });
            });
        }

        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('low_stock') && $request->low_stock) {
            $query->whereRaw('stock_quantity <= alert_threshold');
        }

        if ($request->has('out_of_stock') && $request->out_of_stock) {
            $query->where('stock_quantity', '<=', 0)
                  ->orWhere('status', 'out_of_stock');
        }

        // Tri
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products,
            'message' => 'Produits récupérés avec succès'
        ]);
    }

    public function store(Request $request)
    {
        Log::info('[Product] Creating product:', $request->all());
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'unit_price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|numeric|min:0',
            'unit' => 'required|string|max:20',
            'alert_threshold' => 'required|numeric|min:0',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            Log::error('[Product] Validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $product = Product::create([
                'name' => $request->name,
                'category_id' => $request->category_id,
                'unit_price' => $request->unit_price,
                'stock_quantity' => $request->stock_quantity,
                'unit' => $request->unit,
                'alert_threshold' => $request->alert_threshold,
                'status' => $request->stock_quantity > 0 ? 'available' : 'out_of_stock',
                'description' => $request->description
            ]);

            // ✅ CORRECTION: Créer stock_movement seulement si la table existe
            // Vérifier si la table stock_movements existe
            try {
                if ($request->stock_quantity > 0 && \Schema::hasTable('stock_movements')) {
                    // Vérifier si le modèle StockMovement existe
                    if (class_exists('App\Models\StockMovement')) {
                        $product->stockMovements()->create([
                            'type' => 'in',
                            'quantity' => $request->stock_quantity,
                            'unit_price' => $request->unit_price,
                            'reason' => 'Stock initial',
                            'user_id' => auth()->id()
                        ]);
                    }
                }
            } catch (\Exception $e) {
                // Si erreur sur stock_movements, on continue quand même
                Log::warning('[Product] Stock movement not created: ' . $e->getMessage());
            }

            DB::commit();

            Log::info('[Product] Product created successfully:', ['id' => $product->id]);

            return response()->json([
                'success' => true,
                'data' => $product->load('category'),
                'message' => 'Produit créé avec succès'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Product] Error creating product: ' . $e->getMessage());
            Log::error('[Product] Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du produit: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $product = Product::with('category')->find($id);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produit non trouvé'
                ], 404);
            }

            // Charger stock_movements seulement si disponible
            if (\Schema::hasTable('stock_movements')) {
                $product->load(['stockMovements' => function($q) {
                    $q->orderBy('created_at', 'desc')->limit(10);
                }]);
            }

            return response()->json([
                'success' => true,
                'data' => $product,
                'message' => 'Produit récupéré avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('[Product] Error showing product: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du produit'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'category_id' => 'sometimes|required|exists:categories,id',
            'unit_price' => 'sometimes|required|numeric|min:0',
            'stock_quantity' => 'sometimes|required|numeric|min:0',
            'unit' => 'sometimes|required|string|max:20',
            'alert_threshold' => 'sometimes|required|numeric|min:0',
            'status' => 'sometimes|required|in:available,out_of_stock,discontinued',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Handle stock quantity changes
            if ($request->has('stock_quantity') && $request->stock_quantity != $product->stock_quantity) {
                $difference = $request->stock_quantity - $product->stock_quantity;
                
                // Créer movement seulement si la table existe
                try {
                    if (\Schema::hasTable('stock_movements') && class_exists('App\Models\StockMovement')) {
                        $product->stockMovements()->create([
                            'type' => 'adjustment',
                            'quantity' => abs($difference),
                            'unit_price' => $request->unit_price ?? $product->unit_price,
                            'reason' => 'Ajustement manuel du stock',
                            'user_id' => auth()->id()
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('[Product] Stock movement not created: ' . $e->getMessage());
                }
            }

            $product->update($request->all());

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $product->load('category'),
                'message' => 'Produit mis à jour avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Product] Error updating product: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du produit'
            ], 500);
        }
    }

    public function destroy($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], 404);
        }

        // Check if product has sales
        if (method_exists($product, 'saleDetails') && $product->saleDetails()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer un produit avec des ventes associées'
            ], 400);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produit supprimé avec succès'
        ]);
    }

    public function lowStock()
    {
        $products = Product::whereRaw('stock_quantity <= alert_threshold')
                          ->where('status', 'available')
                          ->with('category')
                          ->get();

        return response()->json([
            'success' => true,
            'data' => $products,
            'message' => 'Produits en stock faible récupérés'
        ]);
    }
}