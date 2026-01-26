<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->enum('operator', ['MTN', 'MOOV', 'CELTIS', 'ORANGE']);
            $table->decimal('amount', 15, 2);
            $table->string('client_name');
            $table->string('client_phone');
            $table->string('external_reference')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->enum('type', ['deposit', 'withdrawal', 'payment', 'transfer'])->default('payment');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['reference', 'operator', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_transactions');
    }
};