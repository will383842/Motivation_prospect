<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_sequences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('trigger_event')->nullable(); // prospect.imported, prospect.opened, etc.
            $table->string('status')->default('draft'); // draft, active, paused, archived
            $table->integer('priority')->default(0);
            $table->boolean('is_repeatable')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sequence_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sequence_id')->constrained('email_sequences')->cascadeOnDelete();
            $table->integer('step_order');
            $table->string('type'); // email, delay, condition, ab_split, action
            $table->string('template_slug')->nullable();
            $table->json('config')->nullable(); // delay_hours, conditions, variants, etc.
            $table->timestamps();

            $table->unique(['sequence_id', 'step_order']);
        });

        Schema::create('prospect_sequences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prospect_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('sequence_id')->constrained('email_sequences')->cascadeOnDelete();
            $table->foreignUuid('current_step_id')->nullable()->constrained('sequence_steps')->nullOnDelete();
            $table->integer('current_step_order')->default(1);
            $table->string('status')->default('active'); // active, completed, exited, paused
            $table->string('exit_reason')->nullable();
            $table->integer('sequence_version')->default(1);
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('next_step_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_step_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_sequences');
        Schema::dropIfExists('sequence_steps');
        Schema::dropIfExists('email_sequences');
    }
};
