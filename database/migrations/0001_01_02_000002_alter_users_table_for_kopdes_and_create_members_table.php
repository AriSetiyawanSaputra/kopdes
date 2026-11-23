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
        Schema::table('users', function (Blueprint $table) {
            $table->string('name', 150)->change();
            $table->string('email', 150)->nullable()->change();
            $table->string('password')->nullable()->change();

            $table->enum('role', [
                'admin_nasional',
                'admin_provinsi',
                'admin_kabupaten',
                'admin_cabang',
                'kasir',
            ])->default('kasir')->after('password')->index();

            $table->char('province_id', 2)->nullable()->after('role')->index();
            $table->char('regency_id', 4)->nullable()->after('province_id')->index();
            $table->foreignId('branch_id')
                ->nullable()
                ->after('regency_id')
                ->constrained('branches')
                ->restrictOnDelete();
            $table->index('branch_id');

            $table->string('card_uid', 64)->nullable()->unique()->after('branch_id');
            $table->string('pin_hash', 255)->nullable()->after('card_uid');
            $table->boolean('is_active')->default(true)->after('pin_hash')->index();

            $table->foreign('province_id')
                ->references('id')
                ->on('provinces')
                ->restrictOnDelete();

            $table->foreign('regency_id')
                ->references('id')
                ->on('regencies')
                ->restrictOnDelete();
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('member_code', 40)->unique();
            $table->foreignId('home_branch_id')
                ->constrained('branches')
                ->restrictOnDelete();
            $table->index('home_branch_id');
            $table->string('card_uid', 64)->unique();
            $table->string('name', 150);
            $table->string('phone', 25)->nullable()->index();
            $table->string('nik', 20)->nullable()->unique();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->date('joined_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['province_id']);
            $table->dropForeign(['regency_id']);
            $table->dropForeign(['branch_id']);

            $table->dropIndex(['role']);
            $table->dropIndex(['province_id']);
            $table->dropIndex(['regency_id']);
            $table->dropIndex(['branch_id']);
            $table->dropUnique(['card_uid']);
            $table->dropIndex(['is_active']);

            $table->dropColumn([
                'role',
                'province_id',
                'regency_id',
                'branch_id',
                'card_uid',
                'pin_hash',
                'is_active',
            ]);
        });
    }
};
