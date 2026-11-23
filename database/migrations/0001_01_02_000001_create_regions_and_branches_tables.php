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
        Schema::create('provinces', function (Blueprint $table) {
            $table->char('id', 2)->primary();
            $table->string('name', 100)->index();
        });

        Schema::create('regencies', function (Blueprint $table) {
            $table->char('id', 4)->primary();
            $table->char('province_id', 2)->index();
            $table->string('name', 100)->index();

            $table->foreign('province_id')
                ->references('id')
                ->on('provinces')
                ->restrictOnDelete();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->char('id', 7)->primary();
            $table->char('regency_id', 4)->index();
            $table->string('name', 100)->index();

            $table->foreign('regency_id')
                ->references('id')
                ->on('regencies')
                ->restrictOnDelete();
        });

        Schema::create('villages', function (Blueprint $table) {
            $table->char('id', 10)->primary();
            $table->char('district_id', 7)->index();
            $table->string('name', 100)->index();

            $table->foreign('district_id')
                ->references('id')
                ->on('districts')
                ->restrictOnDelete();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->char('village_id', 10)->unique();
            $table->char('district_id', 7)->index();
            $table->char('regency_id', 4)->index();
            $table->char('province_id', 2)->index();
            $table->text('address');
            $table->string('phone', 25)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->foreign('village_id')
                ->references('id')
                ->on('villages')
                ->restrictOnDelete();

            $table->foreign('district_id')
                ->references('id')
                ->on('districts')
                ->restrictOnDelete();

            $table->foreign('regency_id')
                ->references('id')
                ->on('regencies')
                ->restrictOnDelete();

            $table->foreign('province_id')
                ->references('id')
                ->on('provinces')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
        Schema::dropIfExists('villages');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('regencies');
        Schema::dropIfExists('provinces');
    }
};
