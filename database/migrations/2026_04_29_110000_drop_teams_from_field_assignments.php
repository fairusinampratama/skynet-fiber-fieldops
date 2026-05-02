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
            if (Schema::hasColumn('submissions', 'team_id')) {
                $table->dropForeign(['team_id']);
                $table->dropColumn('team_id');
            }
        });

        Schema::dropIfExists('teams');
    }

    public function down(): void
    {
        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'name']);
        });

        Schema::table('submissions', function (Blueprint $table): void {
            $table->foreignId('team_id')->nullable()->after('technician_id')->constrained()->nullOnDelete();
        });

        $now = now();

        DB::table('projects')->orderBy('id')->select('id', 'name')->chunkById(200, function ($projects) use ($now): void {
            foreach ($projects as $project) {
                $teamId = DB::table('teams')->insertGetId([
                    'project_id' => $project->id,
                    'leader_id' => null,
                    'name' => 'Restored Team',
                    'notes' => 'Restored by teams rollback migration.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('submissions')->where('project_id', $project->id)->update(['team_id' => $teamId]);
            }
        });
    }
};
