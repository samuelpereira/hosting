<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('whmcs_id')->nullable()->unique();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->char('currency', 3);
            $table->unsignedBigInteger('first_payment_amount_minor')->nullable();
            $table->unsignedBigInteger('recurring_amount_minor')->nullable();
            $table->string('interval_unit', 16)->default('month');
            $table->unsignedTinyInteger('interval_count')->default(1);
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('domain_id')->nullable()->constrained()->nullOnDelete();
            $table->string('associated_domain', 255)->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedInteger('allocated_ram_mb')->nullable();
            $table->unsignedInteger('allocated_disk_gb')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['server_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
