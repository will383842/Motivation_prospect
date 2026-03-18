<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source'); // firebase, mailwizz
            $table->string('external_event_id')->nullable();
            $table->string('event_type');
            $table->json('payload');
            $table->string('status')->default('pending'); // pending, processing, processed, failed, skipped
            $table->integer('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('external_event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
