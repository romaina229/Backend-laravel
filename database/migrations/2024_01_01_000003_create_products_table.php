<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('stock_quantity', 10, 2);
            $table->string('unit')->default('kg');
            $table->decimal('alert_threshold', 10, 2)->default(5);
            $table->enum('status', ['available', 'out_of_stock', 'discontinued'])->default('available');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['name', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};