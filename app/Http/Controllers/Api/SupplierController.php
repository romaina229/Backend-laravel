<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('contact_person', 'like', "%$search%")
                  ->orWhere('telephone', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%");
            });
        }

        $suppliers = $query->orderBy('name')->paginate(
            $request->get('per_page', 15)
        );

        return response()->json([
            'success' => true,
            'data' => $suppliers
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'contact_person' => 'required|string|max:255',
            'telephone' => 'required|string|max:30',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',

            'type_produits' => 'nullable|string',
            'delai_livraison' => 'nullable|integer|min:1|max:90',
            'conditions_paiement' => 'nullable|string|max:100',
            'evaluation' => 'nullable|integer|min:1|max:5',
            'actif' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $supplier = Supplier::create([
                'name' => $request->name,
                'contact_person' => $request->contact_person,
                'telephone' => $request->telephone,
                'email' => $request->email,
                'address' => $request->address,
                'city' => $request->city,
                'country' => $request->country,

                'type_produits' => $request->type_produits,
                'delai_livraison' => $request->delai_livraison,
                'conditions_paiement' => $request->conditions_paiement,
                'evaluation' => $request->evaluation ?? 3,
                'actif' => $request->actif ?? true,
                'notes' => $request->notes,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $supplier,
                'message' => 'Fournisseur créé avec succès'
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur interne lors de la création du fournisseur',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function show($id)
    {
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json([
                'success' => false,
                'message' => 'Fournisseur non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $supplier
        ]);
    }

    public function update(Request $request, $id)
    {
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json([
                'success' => false,
                'message' => 'Fournisseur non trouvé'
            ], 404);
        }

        $supplier->update($request->only($supplier->getFillable()));

        return response()->json([
            'success' => true,
            'data' => $supplier,
            'message' => 'Fournisseur mis à jour avec succès'
        ]);
    }

    public function destroy($id)
    {
        $supplier = Supplier::find($id);

        if (!$supplier) {
            return response()->json([
                'success' => false,
                'message' => 'Fournisseur non trouvé'
            ], 404);
        }

        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Fournisseur supprimé'
        ]);
    }
}
