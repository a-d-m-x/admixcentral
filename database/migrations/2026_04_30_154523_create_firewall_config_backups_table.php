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
        Schema::create('firewall_config_backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firewall_id')->constrained()->cascadeOnDelete();
            $table->string('path')->nullable();
            $table->string('sha256_hash')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('pulled_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->string('status')->default('missing'); // success, failed, missing, stale
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('firewall_config_backups');
    }
};
