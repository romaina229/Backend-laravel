<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateSettingsTable extends Migration
{
    public function up()
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->text('description')->nullable();
            $table->string('group')->default('general');
            $table->timestamps();
        });

        // Insérer les paramètres par défaut
        DB::table('settings')->insert([
            [
                'key' => 'app_name',
                'value' => 'AquaGestion',
                'description' => 'Nom de l\'application',
                'group' => 'general'
            ],
            [
                'key' => 'currency',
                'value' => 'FCFA',
                'description' => 'Devise par défaut',
                'group' => 'general'
            ],
            [
                'key' => 'date_format',
                'value' => 'd/m/Y H:i',
                'description' => 'Format de date',
                'group' => 'general'
            ],
            [
                'key' => 'items_per_page',
                'value' => '20',
                'description' => 'Nombre d\'éléments par page',
                'group' => 'general'
            ],
            [
                'key' => 'stock_alert_threshold',
                'value' => '5',
                'description' => 'Seuil d\'alerte de stock',
                'group' => 'stock'
            ],
            [
                'key' => 'default_tax',
                'value' => '5',
                'description' => 'AIB par défaut (%)',
                'group' => 'sales'
            ],
            [
                'key' => 'invoice_prefix',
                'value' => 'INV-',
                'description' => 'Préfixe des factures',
                'group' => 'invoices'
            ]
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('settings');
    }
}
