<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('olt_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('location')->nullable();
            $table->decimal('latitude', 11, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('status')->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'code']);
        });

        Schema::create('olt_pon_ports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('olt_asset_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('pon_number');
            $table->string('label')->nullable();
            $table->unsignedSmallInteger('capacity')->default(128);
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->unique(['olt_asset_id', 'pon_number']);
        });

        Schema::table('odc_assets', function (Blueprint $table): void {
            $table->foreignId('olt_pon_port_id')->nullable()->after('area_id')->constrained('olt_pon_ports')->nullOnDelete();
        });

        $now = now();

        DB::table('projects')->orderBy('id')->each(function (object $project) use ($now): void {
            $areaId = DB::table('areas')->where('project_id', $project->id)->orderBy('id')->value('id');
            $code = 'OLT-MIGRATED-' . Str::slug((string) $project->code, '-');

            $oltId = DB::table('olt_assets')->insertGetId([
                'project_id' => $project->id,
                'area_id' => $areaId,
                'name' => 'Migrated OLT ' . $project->code,
                'code' => $code,
                'location' => 'Migrated from existing ODC data',
                'status' => 'active',
                'notes' => 'Created automatically during OLT/PON hierarchy migration.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $firstPonId = null;

            foreach (range(1, 8) as $ponNumber) {
                $ponId = DB::table('olt_pon_ports')->insertGetId([
                    'olt_asset_id' => $oltId,
                    'pon_number' => $ponNumber,
                    'label' => 'PON ' . $ponNumber,
                    'capacity' => 128,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $firstPonId ??= $ponId;
            }

            DB::table('odc_assets')
                ->where('project_id', $project->id)
                ->whereNull('olt_pon_port_id')
                ->update(['olt_pon_port_id' => $firstPonId, 'updated_at' => $now]);
        });
    }

    public function down(): void
    {
        Schema::table('odc_assets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('olt_pon_port_id');
        });

        Schema::dropIfExists('olt_pon_ports');
        Schema::dropIfExists('olt_assets');
    }
};
