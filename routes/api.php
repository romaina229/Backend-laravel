<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\MobileTransactionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\ProfileController;

// Version 1 de l'API
Route::prefix('v1')->group(function () {

    // Authentification (publique)
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Routes protégées
    Route::middleware(['auth:sanctum'])->group(function () {

        // Auth
        Route::post('/logout', [AuthController::class, 'logout']); 
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/user/permissions', [UserController::class, 'getPermissions']);

        // Dashboard
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('/dashboard/top-products', [DashboardController::class, 'topProducts']);
        Route::get('/dashboard/recent-sales', [DashboardController::class, 'recentSales']);
        Route::get('/dashboard/low-stock', [DashboardController::class, 'lowStock']);

        // Produits
        Route::apiResource('products', ProductController::class);
        Route::get('/products/low-stock/alerts', [ProductController::class, 'lowStock']);
        Route::get('/products/search/{query}', [ProductController::class, 'search']); 

        // Catégories
        Route::apiResource('categories', CategoryController::class);

        // Ventes - ROUTES CORRIGÉES
        Route::apiResource('sales', SaleController::class);
        Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel']); 
        Route::post('/sales/{sale}/invoice', [SaleController::class, 'generateInvoice']); 
        Route::get('/sales/statistics/summary', [SaleController::class, 'statistics']);
        Route::get('/sales/{sale}/details', [SaleController::class, 'getDetails']);
        Route::get('/sales/ventes', [SaleController::class, 'ventesParJour']);
        Route::get('/sales/statistics/daily', [SaleController::class, 'dailyStatistics']);
        Route::get('/sales/statistics/monthly', [SaleController::class, 'monthlyStatistics']);
        Route::get('/sales/statistics/yearly', [SaleController::class, 'yearlyStatistics']);
        Route::get('/sales/statistics/top-products', [SaleController::class, 'topSellingProducts']);
        

        // Clients
        Route::apiResource('clients', ClientController::class);
        Route::get('/clients/search/phone/{phone}', [ClientController::class, 'searchByPhone']);
        Route::get('/clients/search/email/{email}', [ClientController::class, 'searchByEmail']);
        Route::get('/clients/search/name/{name}', [ClientController::class, 'searchByName']);
        Route::get('/clients/statistics/summary', [ClientController::class, 'summary']);

        // Fournisseurs
        Route::apiResource('suppliers', SupplierController::class);
        Route::get('/suppliers/search/id/{id}', [SupplierController::class, 'searchById']);
        Route::get('/suppliers/search/phone/{phone}', [SupplierController::class, 'searchByPhone']);
        Route::get('/suppliers/search/email/{email}', [SupplierController::class, 'searchByEmail']);
        Route::get('/suppliers/search/name/{name}', [SupplierController::class, 'searchByName']);
        Route::get('/suppliers/statistics/summary', [SupplierController::class, 'summary']);

        // Transactions Mobile Money
        Route::apiResource('mobile-transactions', MobileTransactionController::class);
        Route::post('/mobile-transactions/{transaction}/status', [MobileTransactionController::class, 'updateStatus']); 
        Route::get('/mobile-transactions/statistics/summary', [MobileTransactionController::class, 'statistics']);

        // Factures - ROUTES CORRIGÉES
        Route::apiResource('invoices', InvoiceController::class);
        Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download']); 
        Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'print']); 
        Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'sendEmail']); 
        Route::get('/invoices/statistics/summary', [InvoiceController::class, 'statistics']);

        // Rapports
        Route::prefix('reports')->group(function () {
            Route::get('/sales', [ReportController::class, 'sales']);
            Route::get('/inventory', [ReportController::class, 'inventory']);
            Route::get('/transactions', [ReportController::class, 'transactions']);
            Route::get('/clients', [ReportController::class, 'clients']);
            Route::post('/export', [ReportController::class, 'export']);
        });

        // Utilisateurs (admin seulement)
        Route::middleware(['admin'])->group(function () {
            Route::apiResource('users', UserController::class);
            Route::post('/users/{user}/toggle', [UserController::class, 'toggleStatus']); 
            Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);
            Route::get('/{id}', [UserController::class, 'show']);
            Route::put('/{id}', [UserController::class, 'update']);
            Route::delete('/{id}', [UserController::class, 'destroy']);
            Route::patch('/{id}/toggle-status', [UserController::class, 'toggleStatus']);
            Route::post('/{id}/reset-password', [UserController::class, 'resetPassword']);
        });

        // Paramètres (admin seulement)
        Route::middleware(['admin'])->prefix('settings')->group(function () {
            Route::get('/', [SettingController::class, 'index']);
            Route::put('/', [SettingController::class, 'update']);
            Route::post('/backup', [SettingController::class, 'backup']);
            Route::post('/restore', [SettingController::class, 'restore']);
            Route::get('/group/{group}', [SettingController::class, 'getByGroup']);
            Route::put('/{key}', [SettingController::class, 'updateSingle']);
            Route::post('/backup', [SettingController::class, 'backup']);
            Route::post('/restore', [SettingController::class, 'restore']);
            Route::get('/backups', [SettingController::class, 'listBackups']);
        });

        // Profile routes
        Route::prefix('profile')->group(function () {
            Route::get('/', [ProfileController::class, 'show']);
            Route::put('/', [ProfileController::class, 'update']);
            Route::post('/change-password', [ProfileController::class, 'changePassword']);
            Route::post('/avatar', [ProfileController::class, 'updateAvatar']);
        });
    });
});