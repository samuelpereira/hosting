<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrar_tld_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registrar_tld_id')->constrained()->cascadeOnDelete();
            $table->string('operation', 32);
            $table->unsignedTinyInteger('term_years')->default(1);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['registrar_tld_id', 'operation', 'term_years']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrar_tld_prices');
    }
};
