<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prospect_id')->constrained()->cascadeOnDelete();
            $table->string('consent_type'); // email_marketing, data_processing
            $table->boolean('granted')->default(true);
            $table->string('version')->default('1.0');
            $table->string('ip_address')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('prospect_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
    }
};
