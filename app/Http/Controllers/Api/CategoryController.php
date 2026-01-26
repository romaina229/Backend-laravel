<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    /**
     * Liste toutes les catégories
     * GET /api/v1/categories
     */
    public function index()
    {
        try {
            $categories = Category::orderBy('name')->get();
            
            Log::info('[Categories] Loaded: ' . $categories->count() . ' items');
            
            return response()->json([
                'success' => true,
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            Log::error('[Categories] Error loading: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des catégories: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crée une nouvelle catégorie
     * POST /api/v1/categories
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:categories',
            'description' => 'nullable|string',
            'couleur' => 'nullable|string|max:20',
            'actif' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $category = Category::create([
                'name' => $request->name,
                'description' => $request->description,
                'couleur' => $request->couleur ?? '#1890ff',
                'actif' => $request->actif ?? true
            ]);

            Log::info('[Categories] Created: ' . $category->name);

            return response()->json([
                'success' => true,
                'data' => $category,
                'message' => 'Catégorie créée avec succès'
            ], 201);
        } catch (\Exception $e) {
            Log::error('[Categories] Error creating: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Affiche une catégorie
     * GET /api/v1/categories/{id}
     */
    public function show($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Catégorie non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $category
        ]);
    }

    /**
     * Met à jour une catégorie
     * PUT /api/v1/categories/{id}
     */
    public function update(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Catégorie non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255|unique:categories,name,' . $id,
            'description' => 'nullable|string',
            'couleur' => 'nullable|string|max:20',
            'actif' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $category->update($request->all());

            Log::info('[Categories] Updated: ' . $category->name);

            return response()->json([
                'success' => true,
                'data' => $category,
                'message' => 'Catégorie mise à jour avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('[Categories] Error updating: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprime une catégorie
     * DELETE /api/v1/categories/{id}
     */
    public function destroy($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Catégorie non trouvée'
            ], 404);
        }

        // Vérifier si des produits utilisent cette catégorie
        if ($category->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer: des produits utilisent cette catégorie'
            ], 400);
        }

        try {
            $category->delete();

            Log::info('[Categories] Deleted: ' . $category->name);

            return response()->json([
                'success' => true,
                'message' => 'Catégorie supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('[Categories] Error deleting: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 500);
        }
    }
}