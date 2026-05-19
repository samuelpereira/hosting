<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_tax_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('vat_rate_percent', 5, 2)->default(23);
            $table->decimal('irs_rate_percent', 5, 2)->default(25);
            $table->boolean('domains_irs_exception')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_tax_settings');
    }
};
