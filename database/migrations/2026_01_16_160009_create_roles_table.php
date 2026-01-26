<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('label')->unique();
            $table->text('description')->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Insérer les rôles par défaut
        $this->insertDefaultRoles();
    }

    public function down()
    {
        Schema::dropIfExists('roles');
    }

    private function insertDefaultRoles()
    {
        $roles = [
            [
                'label' => 'Administrateur',
                'description' => 'Accès complet à toutes les fonctionnalités',
                'permissions' => json_encode(['all']),
                'active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'label' => 'Gestionnaire',
                'description' => 'Gestion des produits, stocks, clients et fournisseurs',
                'permissions' => json_encode([
                    'manage_products', 'manage_stock', 'manage_clients',
                    'manage_suppliers', 'view_reports', 'view_sales',
                    'view_dashboard', 'export_data'
                ]),
                'active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'label' => 'Caissier',
                'description' => 'Enregistrement des ventes et génération de factures',
                'permissions' => json_encode([
                    'create_sales', 'create_transactions', 'view_products',
                    'view_clients', 'generate_invoices', 'view_dashboard'
                ]),
                'active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        DB::table('roles')->insert($roles);
    }
};
