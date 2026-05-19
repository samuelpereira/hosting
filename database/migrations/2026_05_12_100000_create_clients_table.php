<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('whmcs_id')->nullable()->unique();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->text('address_line')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('city', 120)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('vat_number', 32)->nullable();
            $table->boolean('vat_exception')->default(false);
            $table->boolean('irs_exception')->default(false);
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamp('registered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
