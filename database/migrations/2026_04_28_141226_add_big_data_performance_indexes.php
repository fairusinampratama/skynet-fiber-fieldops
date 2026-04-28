<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('odp_ports', function (Blueprint $table): void {
            $table->index(['status', 'odp_asset_id'], 'odp_ports_status_odp_asset_id_index');
        });

        Schema::table('odp_assets', function (Blueprint $table): void {
            $table->index('odc_asset_id', 'odp_assets_odc_asset_id_index');
        });

        Schema::table('odc_assets', function (Blueprint $table): void {
            $table->index('olt_pon_port_id', 'odc_assets_olt_pon_port_id_index');
        });

        Schema::table('submissions', function (Blueprint $table): void {
            $table->index(['status', 'project_id'], 'submissions_status_project_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->dropIndex('submissions_status_project_id_index');
        });

        Schema::table('odc_assets', function (Blueprint $table): void {
            $table->dropIndex('odc_assets_olt_pon_port_id_index');
        });

        Schema::table('odp_assets', function (Blueprint $table): void {
            $table->dropIndex('odp_assets_odc_asset_id_index');
        });

        Schema::table('odp_ports', function (Blueprint $table): void {
            $table->dropIndex('odp_ports_status_odp_asset_id_index');
        });
    }
};
