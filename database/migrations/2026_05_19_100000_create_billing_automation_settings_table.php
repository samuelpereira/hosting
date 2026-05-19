<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_automation_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(true);
            $table->time('run_at_time')->default('09:00:00');
            $table->unsignedSmallInteger('service_order_days_before')->default(14);
            $table->unsignedSmallInteger('domain_order_days_before')->default(30);
            $table->unsignedSmallInteger('reminder_days_before_first')->default(7);
            $table->unsignedSmallInteger('reminder_days_before_due')->default(0);
            $table->unsignedSmallInteger('reminder_days_after_due')->default(14);
            $table->boolean('send_order_created_email')->default(true);
            $table->boolean('send_reminder_emails')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_automation_settings');
    }
};
