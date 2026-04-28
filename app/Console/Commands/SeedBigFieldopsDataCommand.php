<?php

namespace App\Console\Commands;

use App\Services\BigDataSeeder;
use Illuminate\Console\Command;
use InvalidArgumentException;

class SeedBigFieldopsDataCommand extends Command
{
    protected $signature = 'fieldops:seed-big-data
        {--profile=medium : Data profile to seed: medium or tiny}
        {--reset : Remove existing BIG-* generated data before seeding}
        {--chunk=1000 : Bulk insert chunk size}
        {--with-submissions : Include synthetic submissions and submission ports}';

    protected $description = 'Seed deterministic big FieldOps data for dashboard and resource testing.';

    public function handle(BigDataSeeder $seeder): int
    {
        $profile = (string) $this->option('profile');
        $chunk = (int) $this->option('chunk');

        if ($chunk < 1) {
            $this->error('The --chunk option must be greater than zero.');

            return self::FAILURE;
        }

        try {
            $counts = $seeder->run(
                profile: $profile,
                reset: (bool) $this->option('reset'),
                withSubmissions: (bool) $this->option('with-submissions'),
                chunk: $chunk,
                progress: fn (string $message) => $this->components->info($message),
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Big data seeding complete.');
        $this->table(
            ['Metric', 'Count'],
            collect($counts)->map(fn (int $count, string $metric): array => [$metric, number_format($count)])->values()->all(),
        );

        return self::SUCCESS;
    }
}
