<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();

            // Identification
            $table->string('name');
            $table->string('contact_person');
            $table->string('telephone', 30);
            $table->string('email')->nullable();

            // Adresse
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();

            // Informations métier
            $table->string('type_produits')->nullable();
            $table->unsignedInteger('delai_livraison')->nullable()
                  ->comment('Délai en jours');
            $table->string('conditions_paiement')->nullable();
            $table->unsignedTinyInteger('evaluation')->default(3);
            $table->boolean('actif')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
