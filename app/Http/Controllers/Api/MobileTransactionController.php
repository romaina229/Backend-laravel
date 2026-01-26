<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class MobileTransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = MobileTransaction::with('user');

        // Filtres
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('reference', 'LIKE', "%{$search}%")
                  ->orWhere('client_name', 'LIKE', "%{$search}%")
                  ->orWhere('client_phone', 'LIKE', "%{$search}%")
                  ->orWhere('external_reference', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('operator') && $request->operator) {
            $query->where('operator', $request->operator);
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Tri
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $transactions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transactions,
            'message' => 'Transactions récupérées avec succès'
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'operator' => 'required|in:MTN,MOOV,CELTIS,ORANGE',
            'amount' => 'required|numeric|min:100',
            'client_name' => 'required|string|max:255',
            'client_phone' => 'required|string|max:20',
            'external_reference' => 'nullable|string|max:255',
            'type' => 'required|in:deposit,withdrawal,payment,transfer',
            'status' => 'required|in:pending,completed,failed,cancelled',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $transaction = MobileTransaction::create([
                'operator' => $request->operator,
                'amount' => $request->amount,
                'client_name' => $request->client_name,
                'client_phone' => $request->client_phone,
                'external_reference' => $request->external_reference,
                'type' => $request->type,
                'status' => $request->status,
                'notes' => $request->notes,
                'user_id' => auth()->id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $transaction,
                'message' => 'Transaction créée avec succès'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la transaction'
            ], 500);
        }
    }

    public function show($id)
    {
        $transaction = MobileTransaction::with('user')->find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $transaction,
            'message' => 'Transaction récupérée avec succès'
        ]);
    }

    public function update(Request $request, $id)
    {
        $transaction = MobileTransaction::find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'operator' => 'sometimes|in:MTN,MOOV,CELTIS,ORANGE',
            'amount' => 'sometimes|numeric|min:100',
            'client_name' => 'sometimes|string|max:255',
            'client_phone' => 'sometimes|string|max:20',
            'external_reference' => 'nullable|string|max:255',
            'type' => 'sometimes|in:deposit,withdrawal,payment,transfer',
            'status' => 'sometimes|in:pending,completed,failed,cancelled',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $transaction->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $transaction,
            'message' => 'Transaction mise à jour avec succès'
        ]);
    }

    public function destroy($id)
    {
        $transaction = MobileTransaction::find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction non trouvée'
            ], 404);
        }

        $transaction->delete();

        return response()->json([
            'success' => true,
            'message' => 'Transaction supprimée avec succès'
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $transaction = MobileTransaction::find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,completed,failed,cancelled'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $transaction->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Statut mis à jour avec succès'
        ]);
    }

    public function statistics(Request $request)
    {
        $period = $request->get('period', 'today');
        
        $query = MobileTransaction::query();

        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->whereBetween('created_at', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ]);
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month)
                      ->whereYear('created_at', now()->year);
                break;
            case 'year':
                $query->whereYear('created_at', now()->year);
                break;
        }

        $totalTransactions = $query->count();
        $totalAmount = $query->sum('amount');
        $successRate = $totalTransactions > 0 
            ? ($query->where('status', 'completed')->count() / $totalTransactions) * 100 
            : 0;

        // Par opérateur
        $byOperator = MobileTransaction::select('operator', 
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(amount) as total_amount'),
            DB::raw('SUM(CASE WHEN status = "completed" THEN amount ELSE 0 END) as completed_amount'))
            ->groupBy('operator')
            ->get();

        // Par statut
        $byStatus = MobileTransaction::select('status', 
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(amount) as total_amount'))
            ->groupBy('status')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_transactions' => $totalTransactions,
                'total_amount' => $totalAmount,
                'success_rate' => $successRate,
                'by_operator' => $byOperator,
                'by_status' => $byStatus
            ],
            'message' => 'Statistiques transactions récupérées'
        ]);
    }
}