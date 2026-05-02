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
            $table->dropForeign(['team_id']);
            $table->foreignId('team_id')->nullable()->change();
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $fallbackTeamId = DB::table('teams')->value('id');

        if ($fallbackTeamId !== null) {
            DB::table('submissions')->whereNull('team_id')->update(['team_id' => $fallbackTeamId]);
        }

        Schema::table('submissions', function (Blueprint $table): void {
            $table->dropForeign(['team_id']);
            $table->foreignId('team_id')->nullable(false)->change();
            $table->foreign('team_id')->references('id')->on('teams')->restrictOnDelete();
        });
    }
};
