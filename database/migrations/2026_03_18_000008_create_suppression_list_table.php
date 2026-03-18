<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppression_list', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prospect_id')->constrained()->cascadeOnDelete();
            $table->string('channel')->default('email');
            $table->string('reason'); // bounce, unsubscribe, complaint, invalid_email, manual
            $table->string('source')->nullable();
            $table->timestamp('lifted_at')->nullable();
            $table->timestamps();

            $table->unique(['prospect_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppression_list');
    }
};
