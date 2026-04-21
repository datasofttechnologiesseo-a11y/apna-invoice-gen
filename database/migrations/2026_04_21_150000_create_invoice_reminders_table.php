<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->string('channel', 20);              // email, whatsapp, sms
            $table->string('recipient', 255);           // email address / phone / etc
            $table->string('status', 20)->default('sent'); // sent, failed, queued
            $table->integer('days_past_due');           // snapshot at send time
            $table->string('trigger', 20)->default('auto'); // auto (scheduler) or manual
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->useCurrent();

            $table->timestamps();

            $table->index(['invoice_id', 'channel', 'days_past_due']);
            $table->index(['company_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_reminders');
    }
};
