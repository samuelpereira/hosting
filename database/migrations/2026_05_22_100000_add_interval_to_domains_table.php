<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->string('interval_unit', 16)->default('year')->after('recurring_amount_minor');
            $table->unsignedTinyInteger('interval_count')->default(1)->after('interval_unit');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn(['interval_unit', 'interval_count']);
        });
    }
};
