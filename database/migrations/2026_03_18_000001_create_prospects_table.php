<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('country')->nullable();
            $table->string('language', 5)->default('fr');
            $table->string('timezone')->default('UTC');
            $table->string('source')->nullable(); // job_ads, backlink, form, manual
            $table->string('source_detail')->nullable(); // which job site, which ad
            $table->string('job_title')->nullable(); // the job they applied for
            $table->string('company')->nullable();
            $table->json('tags')->nullable();
            $table->json('extra')->nullable();

            // Lifecycle
            $table->string('lifecycle_state')->default('lead'); // lead, nurtured, hot, converted, churned, unsubscribed
            $table->timestamp('lifecycle_changed_at')->nullable();

            // Email engagement
            $table->integer('emails_sent')->default(0);
            $table->integer('emails_opened')->default(0);
            $table->integer('emails_clicked')->default(0);
            $table->integer('emails_bounced')->default(0);
            $table->float('engagement_score')->default(0);
            $table->float('fatigue_score')->default(0);
            $table->float('fatigue_multiplier')->default(1.0);

            // Conversion
            $table->string('join_code')->unique()->nullable(); // unique tracking code
            $table->string('firebase_uid')->nullable(); // set when converted
            $table->timestamp('converted_at')->nullable();
            $table->string('converted_via')->nullable(); // which email/sequence converted them

            // Import tracking
            $table->uuid('import_batch_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_sos_expat_user')->default(false); // already registered on SOS-Expat
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('lifecycle_state');
            $table->index('source');
            $table->index('import_batch_id');
            $table->index('join_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};
