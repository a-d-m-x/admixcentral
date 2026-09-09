<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('firewalls', function (Blueprint $table) {
            $table->string('tls_public_key_pin', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('firewalls', function (Blueprint $table) {
            $table->dropColumn('tls_public_key_pin');
        });
    }
};
