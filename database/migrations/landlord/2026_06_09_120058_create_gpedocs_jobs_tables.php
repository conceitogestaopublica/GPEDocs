<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::connection('landlord')->hasTable('gpedocs_jobs')) {
            Schema::connection('landlord')->create('gpedocs_jobs', function (Blueprint $t) {
                $t->id();
                $t->string('queue')->index();
                $t->unsignedInteger('tenant_id')->nullable();
                $t->longText('payload');
                $t->unsignedTinyInteger('attempts');
                $t->unsignedInteger('reserved_at')->nullable();
                $t->unsignedInteger('available_at');
                $t->unsignedInteger('created_at');
                $t->index(['tenant_id', 'queue', 'reserved_at'], 'gpedocs_jobs_tenant_queue_reserved_idx');
            });
        }
        if (! Schema::connection('landlord')->hasTable('gpedocs_job_batches')) {
            Schema::connection('landlord')->create('gpedocs_job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }

        if (! Schema::connection('landlord')->hasTable('gpedocs_failed_jobs')) {
            Schema::connection('landlord')->create('gpedocs_failed_jobs', function (Blueprint $t) {
                $t->id();
                $t->string('uuid')->unique();
                $t->unsignedInteger('tenant_id')->nullable()->index('gpedocs_failed_jobs_tenant_id_index');
                $t->text('connection');
                $t->text('queue');
                $t->longText('payload');
                $t->longText('exception');
                $t->timestamp('failed_at')->useCurrent();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('gpedocs_jobs');
        Schema::connection('landlord')->dropIfExists('gpedocs_job_batches');
        Schema::connection('landlord')->dropIfExists('gpedocs_failed_jobs');
    }
};
