<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prospect_id')->constrained()->cascadeOnDelete();
            $table->string('template_slug')->nullable();
            $table->string('subject')->nullable();
            $table->string('status')->default('queued'); // queued, sent, delivered, opened, clicked, bounced, failed, unsubscribed
            $table->string('mailwizz_message_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamps();

            $table->index(['prospect_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
