<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make ssh_port nullable so OPNsense firewalls (which don't use SSH backup)
     * can have it cleared to null rather than failing with an integrity violation
     * when company reassignment or OS-type changes trigger a null write.
     *
     * The default(22) is preserved for new pfSense firewalls.
     */
    public function up(): void
    {
        Schema::table('firewalls', function (Blueprint $table) {
            $table->integer('ssh_port')->nullable()->default(22)->change();
        });
    }

    public function down(): void
    {
        Schema::table('firewalls', function (Blueprint $table) {
            // Restore existing nulls to 22 before removing nullable
            \Illuminate\Support\Facades\DB::table('firewalls')
                ->whereNull('ssh_port')
                ->update(['ssh_port' => 22]);

            $table->integer('ssh_port')->nullable(false)->default(22)->change();
        });
    }
};
