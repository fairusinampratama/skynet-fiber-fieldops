<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->decimal('planned_latitude', 11, 8)->nullable()->after('area_id');
            $table->decimal('planned_longitude', 11, 8)->nullable()->after('planned_latitude');
        });

        DB::statement('update submissions set planned_latitude = latitude, planned_longitude = longitude where planned_latitude is null and planned_longitude is null');
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->dropColumn(['planned_latitude', 'planned_longitude']);
        });
    }
};
