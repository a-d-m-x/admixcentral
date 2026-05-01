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
        Schema::table('firewalls', function (Blueprint $table) {
            $table->integer('ssh_port')->default(22);
            $table->string('ssh_username')->nullable();
            $table->text('ssh_password')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('firewalls', function (Blueprint $table) {
            $table->dropColumn(['ssh_port', 'ssh_username', 'ssh_password']);
        });
    }
};
