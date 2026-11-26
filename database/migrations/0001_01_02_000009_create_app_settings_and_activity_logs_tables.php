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
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->enum('type', ['string', 'integer', 'decimal', 'boolean', 'json'])->default('string');
            $table->string('group', 50)->default('general')->index();
            $table->foreignId('updated_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')
                ->nullable()
                ->index()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('actor_role', 50)->nullable()->index();
            $table->string('action', 100)->index();
            $table->string('description', 255);
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->char('province_id', 2)->nullable()->index();
            $table->char('regency_id', 4)->nullable()->index();
            $table->foreignId('branch_id')
                ->nullable()
                ->index()
                ->constrained('branches')
                ->restrictOnDelete();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->foreign('province_id')
                ->references('id')
                ->on('provinces')
                ->restrictOnDelete();
            $table->foreign('regency_id')
                ->references('id')
                ->on('regencies')
                ->restrictOnDelete();

            $table->index(['subject_type', 'subject_id'], 'activity_logs_subject_idx');
            $table->index(['branch_id', 'action', 'created_at'], 'activity_logs_branch_action_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('app_settings');
    }
};
