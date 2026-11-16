<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('renewal_applied_at')->nullable()->after('paid_at');
        });

        // Orders already paid have had their linked services/domains extended.
        DB::table('orders')
            ->where('status', 'paid')
            ->update(['renewal_applied_at' => DB::raw('COALESCE(paid_at, updated_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('renewal_applied_at');
        });
    }
};
