<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_automation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->string('event', 64);
            $table->date('context_date');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id', 'event', 'context_date'], 'billing_automation_logs_unique');
            $table->index(['event', 'context_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_automation_logs');
    }
};
