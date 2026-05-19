<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('whmcs_id')->nullable()->unique();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('registrar_id')->constrained()->restrictOnDelete();
            $table->foreignId('registrar_tld_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('registrar_tld_price_id')->nullable()->constrained('registrar_tld_prices')->nullOnDelete();
            $table->string('fqdn');
            $table->string('status', 32)->default('active');
            $table->char('currency', 3);
            $table->unsignedBigInteger('first_payment_amount_minor')->nullable();
            $table->unsignedBigInteger('recurring_amount_minor')->nullable();
            $table->date('registered_at');
            $table->date('expires_at');
            $table->date('due_at');
            $table->boolean('auto_renew')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'expires_at']);
            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
