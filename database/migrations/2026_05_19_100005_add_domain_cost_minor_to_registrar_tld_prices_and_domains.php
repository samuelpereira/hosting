<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrar_tld_prices', function (Blueprint $table) {
            $table->unsignedBigInteger('domain_cost_minor')->nullable()->after('amount_minor');
        });

        Schema::table('domains', function (Blueprint $table) {
            $table->unsignedBigInteger('domain_cost_minor')->nullable()->after('recurring_amount_minor');
        });
    }

    public function down(): void
    {
        Schema::table('registrar_tld_prices', function (Blueprint $table) {
            $table->dropColumn('domain_cost_minor');
        });

        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('domain_cost_minor');
        });
    }
};
