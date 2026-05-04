<?php

namespace App\Services;

use App\Enums\AssetType;
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

    private string $activeProfile = 'medium';

    private string $scenario = 'balanced';

    private int $seed = 1234;

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
        'demo' => [
            'projects' => 4,
            'areas_per_project' => 8,
            'olts_per_project' => 4,
            'pons_per_olt' => 8,
            'odcs_per_project' => 120,
            'odps_per_project' => 560,
            'ports_per_asset' => 8,
            'submissions_per_project' => 160,
            'technicians' => 64,
        ],
    ];

    /** @var array<int, array{name: string, code: string, center: array{float, float}, description: string}> */
    private const DEMO_PROJECTS = [
        ['name' => 'Malang Timur FTTH', 'code' => 'MLG-TMR', 'center' => [-7.966620, 112.632632], 'description' => 'Ekspansi FTTH area urban Malang Timur.'],
        ['name' => 'Surabaya Barat Expansion', 'code' => 'SBY-BRT', 'center' => [-7.275610, 112.642643], 'description' => 'Perluasan kapasitas pelanggan Surabaya Barat.'],
        ['name' => 'Sidoarjo Cluster Upgrade', 'code' => 'SDA-UPG', 'center' => [-7.447800, 112.718300], 'description' => 'Upgrade cluster ODP dan PON padat pelanggan.'],
        ['name' => 'Gresik Industrial Link', 'code' => 'GSK-IND', 'center' => [-7.156650, 112.655500], 'description' => 'Backbone distribusi kawasan industri Gresik.'],
    ];

    /** @var array<int, string> */
    private const DEMO_AREA_NAMES = [
        'Lowokwaru',
        'Blimbing',
        'Klojen',
        'Pakis',
        'Waru',
        'Buduran',
        'Menganti',
        'Driyorejo',
        'Rungkut',
        'Tandes',
        'Sawahan',
        'Kebomas',
    ];

    /** @var array<int, string> */
    private const DEMO_TECHNICIAN_NAMES = [
        'Andi Pratama',
        'Budi Santoso',
        'Citra Lestari',
        'Dewi Anggraini',
        'Eko Wibowo',
        'Fajar Nugroho',
        'Gita Permata',
        'Hendra Wijaya',
        'Intan Maharani',
        'Joko Susilo',
        'Kurniawan Saputra',
        'Laras Putri',
        'Maya Kartika',
        'Nanda Firmansyah',
        'Putri Amelia',
        'Rizky Maulana',
    ];

    /** @var array<int, string> */
    private const ASSIGNMENT_NOTES = [
        'Validasi koordinat dan foto aset.',
        'Survey ulang port kosong untuk aktivasi pelanggan.',
        'Cek label box dan kondisi splitter.',
        'Pastikan jalur distribusi sesuai mapping OLT/PON.',
        'Lengkapi foto close-up dan tampak sekitar.',
    ];

    /** @return array<string, int> */
    public static function profile(string $profile): array
    {
        return self::PROFILES[$profile] ?? throw new InvalidArgumentException("Unknown big data profile [{$profile}].");
    }

    /** @return array<string, int> */
    public function run(
        string $profile = 'medium',
        bool $reset = false,
        bool $withSubmissions = false,
        int $chunk = 1000,
        string $scenario = 'balanced',
        int $seed = 1234,
        ?callable $progress = null,
    ): array {
        $config = self::profile($profile);
        $chunk = max(1, $chunk);
        $this->activeProfile = $profile;
        $this->scenario = in_array($scenario, ['balanced', 'critical', 'clean'], true) ? $scenario : 'balanced';
        $this->seed = $seed;

        if ($reset) {
            $this->progress($progress, 'Menghapus data BIG yang lama');
            $this->reset();
        }

        DB::disableQueryLog();

        $now = now();

        $this->progress($progress, 'Membuat data teknisi');
        $this->seedUsers($config, $now, $chunk);

        $this->progress($progress, 'Membuat proyek, area, OLT, dan PON');
        $this->seedProjects($config, $now, $chunk);
        $this->seedAreas($config, $now, $chunk);
        $this->seedOlts($config, $now, $chunk);
        $this->seedPonPorts($config, $now, $chunk);

        $this->progress($progress, 'Membuat hirarki ODC dan ODP');
        $this->seedOdcs($config, $now, $chunk);
        $this->seedOdps($config, $now, $chunk);

        $this->progress($progress, 'Membuat port ODC');
        $this->seedOdcPorts($config, $now, $chunk);

        $this->progress($progress, 'Membuat port ODP');
        $this->seedOdpPorts($config, $now, $chunk);

        if ($withSubmissions) {
            $this->progress($progress, 'Membuat penugasan lapangan');
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
            DB::table('projects')->where('code', 'like', self::PREFIX.'-P%')->delete();
            DB::table('users')->where('email', 'like', 'big-tech-%@skynet.local')->delete();
        });
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        $projectIds = DB::table('projects')->where('code', 'like', self::PREFIX.'-P%')->pluck('id');
        $oltIds = DB::table('olt_assets')->whereIn('project_id', $projectIds)->pluck('id');
        $ponIds = DB::table('olt_pon_ports')->whereIn('olt_asset_id', $oltIds)->pluck('id');
        $odcIds = DB::table('odc_assets')->whereIn('project_id', $projectIds)->pluck('id');
        $odpIds = DB::table('odp_assets')->whereIn('project_id', $projectIds)->pluck('id');
        $submissionIds = DB::table('submissions')->whereIn('project_id', $projectIds)->pluck('id');

        return [
            'projects' => $projectIds->count(),
            'areas' => DB::table('areas')->whereIn('project_id', $projectIds)->count(),
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
            $name = $this->isDemo()
                ? self::DEMO_TECHNICIAN_NAMES[($index - 1) % count(self::DEMO_TECHNICIAN_NAMES)].sprintf(' %02d', (int) ceil($index / count(self::DEMO_TECHNICIAN_NAMES)))
                : sprintf('Teknisi Big Data %03d', $index);

            $rows[] = [
                'name' => $name,
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
            $project = $this->demoProject($projectIndex);

            $rows[] = [
                'name' => $this->isDemo() ? $project['name'] : "Proyek Big Data {$projectCode}",
                'code' => $projectCode,
                'description' => $this->isDemo() ? $project['description'] : 'Data sintetis untuk pengujian dashboard dan resource.',
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
                    'name' => $this->isDemo() ? $this->demoAreaName($projectIndex, $areaIndex) : "Area Big {$areaCode}",
                    'code' => $areaCode,
                    'description' => $this->isDemo() ? 'Area layanan demo dengan pola utilisasi realistis.' : 'Area layanan sintetis.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->insertIgnoreChunked('areas', $rows, $chunk);
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
                [$latitude, $longitude] = $this->oltCoordinate($projectIndex, $areaIndex, $oltIndex);

                $rows[] = [
                    'project_id' => $projectId,
                    'area_id' => $areas[$this->areaCode($projectIndex, $areaIndex)] ?? null,
                    'name' => $this->isDemo()
                        ? sprintf('OLT %s %02d', $this->demoAreaName($projectIndex, $areaIndex), $oltIndex)
                        : "Big OLT {$oltCode}",
                    'code' => $oltCode,
                    'location' => $this->isDemo()
                        ? sprintf('POP %s - Ring %02d', $this->demoAreaName($projectIndex, $areaIndex), $oltIndex)
                        : sprintf('Synthetic POP P%03d-%02d', $projectIndex, $oltIndex),
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'status' => $this->isDemo() && $oltIndex === $config['olts_per_project'] && $projectIndex % 2 === 0 ? 'maintenance' : 'active',
                    'notes' => $this->isDemo() ? 'Data demo untuk monitoring kapasitas jaringan.' : 'Generated by fieldops:seed-big-data.',
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
                    'label' => 'PON '.$ponNumber,
                    'capacity' => $this->ponCapacityFor($ponNumber),
                    'status' => $this->isDemo() && $ponNumber === 8 ? 'maintenance' : 'active',
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
                $isUnmapped = $this->isOdcUnmapped($odcIndex, $config['odcs_per_project']);
                [$latitude, $longitude] = $this->odcCoordinate($projectIndex, $areaIndex, $odcIndex, $oltIndex, $ponNumber);

                $rows[] = [
                    'project_id' => $projectId,
                    'area_id' => $areas[$this->areaCode($projectIndex, $areaIndex)],
                    'olt_pon_port_id' => $isUnmapped ? null : ($pons[$this->oltCode($projectIndex, $oltIndex).':'.$ponNumber] ?? null),
                    'box_id' => $this->odcCode($projectIndex, $odcIndex),
                    'photo_path' => 'assets/big-data/odc.png',
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'source_submission_id' => null,
                    'approved_by' => null,
                    'approved_at' => $now,
                    'status' => $isUnmapped ? 'unmapped' : $this->assetStatusFor($odcIndex),
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
                $isUnlinked = $this->isOdpUnlinked($odpIndex, $config['odps_per_project']);
                [$latitude, $longitude] = $this->odpCoordinate($projectIndex, $areaIndex, $odpIndex, $isUnlinked ? 0 : $odcIndex);

                $rows[] = [
                    'project_id' => $projectId,
                    'area_id' => $areas[$this->areaCode($projectIndex, $areaIndex)],
                    'odc_asset_id' => $isUnlinked ? null : ($odcs[$this->odcCode($projectIndex, $odcIndex)] ?? null),
                    'box_id' => $this->odpCode($projectIndex, $odpIndex),
                    'photo_path' => 'assets/big-data/odp.png',
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'core_color' => $coreColors[($odpIndex - 1) % count($coreColors)],
                    'source_submission_id' => null,
                    'approved_by' => null,
                    'approved_at' => $now,
                    'status' => $this->assetStatusFor($odpIndex),
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
            ->where('box_id', 'like', self::PREFIX.'-ODC-P%')
            ->orderBy('id')
            ->select('id')
            ->chunkById(500, function ($odcs) use (&$rows, $config, $now, $chunk): void {
                foreach ($odcs as $odc) {
                    foreach (range(1, $config['ports_per_asset']) as $portNumber) {
                        $rows[] = [
                            'odc_asset_id' => $odc->id,
                            'port_number' => $portNumber,
                            'status' => $this->odcPortStatusFor((int) $odc->id, $portNumber),
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
            ->where('box_id', 'like', self::PREFIX.'-ODP-P%')
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

        if (DB::table('submissions')->whereIn('project_id', $projects)->where('box_id', 'like', self::PREFIX.'-%')->count() >= $expectedSubmissions) {
            return;
        }

        $areas = $this->areaIdsByCode();
        $technicians = $this->technicianIds();
        $rows = [];
        $submissionCounter = 1;

        foreach ($projects as $projectCode => $projectId) {
            $projectIndex = $this->numberFromCode($projectCode);

            foreach (range(1, $config['submissions_per_project']) as $submissionIndex) {
                $areaIndex = (($submissionIndex - 1) % $config['areas_per_project']) + 1;
                $status = $this->submissionStatusFor($submissionCounter);
                $reviewed = in_array($status, [SubmissionStatus::Approved, SubmissionStatus::Rejected, SubmissionStatus::CorrectionNeeded], true);
                $assetType = $submissionCounter % 2 === 0 ? AssetType::Odp : AssetType::Odc;
                $assetIndex = (($submissionIndex - 1) % ($assetType === AssetType::Odc ? $config['odcs_per_project'] : $config['odps_per_project'])) + 1;
                [$plannedLatitude, $plannedLongitude] = $assetType === AssetType::Odc
                    ? $this->odcCoordinate(
                        $projectIndex,
                        $areaIndex,
                        $assetIndex,
                        (($assetIndex - 1) % $config['olts_per_project']) + 1,
                        (($assetIndex - 1) % $config['pons_per_olt']) + 1,
                    )
                    : $this->odpCoordinate(
                        $projectIndex,
                        $areaIndex,
                        $assetIndex,
                        (($assetIndex - 1) % $config['odcs_per_project']) + 1,
                    );
                $coordinateDrift = (($submissionCounter % 7) - 3) * 0.000012;
                $fieldLatitude = $status === SubmissionStatus::Assigned ? null : number_format(((float) $plannedLatitude) + $coordinateDrift, 8, '.', '');
                $fieldLongitude = $status === SubmissionStatus::Assigned ? null : number_format(((float) $plannedLongitude) - $coordinateDrift, 8, '.', '');

                $rows[] = [
                    'project_id' => $projectId,
                    'technician_id' => $technicians[($submissionCounter - 1) % count($technicians)],
                    'area_id' => $areas[$this->areaCode($projectIndex, $areaIndex)],
                    'work_date' => now()->subDays($submissionIndex % 30)->toDateString(),
                    'asset_type' => $assetType->value,
                    'box_id' => $assetType === AssetType::Odc ? $this->odcCode($projectIndex, $assetIndex) : $this->odpCode($projectIndex, $assetIndex),
                    'photo_path' => $assetType === AssetType::Odc ? 'submissions/big-data/odc.png' : 'submissions/big-data/odp.png',
                    'planned_latitude' => $plannedLatitude,
                    'planned_longitude' => $plannedLongitude,
                    'latitude' => $fieldLatitude,
                    'longitude' => $fieldLongitude,
                    'core_color' => $assetType === AssetType::Odp ? OdpCoreColor::Biru->value : null,
                    'parent_odc_asset_id' => null,
                    'notes' => $this->submissionNotesFor($submissionCounter, $status),
                    'status' => $status->value,
                    'review_notes' => $reviewed ? $this->reviewNotesFor($status) : null,
                    'reviewed_by' => null,
                    'assigned_by' => null,
                    'assigned_at' => $now,
                    'assignment_notes' => self::ASSIGNMENT_NOTES[($submissionCounter - 1) % count(self::ASSIGNMENT_NOTES)],
                    'submitted_at' => $status === SubmissionStatus::Assigned ? null : $now,
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
            ->where('box_id', 'like', self::PREFIX.'-%')
            ->orderBy('id')
            ->select('id', 'asset_type')
            ->chunkById(500, function ($submissions) use (&$rows, $config, $chunk): void {
                foreach ($submissions as $submission) {
                    foreach (range(1, $config['ports_per_asset']) as $portNumber) {
                        $rows[] = [
                            'submission_id' => $submission->id,
                            'asset_type' => $submission->asset_type,
                            'port_number' => $portNumber,
                            'status' => $this->submissionPortStatusFor((int) $submission->id, $portNumber),
                        ];
                    }

                    $this->flushIfNeeded('submission_ports', $rows, $chunk);
                }
            });

        $this->flush('submission_ports', $rows);
    }

    /** @return array<int, string> */
    private function odpStatusesFor(int $sequence, int $portsPerAsset): array
    {
        if ($this->scenario === 'clean') {
            $slot = $sequence % 10;

            return $slot <= 7
                ? $this->portStatuses($portsPerAsset, used: 2, reserved: 0, available: 6)
                : $this->portStatuses($portsPerAsset, used: 5, reserved: 1, available: 2);
        }

        if ($this->scenario === 'critical') {
            $slot = $sequence % 20;

            if ($slot <= 5) {
                return $this->portStatuses($portsPerAsset, used: 7, reserved: 1, available: 0);
            }

            if ($slot <= 12) {
                return $this->portStatuses($portsPerAsset, used: 6, reserved: 1, available: 1);
            }

            return $slot === 19
                ? $this->portStatuses($portsPerAsset, used: 2, reserved: 0, available: 2, broken: 2, unknown: 2)
                : $this->portStatuses($portsPerAsset, used: 3, reserved: 0, available: 5);
        }

        $slot = $sequence % 20;

        if ($slot < 11) {
            return $this->portStatuses($portsPerAsset, used: 2, reserved: 0, available: 6);
        }

        if ($slot < 16) {
            return $this->portStatuses($portsPerAsset, used: 5, reserved: 1, available: 2);
        }

        if ($slot < 18) {
            return $this->portStatuses($portsPerAsset, used: 6, reserved: 1, available: 1);
        }

        if ($slot < 19) {
            return $this->portStatuses($portsPerAsset, used: 2, reserved: 0, available: 2, broken: 2, unknown: 2);
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
        $slot = $sequence % 100;

        if ($this->scenario === 'clean') {
            return match (true) {
                $slot < 45 => SubmissionStatus::Approved,
                $slot < 70 => SubmissionStatus::Submitted,
                $slot < 90 => SubmissionStatus::Assigned,
                default => SubmissionStatus::Resubmitted,
            };
        }

        if ($this->scenario === 'critical') {
            return match (true) {
                $slot < 25 => SubmissionStatus::Assigned,
                $slot < 45 => SubmissionStatus::Submitted,
                $slot < 60 => SubmissionStatus::Approved,
                $slot < 80 => SubmissionStatus::CorrectionNeeded,
                $slot < 90 => SubmissionStatus::Resubmitted,
                default => SubmissionStatus::Rejected,
            };
        }

        return match (true) {
            $slot < 35 => SubmissionStatus::Assigned,
            $slot < 60 => SubmissionStatus::Submitted,
            $slot < 80 => SubmissionStatus::Approved,
            $slot < 90 => SubmissionStatus::CorrectionNeeded,
            $slot < 95 => SubmissionStatus::Resubmitted,
            default => SubmissionStatus::Rejected,
        };
    }

    private function ponCapacityFor(int $ponNumber): int
    {
        if ($this->isDemo()) {
            return match ($ponNumber) {
                1, 2 => 64,
                3, 4 => 96,
                5, 6 => 128,
                default => 192,
            };
        }

        return match ($ponNumber) {
            1 => 128,
            2 => 230,
            3 => 280,
            default => 512,
        };
    }

    private function isOdcUnmapped(int $odcIndex, int $odcsPerProject): bool
    {
        if ($this->scenario === 'clean') {
            return $odcIndex % 240 === 0 || $odcIndex === $odcsPerProject;
        }

        if ($this->scenario === 'critical') {
            return $odcIndex % 24 === 0 || $odcIndex === $odcsPerProject;
        }

        return $odcIndex % 60 === 0 || $odcIndex === $odcsPerProject;
    }

    private function isOdpUnlinked(int $odpIndex, int $odpsPerProject): bool
    {
        if ($this->scenario === 'clean') {
            return $odpIndex % 320 === 0 || $odpIndex === $odpsPerProject;
        }

        if ($this->scenario === 'critical') {
            return $odpIndex % 45 === 0 || $odpIndex === $odpsPerProject;
        }

        return $odpIndex % 140 === 0 || $odpIndex === $odpsPerProject;
    }

    private function assetStatusFor(int $sequence): string
    {
        if ($this->scenario === 'clean') {
            return $sequence % 80 === 0 ? 'maintenance' : 'active';
        }

        if ($this->scenario === 'critical') {
            return $sequence % 18 === 0 ? 'maintenance' : 'active';
        }

        return $sequence % 40 === 0 ? 'maintenance' : 'active';
    }

    private function odcPortStatusFor(int $odcId, int $portNumber): string
    {
        if ($this->scenario === 'critical' && ($odcId + $portNumber) % 11 === 0) {
            return PortStatus::Broken->value;
        }

        return $portNumber <= 4 ? PortStatus::Used->value : PortStatus::Available->value;
    }

    private function submissionPortStatusFor(int $submissionId, int $portNumber): string
    {
        if (($submissionId + $portNumber) % 17 === 0) {
            return PortStatus::Unknown->value;
        }

        if (($submissionId + $portNumber) % 13 === 0) {
            return PortStatus::Reserved->value;
        }

        return $portNumber <= 4 ? PortStatus::Used->value : PortStatus::Available->value;
    }

    private function submissionNotesFor(int $sequence, SubmissionStatus $status): ?string
    {
        if ($status === SubmissionStatus::Assigned) {
            return null;
        }

        return match ($sequence % 5) {
            0 => 'Foto dan koordinat sudah divalidasi di lokasi.',
            1 => 'Port kosong sudah dicek dan diberi label ulang.',
            2 => 'Kondisi box baik, perlu monitoring kapasitas.',
            3 => 'Terdapat perbedaan label, sudah dicatat untuk review.',
            default => 'Data lapangan sudah dilengkapi oleh teknisi.',
        };
    }

    private function reviewNotesFor(SubmissionStatus $status): string
    {
        return match ($status) {
            SubmissionStatus::Approved => 'Data sudah sesuai.',
            SubmissionStatus::CorrectionNeeded => 'Foto kurang jelas, mohon unggah ulang.',
            SubmissionStatus::Rejected => 'Duplikasi dengan aset existing.',
            default => 'Menunggu tindak lanjut.',
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
        return DB::table('projects')->where('code', 'like', self::PREFIX.'-P%')->orderBy('code')->pluck('id', 'code')->all();
    }

    /** @return array<string, int> */
    private function areaIdsByCode(): array
    {
        return DB::table('areas')->where('code', 'like', self::PREFIX.'-A-P%')->pluck('id', 'code')->all();
    }

    /** @return array<string, int> */
    private function oltIdsByCode(): array
    {
        return DB::table('olt_assets')->where('code', 'like', self::PREFIX.'-OLT-P%')->pluck('id', 'code')->all();
    }

    /** @return array<string, int> */
    private function ponIdsByOltAndNumber(): array
    {
        return DB::table('olt_pon_ports')
            ->join('olt_assets', 'olt_assets.id', '=', 'olt_pon_ports.olt_asset_id')
            ->where('olt_assets.code', 'like', self::PREFIX.'-OLT-P%')
            ->select('olt_assets.code', 'olt_pon_ports.pon_number', 'olt_pon_ports.id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [$row->code.':'.$row->pon_number => $row->id])
            ->all();
    }

    /** @return array<string, int> */
    private function odcIdsByCode(): array
    {
        return DB::table('odc_assets')->where('box_id', 'like', self::PREFIX.'-ODC-P%')->pluck('id', 'box_id')->all();
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

    /** @return array{float, float} */
    private function projectCenter(int $projectIndex): array
    {
        if ($this->isDemo()) {
            return $this->demoProject($projectIndex)['center'];
        }

        $columns = 5;
        $row = intdiv($projectIndex - 1, $columns);
        $column = ($projectIndex - 1) % $columns;

        return [
            -7.760000 + ($row * -0.155000),
            112.470000 + ($column * 0.180000),
        ];
    }

    /** @return array{float, float} */
    private function areaCenter(int $projectIndex, int $areaIndex): array
    {
        [$projectLatitude, $projectLongitude] = $this->projectCenter($projectIndex);
        $areasPerProject = $this->activeProfileConfig()['areas_per_project'];
        $columns = (int) ceil(sqrt($areasPerProject));
        $rows = (int) ceil($areasPerProject / $columns);
        $row = intdiv($areaIndex - 1, $columns);
        $column = ($areaIndex - 1) % $columns;
        $latitudeSpacing = $this->isDemo() ? 0.015000 : 0.022000;
        $longitudeSpacing = $this->isDemo() ? 0.019000 : 0.028000;
        $seedLatitudeOffset = $this->deterministicUnit('area-lat', $projectIndex, $areaIndex) * 0.000260;
        $seedLongitudeOffset = $this->deterministicUnit('area-lng', $projectIndex, $areaIndex) * 0.000260;

        return [
            $projectLatitude + (($row - (($rows - 1) / 2)) * $latitudeSpacing) + $seedLatitudeOffset,
            $projectLongitude + (($column - (($columns - 1) / 2)) * $longitudeSpacing) + $seedLongitudeOffset,
        ];
    }

    /** @return array{string, string} */
    private function oltCoordinate(int $projectIndex, int $areaIndex, int $oltIndex): array
    {
        [$latitude, $longitude] = $this->areaCenter($projectIndex, $areaIndex);
        [$jitterLatitude, $jitterLongitude] = $this->jitter('olt', 0.000420, $projectIndex, $areaIndex, $oltIndex);

        return $this->formatCoordinatePair($latitude + $jitterLatitude, $longitude + $jitterLongitude);
    }

    /** @return array{string, string} */
    private function odcCoordinate(int $projectIndex, int $areaIndex, int $odcIndex, int $oltIndex, int $ponNumber): array
    {
        [$latitude, $longitude] = $this->areaCenter($projectIndex, $areaIndex);
        $localIndex = $this->localAreaSequence($odcIndex, $areaIndex);
        [$ringLatitude, $ringLongitude] = $this->ringOffset(
            sequence: ($localIndex * 17) + ($ponNumber * 7) + $oltIndex,
            baseRadius: 0.001600,
            radiusStep: 0.000520,
            ringSize: 6,
        );
        [$jitterLatitude, $jitterLongitude] = $this->jitter('odc', 0.000180, $projectIndex, $areaIndex, $odcIndex);

        return $this->formatCoordinatePair(
            $latitude + $ringLatitude + $jitterLatitude,
            $longitude + $ringLongitude + $jitterLongitude,
        );
    }

    /** @return array{string, string} */
    private function odpCoordinate(int $projectIndex, int $areaIndex, int $odpIndex, int $odcIndex = 0): array
    {
        $config = $this->activeProfileConfig();

        if ($odcIndex > 0) {
            $parentAreaIndex = (($odcIndex - 1) % $config['areas_per_project']) + 1;
            $parentOltIndex = (($odcIndex - 1) % $config['olts_per_project']) + 1;
            $parentPonNumber = (($odcIndex - 1) % $config['pons_per_olt']) + 1;
            [$latitude, $longitude] = array_map('floatval', $this->odcCoordinate($projectIndex, $parentAreaIndex, $odcIndex, $parentOltIndex, $parentPonNumber));
            $localIndex = max(1, intdiv(max(0, $odpIndex - $odcIndex), $config['odcs_per_project']) + 1);
            [$ringLatitude, $ringLongitude] = $this->ringOffset(
                sequence: ($localIndex * 29) + $odpIndex,
                baseRadius: 0.000320,
                radiusStep: 0.000140,
                ringSize: 8,
            );
        } else {
            [$latitude, $longitude] = $this->areaCenter($projectIndex, $areaIndex);
            $localIndex = $this->localAreaSequence($odpIndex, $areaIndex);
            [$ringLatitude, $ringLongitude] = $this->ringOffset(
                sequence: ($localIndex * 19) + $odpIndex,
                baseRadius: 0.002400,
                radiusStep: 0.000380,
                ringSize: 10,
            );
        }

        [$jitterLatitude, $jitterLongitude] = $this->jitter('odp', 0.000070, $projectIndex, $areaIndex, $odpIndex);

        return $this->formatCoordinatePair(
            $latitude + $ringLatitude + $jitterLatitude,
            $longitude + $ringLongitude + $jitterLongitude,
        );
    }

    /** @return array<string, int> */
    private function activeProfileConfig(): array
    {
        return self::profile($this->activeProfile);
    }

    private function localAreaSequence(int $assetIndex, int $areaIndex): int
    {
        $areasPerProject = $this->activeProfileConfig()['areas_per_project'];

        return max(1, intdiv(max(0, $assetIndex - $areaIndex), $areasPerProject) + 1);
    }

    /** @return array{float, float} */
    private function ringOffset(int $sequence, float $baseRadius, float $radiusStep, int $ringSize): array
    {
        $ring = intdiv(max(0, $sequence - 1), max(1, $ringSize));
        $radius = $baseRadius + ($ring % 5) * $radiusStep;
        $angle = deg2rad((($sequence * 137) + ($this->seed % 360)) % 360);

        return [
            sin($angle) * $radius,
            cos($angle) * $radius,
        ];
    }

    /** @return array{float, float} */
    private function jitter(string $scope, float $scale, int ...$values): array
    {
        return [
            $this->deterministicUnit($scope.'-lat', ...$values) * $scale,
            $this->deterministicUnit($scope.'-lng', ...$values) * $scale,
        ];
    }

    private function deterministicUnit(string $scope, int ...$values): float
    {
        $unsigned = (float) sprintf('%u', crc32($this->seed.'|'.$scope.'|'.implode('|', $values)));

        return ($unsigned / 4294967295.0) * 2 - 1;
    }

    /** @return array{string, string} */
    private function formatCoordinatePair(float $latitude, float $longitude): array
    {
        $latitude = max(-90.0, min(90.0, $latitude));
        $longitude = max(-180.0, min(180.0, $longitude));

        return [
            number_format($latitude, 8, '.', ''),
            number_format($longitude, 8, '.', ''),
        ];
    }

    private function isDemo(): bool
    {
        return $this->activeProfile === 'demo';
    }

    /** @return array{name: string, code: string, center: array{float, float}, description: string} */
    private function demoProject(int $projectIndex): array
    {
        return self::DEMO_PROJECTS[($projectIndex - 1) % count(self::DEMO_PROJECTS)];
    }

    private function demoAreaName(int $projectIndex, int $areaIndex): string
    {
        $name = self::DEMO_AREA_NAMES[(($projectIndex - 1) * 3 + $areaIndex - 1) % count(self::DEMO_AREA_NAMES)];

        return sprintf('%s Cluster %02d', $name, $areaIndex);
    }
}
