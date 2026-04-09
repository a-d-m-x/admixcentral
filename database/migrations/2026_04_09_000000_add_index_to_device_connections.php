<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a composite index on device_connections(firewall_id, disconnected_at).
     *
     * The DashboardController runs a WHERE NOT EXISTS (SELECT 1 FROM device_connections
     * WHERE firewall_id = firewalls.id AND disconnected_at IS NULL) subquery on every
     * dashboard page load. Without an index this becomes a full table scan per firewall,
     * which is O(connections × firewalls) and grows badly past 50+ firewalls.
     *
     * The composite index (firewall_id, disconnected_at) allows MySQL to satisfy the
     * subquery with a single index range scan, making the whereDoesntHave() query
     * an O(log n) operation on the index instead.
     */
    public function up(): void
    {
        Schema::table('device_connections', function (Blueprint $table) {
            // Only add if it doesn't already exist (safe to re-run)
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = array_keys($sm->listTableIndexes('device_connections'));

            if (!in_array('device_connections_firewall_id_disconnected_at_index', $indexes)) {
                $table->index(['firewall_id', 'disconnected_at'], 'device_connections_firewall_id_disconnected_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('device_connections', function (Blueprint $table) {
            $table->dropIndexIfExists('device_connections_firewall_id_disconnected_at_index');
        });
    }
};
