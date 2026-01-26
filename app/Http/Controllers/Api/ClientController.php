<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $query = Client::query();

        // Recherche
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('address', 'LIKE', "%{$search}%")
                  ->orWhere('telephone', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Tri
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $clients = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $clients,
            'message' => 'Clients récupérés avec succès'
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'telephone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $client = Client::create([
                'name' => $request->name,
                'telephone' => $request->telephone,
                'contact' => $request->name,
                'email' => $request->email,
                'address' => $request->address
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $client,
                'message' => 'Client créé avec succès'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du client'
            ], 500);
        }
    }

    public function show($id)
    {
        $client = Client::with(['sales' => function($q) {
            $q->orderBy('created_at', 'desc')->limit(10);
        }])->find($id);

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client non trouvé'
            ], 404);
        }

        // Calculer les statistiques
        $stats = [
            'total_sales' => $client->sales()->count(),
            'total_amount' => $client->sales()->sum('total_amount'),
            'last_purchase' => $client->sales()->latest()->first()?->created_at
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'client' => $client,
                'stats' => $stats
            ],
            'message' => 'Client récupéré avec succès'
        ]);
    }

    public function update(Request $request, $id)
    {
        $client = Client::find($id);

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'telephone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $client->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $client,
            'message' => 'Client mis à jour avec succès'
        ]);
    }

    public function destroy($id)
    {
        $client = Client::withCount('sales')->find($id);

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client non trouvé'
            ], 404);
        }

        if ($client->sales_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer un client avec des ventes associées'
            ], 400);
        }

        $client->delete();

        return response()->json([
            'success' => true,
            'message' => 'Client supprimé avec succès'
        ]);
    }

    public function searchByPhone($phone)
    {
        $client = Client::where('telephone', $phone)->first();

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $client,
            'message' => 'Client trouvé'
        ]);
    }

    public function stats(Request $request)
    {
        $stats = [
            'total_clients' => Client::count(),
            'active_clients' => Client::has('sales', '>=', 1)->count(),
            'new_this_month' => Client::whereMonth('created_at', now()->month)->count(),
            'top_clients' => Client::withSum('sales', 'total_amount')
                ->orderBy('sales_sum_total_amount', 'desc')
                ->limit(5)
                ->get(['id', 'name', 'sales_sum_total_amount'])
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => 'Statistiques clients récupérées avec succès'
        ]);
    }

        public function summary()
    {
        return response()->json([
            'data' => [
                'total_clients' => Client::count(),
                'active_clients' => Client::where('status', 'active')->count(),
                'inactive_clients' => Client::where('status', 'inactive')->count(),
                'new_clients_this_month' => Client::whereMonth('created_at', now()->month)->count(),
                //nouveaux indicateurs
                'clients_with_sales' => Client::has('sales')->count(),
                'clients_without_sales' => Client::doesntHave('sales')->count(),
                'recent_clients' => Client::orderBy('created_at', 'desc')->limit(5)->get(),
                'clients_active_this_month' => Client::where('status', 'active')
                    ->whereMonth('created_at', now()->month)
                    ->count(),
            ]
        ]);
    }
}