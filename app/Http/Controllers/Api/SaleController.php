<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Product;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $query = Sale::with(['user', 'client', 'details.product']);

        // Filtres
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('reference', 'LIKE', "%{$search}%")
                  ->orWhereHas('client', function($q) use ($search) {
                      $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('telephone', 'LIKE', "%{$search}%");
                  });
            });
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('payment_method') && $request->payment_method) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('user_id') && $request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        // Tri
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $sales = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $sales,
            'message' => 'Ventes récupérées avec succès'
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'nullable|exists:clients,id',
            'client_name' => 'required_without:client_id|string|max:255',
            'client_phone' => 'required_without:client_id|string|max:20',
            'payment_method' => 'required|in:cash,mobile_money,card,credit',
            'payment_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Vérifier le stock avant de commencer
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                if (!$product || $product->stock_quantity < $item['quantity']) {
                    throw new \Exception("Stock insuffisant pour {$product->name}");
                }
            }

            // Créer ou récupérer le client
            $client = null;
            if ($request->client_id) {
                $client = Client::find($request->client_id);
            } elseif ($request->client_name) {
                $client = Client::firstOrCreate(
                    ['telephone' => $request->client_phone],
                    [
                        'name' => $request->client_name,
                        'contact' => $request->client_name,
                        'telephone' => $request->client_phone
                    ]
                );
            }

            // Générer une référence unique
            $reference = 'SALE-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

            // Créer la vente
            $sale = Sale::create([
                'reference' => $reference,
                'user_id' => auth()->id(),
                'client_id' => $client?->id,
                'total_amount' => 0,
                'status' => 'completed',
                'payment_method' => $request->payment_method,
                'payment_reference' => $request->payment_reference,
                'notes' => $request->notes
            ]);

            $totalAmount = 0;

            // Ajouter les produits
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                $quantity = $item['quantity'];
                $unitPrice = $product->unit_price;
                $subtotal = $unitPrice * $quantity;

                // Créer le détail de vente
                SaleDetail::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal
                ]);

                // Mettre à jour le stock
                $product->updateStock($quantity, 'out', "Vente #{$reference}");

                $totalAmount += $subtotal;
            }

            // Mettre à jour le montant total
            $sale->update(['total_amount' => $totalAmount]);

            // Générer la facture
            $invoice = $sale->generateInvoice();

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'sale' => $sale->load(['user', 'client', 'details.product', 'invoice']),
                    'invoice' => $invoice
                ],
                'message' => 'Vente créée avec succès'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $sale = Sale::with(['user', 'client', 'details.product', 'invoice'])->find($id);

        if (!$sale) {
            return response()->json([
                'success' => false,
                'message' => 'Vente non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $sale,
            'message' => 'Vente récupérée avec succès'
        ]);
    }

    public function cancel($id)
    {
        $sale = Sale::with('details.product')->find($id);

        if (!$sale) {
            return response()->json([
                'success' => false,
                'message' => 'Vente non trouvée'
            ], 404);
        }

        if ($sale->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Vente déjà annulée'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Restaurer le stock
            foreach ($sale->details as $detail) {
                $detail->product->updateStock(
                    $detail->quantity,
                    'in',
                    "Annulation vente #{$sale->reference}"
                );
            }

            // Mettre à jour le statut
            $sale->update(['status' => 'cancelled']);

            // Annuler la facture associée
            if ($sale->invoice) {
                $sale->invoice->update(['status' => 'cancelled']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vente annulée avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation de la vente'
            ], 500);
        }
    }

    public function generateInvoice($id)
    {
        $sale = Sale::with('details.product')->find($id);

        if (!$sale) {
            return response()->json([
                'success' => false,
                'message' => 'Vente non trouvée'
            ], 404);
        }

        if ($sale->invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Facture déjà générée'
            ], 400);
        }

        $invoice = $sale->generateInvoice();

        return response()->json([
            'success' => true,
            'data' => $invoice,
            'message' => 'Facture générée avec succès'
        ]);
    }

    public function statistics(Request $request)
    {
        $period = $request->get('period', 'today');
        
        $query = Sale::where('status', 'completed');

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

        $totalSales = $query->count();
        $totalRevenue = $query->sum('total_amount');
        $avgTicket = $totalSales > 0 ? $totalRevenue / $totalSales : 0;

        // Ventes par méthode de paiement
        $paymentMethods = Sale::select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as amount'))
                             ->where('status', 'completed')
                             ->groupBy('payment_method')
                             ->get();

        // Top produits
        $topProducts = SaleDetail::select('product_id', DB::raw('SUM(quantity) as total_quantity'))
                                 ->with('product')
                                 ->whereHas('sale', function($q) {
                                     $q->where('status', 'completed');
                                 })
                                 ->groupBy('product_id')
                                 ->orderBy('total_quantity', 'desc')
                                 ->limit(5)
                                 ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_sales' => $totalSales,
                'total_revenue' => $totalRevenue,
                'today_sales' => $query->whereDate('created_at', today())->count(),
                'today_revenue' => $query->whereDate('created_at', today())->sum('total_amount'),
                'total_products' => Product::count(),
                'clients_count' => Client::count(),
                'clients_name' => Client::pluck('name'),
                'client_actives' => Client::whereHas('sales')->count(),
                'avg_ticket' => $avgTicket,
                'payment_methods' => $paymentMethods,
                'top_products' => $topProducts
            ],
            'message' => 'Statistiques récupérées avec succès'
        ]);
    }

    // Nouvelle méthode pour les ventes par jour affiche graphique
    public function ventesParJour(Request $request)
    {
        $days = (int) $request->query('days', 7);

        $startDate = Carbon::now()->subDays($days);

        $data = DB::table('ventes')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(montant) as total')
            )
            ->where('created_at', '>=', $startDate)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'asc')
            ->get();

        return response()->json($data);
    }

    // Méthode alternative pour les statistiques quotidiennes des ventes
    public function dailyStatistics(Request $request)
    {
        $days = (int) $request->query('days', 7);
        $startDate = Carbon::now()->subDays($days);

        $data = Sale::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_amount) as total')
            )
            ->where('created_at', '>=', $startDate)
            ->whereNull('deleted_at')   // important si soft delete actif
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
    public function dashboardStats()
    {
        $dashboardStats = [
            'today_sales' => DB::table('sales')->whereDate('created_at', now())->sum('amount'),
            'total_sales' => DB::table('sales')->sum('amount'),
        ];

        return response()->json($dashboardStats);
    }
    
}