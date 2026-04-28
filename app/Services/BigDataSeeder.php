<?php

namespace App\Services;

use App\Enums\OdpCoreColor;
use App\Enums\PortStatus;
use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class BigDataSeeder
{
    public const PREFIX = 'BIG';

    /** @var array<string, array<string, int>> */
    public const PROFILES = [
        'tiny' => [
            'projects' => 2,
            'areas_per_project' => 2,
            'olts_per_project' => 2,
            'pons_per_olt' => 4,
            'odcs_per_project' => 8,
            'odps_per_project' => 24,
            'ports_per_asset' => 8,
            'submissions_per_project' => 10,
            'technicians' => 8,
        ],
        'medium' => [
            'projects' => 10,
            'areas_per_project' => 10,
            'olts_per_project' => 10,
            'pons_per_olt' => 8,
            'odcs_per_project' => 200,
            'odps_per_project' => 1000,
            'ports_per_asset' => 8,
            'submissions_per_project' => 200,
            'technicians' => 100,
        ],
    ];

    /** @return array<string, int> */
    public static function profile(string $profile): array
    {
        return self::PROFILES[$profile] ?? throw new InvalidArgumentException("Unknown big data profile [{$profile}].");
    }

    /** @return array<string, int> */
    public function run(string $profile = 'medium', bool $reset = false, bool $withSubmissions = false, int $chunk = 1000, ?callable $progress = null): array
    {
        $config = self::profile($profile);
        $chunk = max(1, $chunk);

        if ($reset) {
            $this->progress($progress, 'Resetting existing BIG data');
            $this->reset();
        }

        DB::disableQueryLog();

        $now = now();

        $this->progress($progress, 'Seeding users');
        $this->seedUsers($config, $now, $chunk);

        $this->progress($progress, 'Seeding projects, areas, teams, OLTs, and PONs');
        $this->seedProjects($config, $now, $chunk);
        $this->seedAreas($config, $now, $chunk);
        $this->seedTeams($config, $now, $chunk);
        $this->seedOlts($config, $now, $chunk);
        $this->seedPonPorts($config, $now, $chunk);

        $this->progress($progress, 'Seeding ODC and ODP hierarchy');
        $this->seedOdcs($config, $now, $chunk);
        $this->seedOdps($config, $now, $chunk);

        $this->progress($progress, 'Seeding ODC ports');
        $this->seedOdcPorts($config, $now, $chunk);

        $this->progress($progress, 'Seeding ODP ports');
        $this->seedOdpPorts($config, $now, $chunk);

        if ($withSubmissions) {
            $this->progress($progress, 'Seeding submissions');
            $this->seedSubmissions($config, $now, $chunk);
        }

        return $this->counts();
    }

    private function progress(?callable $progress, string $message): void
    {
        if ($progress !== null) {
            $progress($message);
        }
    }

    public function reset(): void
    {
        DB::transaction(function (): void {
            DB::table('projects')->where('code', 'like', self::PREFIX . '-P%')->delete();
            DB::table('users')->where('email', 'like', 'big-tech-%@skynet.local')->delete();
        });
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        $projectIds = DB::table('projects')->where('code', 'like', self::PREFIX . '-P%')->pluck('id');
        $oltIds = DB::table('olt_assets')->whereIn('project_id', $projectIds)->pluck('id');
        $ponIds = DB::table('olt_pon_ports')->whereIn('olt_asset_id', $oltIds)->pluck('id');
        $odcIds = DB::table('odc_assets')->whereIn('project_id', $projectIds)->pluck('id');
        $odpIds = DB::table('odp_assets')->whereIn('project_id', $projectIds)->pluck('id');
        $submissionIds = DB::table('submissions')->whereIn('project_id', $projectIds)->pluck('id');

        return [
            'projects' => $projectIds->count(),
            'areas' => DB::table('areas')->whereIn('project_id', $projectIds)->count(),
            'teams' => DB::table('teams')->whereIn('project_id', $projectIds)->count(),
            'technicians' => DB::table('users')->where('email', 'like', 'big-tech-%@skynet.local')->count(),
            'olts' => $oltIds->count(),
            'pons' => $ponIds->count(),
            'odcs' => $odcIds->count(),
            'odps' => $odpIds->count(),
            'odc_ports' => DB::table('odc_ports')->whereIn('odc_asset_id', $odcIds)->count(),
            'odp_ports' => DB::table('odp_ports')->whereIn('odp_asset_id', $odpIds)->count(),
            'submissions' => $submissionIds->count(),
            'submission_ports' => DB::table('submission_ports')->whereIn('submission_id', $submissionIds)->count(),
            'unmapped_odcs' => DB::table('odc_assets')->whereIn('project_id', $projectIds)->whereNull('olt_pon_port_id')->count(),
            'unlinked_odps' => DB::table('odp_assets')->whereIn('project_id', $projectIds)->whereNull('odc_asset_id')->count(),
        ];
    }

    /** @param array<string, int> $config */
    private function seedUsers(array $config, mixed $now, int $chunk): void
    {
        $password = Hash::make('password');
        $rows = [];

        foreach (range(1, $config['technicians']) as $index) {
            $rows[] = [
                'name' => sprintf('Big Data Technician %03d', $index),
                'email' => sprintf('big-tech-%03d@skynet.local', $index),
                'email_verified_at' => $now,
                'password' => $password,
                'role' => UserRole::Technician->value,
                'phone' => sprintf('+62813%08d', $index),
                'is_active' => true,
                'remember_token' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertIgnoreChunked('users', $rows, $chunk);
    }

    /** @param array<string, int> $config */
    private function seedProjects(array $config, mixed $now, int $chunk): void
    {
        $rows = [];

        foreach (range(1, $config['projects']) as $projectIndex) {
            $projectCode = $this->projectCode($projectIndex);
            $rows[] = [
                'name' => "Big Data Project {$projectCode}",
                'code' => $projectCode,
                'description' => 'Synthetic medium-scale project for dashboard and resource testing.',
                'status' => 'active',
                'start_date' => now()->subMonth()->toDateString(),
                'target_date' => now()->addMonths(6)->toDateString(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insertIgnoreChunked('projects', $rows, $chunk);
    }

    /** @param array<string, int> $config */
    private function seedAreas(array $config, mixed $now, int $chunk): void
    {
        $projects = $this->projectIdsByCode();
        $rows = [];

        foreach ($projects as $projectCode => $projectId) {
            $projectIndex = $this->numberFromCode($projectCode);

            foreach (range(1, $config['areas_per_project']) as $areaIndex) {
                $areaCode = $this->areaCode($projectIndex, $areaIndex);
                $rows[] = [
                    'project_id' => $projectId,
                    'name' => "Big Area {$areaCode}",
                    'code' => $areaCode,
                    'description' => 'Synthetic service area.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->insertIgnoreChunked('areas', $rows, $chunk);
    }

    /** @param array<string, int> $config */
    private function seedTeams(array $config, mixed $now, int $chunk): void
    {
        $projects = $this->projectIdsByCode();
        $technicians = $this->technicianIds();
        $rows = [];
        $teamCounter = 1;

        foreach ($projects as $projectCode => $projectId) {
            $projectIndex = $this->numberFromCode($projectCode);

            foreach (range(1, $config['areas_per_project']) as $teamIndex) {
                $rows[] = [
                    'project_id' => $projectId,
                    'leader_id' => $technicians[($teamCounter - 1) % count($technicians)] ?? null,
                    'name' => sprintf('Big Team P%03d-%03d', $projectIndex, $teamIndex),
                    'notes' => 'Synthetic team for big-data testing.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $teamCounter++;
            }
        }

        $this->insertIgnoreChunked('teams', $rows, $chunk);
    }

    /** @param array<string, int> $config */
    private function seedOlts(array $config, mixed $now, int $chunk): void
    {
        $projects = $this->projectIdsByCode();
        $areas = $this->areaIdsByCode();
        $rows = [];

        foreach ($projects as $projectCode => $projectId) {
            $projectIndex = $this->numberFromCode($projectCode);

            foreach (range(1, $config['olts_per_project']) as $oltIndex) {
                $areaIndex = (($oltIndex - 1) % $config['areas_per_project']) + 1;
                $oltCode = $this->oltCode($projectIndex, $oltIndex);

                $rows[] = [
                    'project_id' => $projectId,
                    'area_id' => $areas[$this->areaCode($projectIndex, $areaIndex)] ?? null,
                    'name' => "Big OLT {$oltCode}",
                    'code' => $oltCode,
                    'location' => sprintf('Synthetic POP P%03d-%02d', $projectIndex, $oltIndex),
                    'latitude' => $this->latitude($projectIndex, $oltIndex),
                    'longitude' => $this->longitude($projectIndex, $oltIndex),
                    'status' => 'active',
                    'notes' => 'Generated by fieldops:seed-big-data.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->insertIgnoreChunked('olt_assets', $rows, $chunk);
    }

    /** @param array<string, int> $config */
    private function seedPonPorts(array $config, mixed $now, int $chunk): void
    {
        $olts = $this->oltIdsByCode();
        $rows = [];

        foreach ($olts as $oltCode => $oltId) {
            foreach (range(1, $config['pons_per_olt']) as $ponNumber) {
                $rows[] = [
                    'olt_asset_id' => $oltId,
                    'pon_number' => $ponNumber,
                    'label' => 'PON ' . $ponNumber,
                    'capacity' => $this->ponCapacityFor($ponNumber),
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->insertIgnoreChunked('olt_pon_ports', $rows, $chunk);
    }

    /** @param array<string, int> $config */
    private function seedOdcs(array $config, mixed $now, int $chunk): void
    {
        $projects = $this->projectIdsByCode();
        $areas = $this->areaIdsByCode();
        $pons = $this->ponIdsByOltAndNumber();
        $rows = [];

        foreach ($projects as $projectCode => $projectId) {
            $projectIndex = $this->numberFromCode($projectCode);

            foreach (range(1, $config['odcs_per_project']) as $odcIndex) {
                $areaIndex = (($odcIndex - 1) % $config['areas_per_project']) + 1;
                $oltIndex = (($odcIndex - 1) % $config['olts_per_project']) + 1;
                $ponNumber = (($odcIndex - 1) % $config['pons_per_olt']) + 1;
                $isUnmapped = $odcIndex % 100 === 0 || $odcIndex === $config['odcs_per_project'];

                $rows[] = [
                    'project_id' => $projectId,
                    'area_id' => $areas[$this->areaCode($projectIndex, $areaIndex)],
                    'olt_pon_port_id' => $isUnmapped ? null : ($pons[$this->oltCode($projectIndex, $oltIndex) . ':' . $ponNumber] ?? null),
                    'box_id' => $this->odcCode($projectIndex, $odcIndex),
                    'photo_path' => 'assets/big-data/odc.png',
                    'latitude' => $this->latitude($projectIndex, $odcIndex),
                    'longitude' => $this->longitude($projectIndex, $odcIndex),
                    'source_submission_id' => null,
                    'approved_by' => null,
                    'approved_at' => $now,
                    'status' => $isUnmapped ? 'unmapped' : 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->insertIgnoreChunked('odc_assets', $rows, $chunk);
    }

    /** @param array<string, int> $config */
    private function seedOdps(array $config, mixed $now, int $chunk): void
    {
        $projects = $this->projectIdsByCode();
        $areas = $this->areaIdsByCode();
        $odcs = $this->odcIdsByCode();
        $coreColors = array_map(fn (OdpCoreColor $color): string => $color->value, OdpCoreColor::cases());
        $rows = [];

        foreach ($projects as $projectCode => $projectId) {
            $projectIndex = $this->numberFromCode($projectCode);

            foreach (range(1, $config['odps_per_project']) as $odpIndex) {
                $areaIndex = (($odpIndex - 1) % $config['areas_per_project']) + 1;
                $odcIndex = (($odpIndex - 1) % $config['odcs_per_project']) + 1;
                $isUnlinked = $odpIndex % 200 === 0 || $odpIndex === $config['odps_per_project'];

                $rows[] = [
                    'project_id' => $projectId,
                    'area_id' => $areas[$this->areaCode($projectIndex, $areaIndex)],
                    'odc_asset_id' => $isUnlinked ? null : ($odcs[$this->odcCode($projectIndex, $odcIndex)] ?? null),
                    'box_id' => $this->odpCode($projectIndex, $odpIndex),
                    'photo_path' => 'assets/big-data/odp.png',
                    'latitude' => $this->latitude($projectIndex, $odpIndex),
                    'longitude' => $this->longitude($projectIndex, $odpIndex),
                    'core_color' => $coreColors[($odpIndex - 1) % count($coreColors)],
                    'source_submission_id' => null,
                    'approved_by' => null,
                    'approved_at' => $now,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->insertIgnoreChunked('odp_assets', $rows, $chunk);
    }

    /** @param array<string, int> $config */
    private function seedOdcPorts(array $config, mixed $now, int $chunk): void
    {
        $rows = [];

        DB::table('odc_assets')
            ->where('box_id', 'like', self::PREFIX . '-ODC-P%')
            ->orderBy('id')
            ->select('id')
            ->chunkById(500, function ($odcs) use (&$rows, $config, $now, $chunk): void {
                foreach ($odcs as $odc) {
                    foreach (range(1, $config['ports_per_asset']) as $portNumber) {
                        $rows[] = [
                            'odc_asset_id' => $odc->id,
                            'port_number' => $portNumber,
                            'status' => $portNumber <= 4 ? PortStatus::Used->value : PortStatus::Available->value,
                            'source_submission_id' => null,
                            'updated_by' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    $this->flushIfNeeded('odc_ports', $rows, $chunk);
                }
            });

        $this->flush('odc_ports', $rows);
    }

    /** @param array<string, int> $config */
    private function seedOdpPorts(array $config, mixed $now, int $chunk): void
    {
        $rows = [];
        $sequence = 1;

        DB::table('odp_assets')
            ->where('box_id', 'like', self::PREFIX . '-ODP-P%')
            ->orderBy('box_id')
            ->select('id')
            ->chunk(500, function ($odps) use (&$rows, &$sequence, $config, $now, $chunk): void {
                foreach ($odps as $odp) {
                    foreach ($this->odpStatusesFor($sequence, $config['ports_per_asset']) as $portNumber => $status) {
                        $rows[] = [
                            'odp_asset_id' => $odp->id,
                            'port_number' => $portNumber,
                            'status' => $status,
                            'source_submission_id' => null,
                            'updated_by' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    $sequence++;
                    $this->flushIfNeeded('odp_ports', $rows, $chunk);
                }
            });

        $this->flush('odp_ports', $rows);
    }

    /** @param array<string, int> $config */
    private function seedSubmissions(array $config, mixed $now, int $chunk): void
    {
        $projects = $this->projectIdsByCode();
        $expectedSubmissions = count($projects) * $config['submissions_per_project'];

        if (DB::table('submissions')->whereIn('project_id', $projects)->where('odc_box_id', 'like', self::PREFIX . '-ODC-P%')->count() >= $expectedSubmissions) {
            return;
        }

        $areas = $this->areaIdsByCode();
        $teams = $this->teamIdsByName();
        $technicians = $this->technicianIds();
        $rows = [];
        $submissionCounter = 1;

        foreach ($projects as $projectCode => $projectId) {
            $projectIndex = $this->numberFromCode($projectCode);

            foreach (range(1, $config['submissions_per_project']) as $submissionIndex) {
                $areaIndex = (($submissionIndex - 1) % $config['areas_per_project']) + 1;
                $status = $this->submissionStatusFor($submissionCounter);
                $reviewed = in_array($status, [SubmissionStatus::Approved, SubmissionStatus::Rejected, SubmissionStatus::CorrectionNeeded], true);

                $rows[] = [
                    'project_id' => $projectId,
                    'technician_id' => $technicians[($submissionCounter - 1) % count($technicians)],
                    'team_id' => $teams[sprintf('Big Team P%03d-%03d', $projectIndex, $areaIndex)],
                    'area_id' => $areas[$this->areaCode($projectIndex, $areaIndex)],
                    'work_date' => now()->subDays($submissionIndex % 30)->toDateString(),
                    'odc_box_id' => $this->odcCode($projectIndex, (($submissionIndex - 1) % $config['odcs_per_project']) + 1),
                    'odc_photo_path' => 'submissions/big-data/odc.png',
                    'odc_latitude' => $this->latitude($projectIndex, $submissionIndex),
                    'odc_longitude' => $this->longitude($projectIndex, $submissionIndex),
                    'odp_box_id' => $this->odpCode($projectIndex, (($submissionIndex - 1) % $config['odps_per_project']) + 1),
                    'odp_photo_path' => 'submissions/big-data/odp.png',
                    'odp_latitude' => $this->latitude($projectIndex, $submissionIndex + 1),
                    'odp_longitude' => $this->longitude($projectIndex, $submissionIndex + 1),
                    'odp_core_color' => OdpCoreColor::Biru->value,
                    'notes' => 'Synthetic submission for resource pagination testing.',
                    'status' => $status->value,
                    'review_notes' => $reviewed ? 'Synthetic review note.' : null,
                    'reviewed_by' => null,
                    'submitted_at' => $status === SubmissionStatus::Draft ? null : $now,
                    'reviewed_at' => $reviewed ? $now : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $submissionCounter++;
            }
        }

        $this->insertIgnoreChunked('submissions', $rows, $chunk);
        $this->seedSubmissionPorts($config, $now, $chunk);
    }

    /** @param array<string, int> $config */
    private function seedSubmissionPorts(array $config, mixed $now, int $chunk): void
    {
        $rows = [];

        DB::table('submissions')
            ->where('odc_box_id', 'like', self::PREFIX . '-ODC-P%')
            ->orderBy('id')
            ->select('id')
            ->chunkById(500, function ($submissions) use (&$rows, $config, $now, $chunk): void {
                foreach ($submissions as $submission) {
                    foreach (['odc', 'odp'] as $assetType) {
                        foreach (range(1, $config['ports_per_asset']) as $portNumber) {
                            $rows[] = [
                                'submission_id' => $submission->id,
                                'asset_type' => $assetType,
                                'port_number' => $portNumber,
                                'status' => $portNumber <= 4 ? PortStatus::Used->value : PortStatus::Available->value,
                            ];
                        }
                    }

                    $this->flushIfNeeded('submission_ports', $rows, $chunk);
                }
            });

        $this->flush('submission_ports', $rows);
    }

    /** @return array<int, string> */
    private function odpStatusesFor(int $sequence, int $portsPerAsset): array
    {
        $slot = $sequence % 20;

        if ($slot === 0) {
            return $this->portStatuses($portsPerAsset, used: 2, reserved: 0, available: 2, broken: 2, unknown: 2);
        }

        if ($slot <= 12) {
            return $this->portStatuses($portsPerAsset, used: 2, reserved: 0, available: 6);
        }

        if ($slot <= 16) {
            return $this->portStatuses($portsPerAsset, used: 6, reserved: 1, available: 1);
        }

        return $this->portStatuses($portsPerAsset, used: 7, reserved: 1, available: 0);
    }

    /** @return array<int, string> */
    private function portStatuses(int $portsPerAsset, int $used, int $reserved, int $available, int $broken = 0, int $unknown = 0): array
    {
        $statuses = [
            ...array_fill(0, $used, PortStatus::Used->value),
            ...array_fill(0, $reserved, PortStatus::Reserved->value),
            ...array_fill(0, $available, PortStatus::Available->value),
            ...array_fill(0, $broken, PortStatus::Broken->value),
            ...array_fill(0, $unknown, PortStatus::Unknown->value),
        ];

        $statuses = array_pad(array_slice($statuses, 0, $portsPerAsset), $portsPerAsset, PortStatus::Available->value);

        return array_combine(range(1, $portsPerAsset), $statuses);
    }

    private function submissionStatusFor(int $sequence): SubmissionStatus
    {
        return match ($sequence % 6) {
            0 => SubmissionStatus::Approved,
            1 => SubmissionStatus::Draft,
            2 => SubmissionStatus::Submitted,
            3 => SubmissionStatus::CorrectionNeeded,
            4 => SubmissionStatus::Resubmitted,
            default => SubmissionStatus::Rejected,
        };
    }

    private function ponCapacityFor(int $ponNumber): int
    {
        return match ($ponNumber) {
            1 => 128,
            2 => 230,
            3 => 280,
            default => 512,
        };
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function insertIgnoreChunked(string $table, array $rows, int $chunk): void
    {
        foreach (array_chunk($rows, $chunk) as $batch) {
            DB::table($table)->insertOrIgnore($batch);
        }
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function flushIfNeeded(string $table, array &$rows, int $chunk): void
    {
        if (count($rows) >= $chunk) {
            $this->flush($table, $rows);
        }
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function flush(string $table, array &$rows): void
    {
        if ($rows === []) {
            return;
        }

        DB::table($table)->insertOrIgnore($rows);
        $rows = [];
    }

    /** @return array<string, int> */
    private function projectIdsByCode(): array
    {
        return DB::table('projects')->where('code', 'like', self::PREFIX . '-P%')->orderBy('code')->pluck('id', 'code')->all();
    }

    /** @return array<string, int> */
    private function areaIdsByCode(): array
    {
        return DB::table('areas')->where('code', 'like', self::PREFIX . '-A-P%')->pluck('id', 'code')->all();
    }

    /** @return array<string, int> */
    private function oltIdsByCode(): array
    {
        return DB::table('olt_assets')->where('code', 'like', self::PREFIX . '-OLT-P%')->pluck('id', 'code')->all();
    }

    /** @return array<string, int> */
    private function ponIdsByOltAndNumber(): array
    {
        return DB::table('olt_pon_ports')
            ->join('olt_assets', 'olt_assets.id', '=', 'olt_pon_ports.olt_asset_id')
            ->where('olt_assets.code', 'like', self::PREFIX . '-OLT-P%')
            ->select('olt_assets.code', 'olt_pon_ports.pon_number', 'olt_pon_ports.id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [$row->code . ':' . $row->pon_number => $row->id])
            ->all();
    }

    /** @return array<string, int> */
    private function odcIdsByCode(): array
    {
        return DB::table('odc_assets')->where('box_id', 'like', self::PREFIX . '-ODC-P%')->pluck('id', 'box_id')->all();
    }

    /** @return array<string, int> */
    private function teamIdsByName(): array
    {
        return DB::table('teams')->where('name', 'like', 'Big Team P%')->pluck('id', 'name')->all();
    }

    /** @return array<int, int> */
    private function technicianIds(): array
    {
        return DB::table('users')->where('email', 'like', 'big-tech-%@skynet.local')->orderBy('email')->pluck('id')->all();
    }

    private function projectCode(int $projectIndex): string
    {
        return sprintf('%s-P%03d', self::PREFIX, $projectIndex);
    }

    private function areaCode(int $projectIndex, int $areaIndex): string
    {
        return sprintf('%s-A-P%03d-%03d', self::PREFIX, $projectIndex, $areaIndex);
    }

    private function oltCode(int $projectIndex, int $oltIndex): string
    {
        return sprintf('%s-OLT-P%03d-%02d', self::PREFIX, $projectIndex, $oltIndex);
    }

    private function odcCode(int $projectIndex, int $odcIndex): string
    {
        return sprintf('%s-ODC-P%03d-%03d', self::PREFIX, $projectIndex, $odcIndex);
    }

    private function odpCode(int $projectIndex, int $odpIndex): string
    {
        return sprintf('%s-ODP-P%03d-%05d', self::PREFIX, $projectIndex, $odpIndex);
    }

    private function numberFromCode(string $code): int
    {
        preg_match('/P(\d+)/', $code, $matches);

        return (int) ($matches[1] ?? 0);
    }

    private function latitude(int $projectIndex, int $offset): string
    {
        return number_format(-7.90 - ($projectIndex * 0.01) - (($offset % 100) * 0.0001), 8, '.', '');
    }

    private function longitude(int $projectIndex, int $offset): string
    {
        return number_format(112.60 + ($projectIndex * 0.01) + (($offset % 100) * 0.0001), 8, '.', '');
    }
}
