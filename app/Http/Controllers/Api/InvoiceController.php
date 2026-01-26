<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::with('sale.client', 'sale.user');

        // Recherche
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('invoice_number', 'LIKE', "%{$search}%")
                  ->orWhereHas('sale.client', function($q) use ($search) {
                      $q->where('name', 'LIKE', "%{$search}%");
                  });
            });
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('invoice_date', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('invoice_date', '<=', $request->date_to);
        }

        // Tri
        $sortBy = $request->get('sort_by', 'invoice_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $invoices = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $invoices,
            'message' => 'Factures récupérées avec succès'
        ]);
    }

    public function show($id)
    {
        $invoice = Invoice::with([
            'sale.client',
            'sale.user',
            'sale.details.product'
        ])->find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Facture non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $invoice,
            'message' => 'Facture récupérée avec succès'
        ]);
    }

    public function download($id)
    {
        $invoice = Invoice::with([
            'sale.client',
            'sale.user',
            'sale.details.product'
        ])->find($id);

        if (!$invoice) {
            abort(404);
        }

        $pdf = Pdf::loadView('invoices.template', [
            'invoice' => $invoice,
            'date' => now()->format('d/m/Y'),
            'company' => [
                'name' => 'AquaGestion',
                'address' => 'Bénin, Abomey-Calavi',
                'phone' => '+229 01 69 35 17 66',
                'email' => 'liferopro@gmail.com'
            ]
        ]);

        return $pdf->download("facture-{$invoice->invoice_number}.pdf");
    }

    public function sendEmail(Request $request, $id)
    {
        $invoice = Invoice::with('sale.client')->find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Facture non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Générer le PDF
            $pdf = Pdf::loadView('invoices.template', [
                'invoice' => $invoice,
                'date' => now()->format('d/m/Y'),
                'company' => [
                    'name' => 'AquaGestion',
                    'address' => 'Bénin, Abomey-Calavi',
                    'phone' => '+229 01 69 35 17 66',
                    'email' => 'liferopro@gmail.com'
                ]
            ]);

            // Envoyer l'email
            // Mail::to($request->email)->send(new InvoiceMail($invoice, $pdf));

            // Mettre à jour le statut
            $invoice->update(['status' => 'sent']);

            return response()->json([
                'success' => true,
                'message' => 'Facture envoyée par email avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de l\'email'
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $invoice = Invoice::find($id);

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Facture non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:draft,sent,paid,overdue,cancelled'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $invoice->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Statut de la facture mis à jour'
        ]);
    }

    public function stats(Request $request)
    {
        $period = $request->get('period', 'month');

        $query = Invoice::query();

        switch ($period) {
            case 'today':
                $query->whereDate('invoice_date', today());
                break;
            case 'week':
                $query->whereBetween('invoice_date', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ]);
                break;
            case 'month':
                $query->whereMonth('invoice_date', now()->month)
                      ->whereYear('invoice_date', now()->year);
                break;
            case 'year':
                $query->whereYear('invoice_date', now()->year);
                break;
        }

        $stats = [
            'total_invoices' => $query->count(),
            'total_amount' => $query->sum('total_amount'),
            'paid_amount' => $query->where('status', 'paid')->sum('total_amount'),
            'pending_amount' => $query->whereIn('status', ['draft', 'sent'])->sum('total_amount'),
            'overdue_amount' => $query->where('status', 'overdue')->sum('total_amount'),
            'by_status' => $query->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as amount'))
                ->groupBy('status')
                ->get()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => 'Statistiques factures récupérées'
        ]);
    }
}