<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrar_tlds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registrar_id')->constrained()->cascadeOnDelete();
            $table->string('tld', 32);
            $table->timestamps();

            $table->unique(['registrar_id', 'tld']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrar_tlds');
    }
};
