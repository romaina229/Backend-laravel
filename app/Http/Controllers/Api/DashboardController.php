<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Client;
use App\Models\MobileTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $period = $request->get('period', 'today');

        // Ventes du jour
        $todaySales = Sale::whereDate('created_at', today())
            ->where('status', 'completed')
            ->sum('total_amount');

        $todaySalesCount = Sale::whereDate('created_at', today())
            ->where('status', 'completed')
            ->count();

        // Ventes de la semaine
        $weekSales = Sale::whereBetween('created_at', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek()
        ])->where('status', 'completed')->sum('total_amount');

        // Ventes du mois
        $monthSales = Sale::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('status', 'completed')
            ->sum('total_amount');

        // Transactions mobiles du jour
        $todayTransactions = MobileTransaction::whereDate('created_at', today())
            ->where('status', 'completed')
            ->sum('amount');

        $todayTransactionsCount = MobileTransaction::whereDate('created_at', today())
            ->where('status', 'completed')
            ->count();

        // Alertes stock
        $lowStockCount = Product::whereRaw('stock_quantity <= alert_threshold')
            ->where('stock_quantity', '>', 0)
            ->count();

        // Nouveaux clients ce mois
        $newClientsThisMonth = Client::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Panier moyen
        $avgTicket = Sale::where('status', 'completed')
            ->whereDate('created_at', today())
            ->avg('total_amount');

        // Statistiques par opérateur
        $operatorsStats = MobileTransaction::select('operator',
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(amount) as total_amount')
        )
        ->whereDate('created_at', today())
        ->groupBy('operator')
        ->get();

        // Tendances (comparaison avec hier)
        $yesterdaySales = Sale::whereDate('created_at', Carbon::yesterday())
            ->where('status', 'completed')
            ->sum('total_amount');

        $salesTrend = $yesterdaySales > 0 
            ? (($todaySales - $yesterdaySales) / $yesterdaySales) * 100 
            : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'today_sales' => $todaySalesCount,
                'today_revenue' => $todaySales,
                'week_revenue' => $weekSales,
                'month_revenue' => $monthSales,
                'today_transactions' => $todayTransactionsCount,
                'mobile_revenue' => $todayTransactions,
                'low_stock_alerts' => $lowStockCount,
                'new_clients_month' => $newClientsThisMonth,
                'avg_ticket' => $avgTicket,
                'sales_trend' => $salesTrend,
                'operators_stats' => $operatorsStats,
                'summary' => [
                    'total_revenue' => $todaySales + $todayTransactions,
                    'total_operations' => $todaySalesCount + $todayTransactionsCount
                ]
            ],
            'message' => 'Statistiques dashboard récupérées'
        ]);
    }

    public function topProducts(Request $request)
    {
        $limit = $request->get('limit', 5);
        $period = $request->get('period', 'today');

        $query = DB::table('sale_details')
            ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
            ->join('products', 'sale_details.product_id', '=', 'products.id')
            ->where('sales.status', 'completed');

        switch ($period) {
            case 'today':
                $query->whereDate('sales.created_at', today());
                break;
            case 'week':
                $query->whereBetween('sales.created_at', [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek()
                ]);
                break;
            case 'month':
                $query->whereMonth('sales.created_at', now()->month)
                    ->whereYear('sales.created_at', now()->year);
                break;
        }

        $topProducts = $query->select(
                'products.id',
                'products.name',
                'products.unit',
                DB::raw('SUM(sale_details.quantity) as total_quantity'),
                DB::raw('SUM(sale_details.subtotal) as total_revenue'),
                DB::raw('COUNT(DISTINCT sales.id) as sales_count')
            )
            ->groupBy('products.id', 'products.name', 'products.unit')
            ->orderBy('total_quantity', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $topProducts,
            'message' => 'Top produits récupérés'
        ]);
    }

    public function recentSales(Request $request)
    {
        $limit = $request->get('limit', 5);

        $recentSales = Sale::with(['client', 'user'])
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($sale) {
                return [
                    'id' => $sale->id,
                    'reference' => $sale->reference,
                    'client' => $sale->client,
                    'user' => $sale->user->name,
                    'total_amount' => $sale->total_amount,
                    'payment_method' => $sale->payment_method,
                    'status' => $sale->status,
                    'created_at' => $sale->created_at,
                    'item_count' => $sale->details->count()
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $recentSales,
            'message' => 'Ventes récentes récupérées'
        ]);
    }

    public function lowStock(Request $request)
    {
        $limit = $request->get('limit', 5);

        $lowStockProducts = Product::with('category')
            ->whereRaw('stock_quantity <= alert_threshold')
            ->where('stock_quantity', '>', 0)
            ->orderByRaw('stock_quantity / alert_threshold')
            ->limit($limit)
            ->get()
            ->map(function($product) {
                $percentage = ($product->stock_quantity / $product->alert_threshold) * 100;
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category->name,
                    'stock_quantity' => $product->stock_quantity,
                    'unit' => $product->unit,
                    'alert_threshold' => $product->alert_threshold,
                    'percentage' => min(100, $percentage),
                    'status' => $percentage <= 30 ? 'critical' : ($percentage <= 60 ? 'warning' : 'normal')
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $lowStockProducts,
            'message' => 'Alertes stock récupérées'
        ]);
    }

    public function salesChart(Request $request)
    {
        $days = $request->get('days', 7);

        $salesData = Sale::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as sales_count'),
                DB::raw('SUM(total_amount) as total_amount')
            )
            ->where('status', 'completed')
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Remplir les jours manquants
        $dates = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $dates[$date] = [
                'date' => $date,
                'sales_count' => 0,
                'total_amount' => 0
            ];
        }

        foreach ($salesData as $sale) {
            $dates[$sale->date] = $sale->toArray();
        }

        return response()->json([
            'success' => true,
            'data' => array_values($dates),
            'message' => 'Données graphique ventes récupérées'
        ]);
    }

    public function performanceMetrics(Request $request)
    {
        // Conversion rate (ventes / transactions totales)
        $totalSales = Sale::whereDate('created_at', today())->count();
        $completedSales = Sale::whereDate('created_at', today())
            ->where('status', 'completed')
            ->count();

        $conversionRate = $totalSales > 0 ? ($completedSales / $totalSales) * 100 : 0;

        // Taux de réussite transactions mobiles
        $totalTransactions = MobileTransaction::whereDate('created_at', today())->count();
        $completedTransactions = MobileTransaction::whereDate('created_at', today())
            ->where('status', 'completed')
            ->count();

        $transactionSuccessRate = $totalTransactions > 0 
            ? ($completedTransactions / $totalTransactions) * 100 
            : 0;

        // Temps moyen de traitement
        $avgProcessingTime = Sale::whereDate('created_at', today())
            ->whereNotNull('completed_at')
            ->avg(DB::raw('TIME_TO_SEC(TIMEDIFF(completed_at, created_at))'));

        // Satisfaction client (basé sur les notes)
        $avgRating = 4.5; // À implémenter avec un système de notation

        return response()->json([
            'success' => true,
            'data' => [
                'conversion_rate' => round($conversionRate, 1),
                'transaction_success_rate' => round($transactionSuccessRate, 1),
                'avg_processing_time' => round($avgProcessingTime / 60, 1), // en minutes
                'customer_satisfaction' => $avgRating,
                'efficiency_score' => round(($conversionRate + $transactionSuccessRate + $avgRating * 20) / 3, 1)
            ],
            'message' => 'Métriques de performance récupérées'
        ]);
    }
}