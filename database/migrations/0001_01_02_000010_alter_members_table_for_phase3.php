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
        Schema::table('members', function (Blueprint $table) {
            $table->string('card_uid', 64)->nullable()->change();
            $table->string('pin_hash', 255)->nullable()->after('status');
            $table->string('remember_token', 100)->nullable()->after('pin_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['pin_hash', 'remember_token']);
            $table->string('card_uid', 64)->nullable(false)->change();
        });
    }
};
