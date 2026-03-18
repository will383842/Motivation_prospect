<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('filename');
            $table->string('source')->nullable(); // job_ads, manual, etc.
            $table->string('source_detail')->nullable(); // which site/ad
            $table->integer('total_rows')->default(0);
            $table->integer('imported')->default(0);
            $table->integer('duplicates_skipped')->default(0);
            $table->integer('sos_users_skipped')->default(0);
            $table->integer('invalid_skipped')->default(0);
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->json('errors')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
