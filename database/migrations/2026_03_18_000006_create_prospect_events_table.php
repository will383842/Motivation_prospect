<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prospect_id')->constrained()->cascadeOnDelete();
            $table->string('event_type'); // imported, email_sent, email_opened, email_clicked, link_visited, converted, unsubscribed
            $table->json('event_data')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['prospect_id', 'event_type']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_events');
    }
};
