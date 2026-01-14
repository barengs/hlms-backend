<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            
            // Payment details
            $table->string('payment_gateway'); // 'midtrans', 'stripe', 'paypal', etc
            $table->string('payment_method'); // 'credit_card', 'bank_transfer', 'ewallet', etc
            $table->string('transaction_id')->unique();
            $table->string('payment_status'); // 'pending', 'settlement', 'capture', 'cancel', 'expire', 'failure'
            
            // Amounts
            $table->decimal('amount', 12, 2);
            $table->decimal('fee_amount', 12, 2)->default(0);
            
            // Metadata
            $table->json('gateway_response')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            $table->timestamp('completed_at')->nullable();

            $table->index(['order_id', 'payment_status']);
            $table->index('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
