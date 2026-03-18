<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('category')->nullable(); // onboarding, nurture, conversion, reactivation
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('email_template_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('email_templates')->cascadeOnDelete();
            $table->string('language', 5)->default('fr');
            $table->string('subject');
            $table->text('body_html');
            $table->text('body_text')->nullable();
            $table->string('preview_text')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['template_id', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_template_variants');
        Schema::dropIfExists('email_templates');
    }
};
