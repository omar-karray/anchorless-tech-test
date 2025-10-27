<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class ConfigureApplication extends Command
{
    protected $signature = 'app:configure
        {--yes : Run without confirmation}
        {--recreate-minio : Recreate the MinIO bucket when configuring storage}';

    protected $description = 'Configure supporting services and rebuild the database for local testing.';

    public function handle(): int
    {
        if (! $this->installComposerDependencies()) {
            return $this->finalize(Command::FAILURE);
        }

        $autoApprove = (bool) $this->option('yes');
        $this->warn('This command can configure MinIO, rebuild the database, run tests, and clear caches.');

        $shouldConfigureMinio = $autoApprove
            ? true
            : $this->confirm('Configure MinIO now?', true);

        if ($shouldConfigureMinio) {
            $this->info('Configuring MinIO...');
            $minioOptions = $this->option('recreate-minio') ? ['--force' => true] : [];
            $minioResult = $this->call('minio:configure', $minioOptions);

            if ($minioResult !== Command::SUCCESS) {
                $this->error('MinIO configuration failed. Aborting.');
                return $this->finalize($minioResult);
            }
        } else {
            if ($this->option('recreate-minio')) {
                $this->warn('Skipping MinIO configuration despite --recreate-minio flag.');
            } else {
                $this->info('Skipping MinIO configuration.');
            }
        }

        $runMigrations = $this->option('yes')
            ? true
            : $this->confirm('Would you like to run migrate:fresh --seed now?', true);

        if ($runMigrations) {
            $this->info('Rebuilding database schema and seeding data...');
            $migrateResult = $this->call('migrate:fresh', [
                '--seed' => true,
                '--force' => true,
            ]);

            if ($migrateResult !== Command::SUCCESS) {
                $this->error('Database migration failed. Aborting.');
                return $this->finalize($migrateResult);
            }
        } else {
            $this->info('Skipping database migrations.');
        }

        $runTests = $autoApprove
            ? true
            : $this->confirm('Would you like to run the test suite now?', false);

        if ($runTests) {
            $this->info('Running automated tests...');
            $testResult = $this->call('test');

            if ($testResult !== Command::SUCCESS) {
                $this->warn('Tests reported failures.');
                return $this->finalize($testResult);
            }
        } else {
            $this->info('Skipping automated tests.');
        }

        $this->info('Application configured successfully.');

        return $this->finalize(Command::SUCCESS);
    }

    private function installComposerDependencies(): bool
    {
        $this->info('Ensuring Composer dependencies are installed...');

        $process = Process::fromShellCommandline('composer install --no-interaction --ansi', base_path());
        $process->setTimeout(null);

        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->error('Composer install failed.');
            $this->output->writeln($process->getErrorOutput());

            return false;
        }

        $this->info('Composer dependencies are up to date.');

        return true;
    }

    private function finalize(int $exitCode): int
    {
        $this->info('Clearing cached optimizations...');
        $this->call('optimize:clear');

        return $exitCode;
    }
}
