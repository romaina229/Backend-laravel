<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Client;
use App\Models\MobileTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SalesReportExport;
use App\Exports\InventoryReportExport;
use App\Exports\TransactionsReportExport;

class ReportController extends Controller
{
    public function sales(Request $request)
    {
        $validator = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'group_by' => 'nullable|in:day,week,month,year,product,category,client'
        ]);

        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;
        $groupBy = $request->group_by ?? 'day';

        $query = Sale::whereBetween('created_at', [$dateFrom, $dateTo])
                     ->where('status', 'completed');

        $reportData = [];

        switch ($groupBy) {
            case 'day':
                $reportData = $query->select(
                    DB::raw('DATE(created_at) as period'),
                    DB::raw('COUNT(*) as sales_count'),
                    DB::raw('SUM(total_amount) as total_amount'),
                    DB::raw('AVG(total_amount) as avg_ticket')
                )
                ->groupBy('period')
                ->orderBy('period')
                ->get();
                break;

            case 'week':
                $reportData = $query->select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('WEEK(created_at) as week'),
                    DB::raw('COUNT(*) as sales_count'),
                    DB::raw('SUM(total_amount) as total_amount'),
                    DB::raw('AVG(total_amount) as avg_ticket')
                )
                ->groupBy('year', 'week')
                ->orderBy('year')->orderBy('week')
                ->get();
                break;

            case 'month':
                $reportData = $query->select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('COUNT(*) as sales_count'),
                    DB::raw('SUM(total_amount) as total_amount'),
                    DB::raw('AVG(total_amount) as avg_ticket')
                )
                ->groupBy('year', 'month')
                ->orderBy('year')->orderBy('month')
                ->get();
                break;

            case 'product':
                $reportData = DB::table('sale_details')
                    ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
                    ->join('products', 'sale_details.product_id', '=', 'products.id')
                    ->whereBetween('sales.created_at', [$dateFrom, $dateTo])
                    ->where('sales.status', 'completed')
                    ->select(
                        'products.name as product_name',
                        DB::raw('SUM(sale_details.quantity) as total_quantity'),
                        DB::raw('SUM(sale_details.subtotal) as total_amount'),
                        DB::raw('AVG(sale_details.unit_price) as avg_price')
                    )
                    ->groupBy('products.id', 'products.name')
                    ->orderBy('total_quantity', 'desc')
                    ->get();
                break;

            case 'client':
                $reportData = $query->join('clients', 'sales.client_id', '=', 'clients.id')
                    ->select(
                        'clients.name as client_name',
                        DB::raw('COUNT(*) as purchase_count'),
                        DB::raw('SUM(sales.total_amount) as total_amount'),
                        DB::raw('MAX(sales.created_at) as last_purchase')
                    )
                    ->groupBy('clients.id', 'clients.name')
                    ->orderBy('total_amount', 'desc')
                    ->get();
                break;
        }

        // Statistiques générales
        $summary = [
            'total_sales' => $query->count(),
            'total_revenue' => $query->sum('total_amount'),
            'avg_ticket' => $query->avg('total_amount'),
            'unique_clients' => $query->distinct('client_id')->count('client_id'),
            'by_payment_method' => $query->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as amount'))
                ->groupBy('payment_method')
                ->get()
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'report' => $reportData,
                'summary' => $summary,
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo,
                    'group_by' => $groupBy
                ]
            ],
            'message' => 'Rapport des ventes généré avec succès'
        ]);
    }

    public function inventory(Request $request)
    {
        $validator = $request->validate([
            'filter' => 'nullable|in:all,low_stock,out_of_stock,available'
        ]);

        $filter = $request->filter ?? 'all';

        $query = Product::with('category');

        switch ($filter) {
            case 'low_stock':
                $query->whereRaw('stock_quantity <= alert_threshold')
                      ->where('stock_quantity', '>', 0);
                break;
            case 'out_of_stock':
                $query->where('stock_quantity', '<=', 0)
                      ->orWhere('status', 'out_of_stock');
                break;
            case 'available':
                $query->where('stock_quantity', '>', 0)
                      ->where('status', 'available');
                break;
        }

        $products = $query->orderBy('name')->get();

        // Statistiques du stock
        $stats = [
            'total_products' => Product::count(),
            'total_value' => Product::sum(DB::raw('stock_quantity * unit_price')),
            'low_stock_count' => Product::whereRaw('stock_quantity <= alert_threshold')
                ->where('stock_quantity', '>', 0)->count(),
            'out_of_stock_count' => Product::where('stock_quantity', '<=', 0)->count(),
            'by_category' => Product::join('categories', 'products.category_id', '=', 'categories.id')
                ->select('categories.name', DB::raw('COUNT(*) as count'), DB::raw('SUM(stock_quantity * unit_price) as value'))
                ->groupBy('categories.id', 'categories.name')
                ->get()
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $products,
                'stats' => $stats,
                'filter' => $filter
            ],
            'message' => 'Rapport d\'inventaire généré avec succès'
        ]);
    }

    public function transactions(Request $request)
    {
        $validator = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'operator' => 'nullable|in:MTN,MOOV,CELTIS,ORANGE',
            'status' => 'nullable|in:pending,completed,failed,cancelled'
        ]);

        $query = MobileTransaction::whereBetween('created_at', [$request->date_from, $request->date_to]);

        if ($request->has('operator')) {
            $query->where('operator', $request->operator);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();

        // Statistiques
        $stats = [
            'total_transactions' => $transactions->count(),
            'total_amount' => $transactions->sum('amount'),
            'success_rate' => $transactions->count() > 0 
                ? ($transactions->where('status', 'completed')->count() / $transactions->count()) * 100 
                : 0,
            'by_operator' => $transactions->groupBy('operator')->map(function($group) {
                return [
                    'count' => $group->count(),
                    'amount' => $group->sum('amount'),
                    'completed' => $group->where('status', 'completed')->sum('amount')
                ];
            }),
            'by_status' => $transactions->groupBy('status')->map(function($group) {
                return [
                    'count' => $group->count(),
                    'amount' => $group->sum('amount')
                ];
            }),
            'by_type' => $transactions->groupBy('type')->map(function($group) {
                return [
                    'count' => $group->count(),
                    'amount' => $group->sum('amount')
                ];
            })
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions,
                'stats' => $stats,
                'period' => [
                    'from' => $request->date_from,
                    'to' => $request->date_to
                ]
            ],
            'message' => 'Rapport des transactions généré avec succès'
        ]);
    }

    public function clients(Request $request)
    {
        $validator = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from'
        ]);

        $clients = Client::withCount(['sales as purchase_count' => function($q) use ($request) {
                $q->whereBetween('created_at', [$request->date_from, $request->date_to])
                  ->where('status', 'completed');
            }])
            ->withSum(['sales as total_spent' => function($q) use ($request) {
                $q->whereBetween('created_at', [$request->date_from, $request->date_to])
                  ->where('status', 'completed');
            }], 'total_amount')
            ->having('purchase_count', '>', 0)
            ->orderBy('total_spent', 'desc')
            ->get();

        // Top clients
        $topClients = $clients->take(10);

        // Distribution par montant dépensé
        $distribution = [
            '0-10000' => $clients->where('total_spent', '<', 10000)->count(),
            '10000-50000' => $clients->whereBetween('total_spent', [10000, 50000])->count(),
            '50000-100000' => $clients->whereBetween('total_spent', [50000, 100000])->count(),
            '100000+' => $clients->where('total_spent', '>=', 100000)->count()
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'clients' => $clients,
                'top_clients' => $topClients,
                'distribution' => $distribution,
                'stats' => [
                    'total_clients' => $clients->count(),
                    'total_spent' => $clients->sum('total_spent'),
                    'avg_spent' => $clients->avg('total_spent'),
                    'new_clients' => Client::whereBetween('created_at', [$request->date_from, $request->date_to])->count()
                ]
            ],
            'message' => 'Rapport clients généré avec succès'
        ]);
    }

    public function export(Request $request)
    {
        $validator = $request->validate([
            'type' => 'required|in:sales,inventory,transactions',
            'format' => 'required|in:excel,pdf,csv'
        ]);

        $fileName = 'report-' . $request->type . '-' . date('Ymd-His') . '.' . $request->format;

        switch ($request->type) {
            case 'sales':
                return Excel::download(new SalesReportExport($request->all()), $fileName);
            case 'inventory':
                return Excel::download(new InventoryReportExport($request->all()), $fileName);
            case 'transactions':
                return Excel::download(new TransactionsReportExport($request->all()), $fileName);
        }

        return response()->json([
            'success' => false,
            'message' => 'Type de rapport non supporté'
        ], 400);
    }
}