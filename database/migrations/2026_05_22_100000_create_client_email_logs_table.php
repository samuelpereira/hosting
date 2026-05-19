<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email_template_slug')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->longText('message');
            $table->string('to_address');
            $table->text('cc')->nullable();
            $table->text('bcc')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['client_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_email_logs');
    }
};
