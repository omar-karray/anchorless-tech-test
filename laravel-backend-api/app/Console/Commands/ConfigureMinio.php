<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ConfigureMinio extends Command
{
    protected $signature = 'minio:configure {--force : Delete and recreate the bucket}';
    protected $description = 'Configure MinIO: ensure the bucket exists and the client is installed.';

    public function handle(): int
    {
        $disk = config('filesystems.disks.minio');

        if (empty($disk)) {
            $this->error('MinIO disk is not configured. Please check config/filesystems.php.');
            return Command::FAILURE;
        }

        $endpoint = $disk['endpoint'] ?? env('MINIO_ENDPOINT', 'http://minio:9000');
        $accessKey = $disk['key'] ?? env('MINIO_ACCESS_KEY_ID', 'sail');
        $secretKey = $disk['secret'] ?? env('MINIO_SECRET_ACCESS_KEY', 'password');
        $bucket = $disk['bucket'] ?? env('MINIO_BUCKET', 'uploads');
        $alias = Str::slug(parse_url($endpoint, PHP_URL_HOST) ?? 'minio');

        $this->info('Configuring MinIO...');

        try {
            $this->ensureClientIsInstalled();
            $this->setAlias($alias, $endpoint, $accessKey, $secretKey);
            $this->ensureBucketExists($alias, $bucket);
            $this->ensurePolicy($alias, $bucket, $accessKey);
            $this->verifyStorage($alias, $bucket);
        } catch (ProcessFailedException $exception) {
            $process = $exception->getProcess();
            $this->error("Command failed: {$process->getCommandLine()}");
            $this->newLine();
            $this->line(trim($process->getErrorOutput()));
            return Command::FAILURE;
        } catch (\Throwable $throwable) {
            $this->error($throwable->getMessage());
            return Command::FAILURE;
        }

        $this->info('MinIO bucket is ready.');

        return Command::SUCCESS;
    }

    private function ensureClientIsInstalled(): void
    {
        if ($this->commandExists('mc')) {
            return;
        }

        $this->info('Installing MinIO client (mc)...');

        $downloadCommand = sprintf(
            'curl -sSL %s -o /usr/local/bin/mc',
            escapeshellarg('https://dl.min.io/client/mc/release/linux-amd64/mc')
        );

        $this->runShell($downloadCommand);
        $this->runProcess(['chmod', '+x', '/usr/local/bin/mc']);
    }

    private function setAlias(string $alias, string $endpoint, string $accessKey, string $secretKey): void
    {
        $this->runProcess(['mc', 'alias', 'set', $alias, $endpoint, $accessKey, $secretKey]);
        $this->info("Alias '{$alias}' configured.");
    }

    private function ensureBucketExists(string $alias, string $bucket): void
    {
        if ($this->option('force')) {
            $this->info("--force supplied: recreating bucket '{$bucket}'.");
            $this->runProcess(['mc', 'rb', '--force', "{$alias}/{$bucket}"], true);
            $this->runProcess(['mc', 'mb', "{$alias}/{$bucket}"]);
            return;
        }

        $list = $this->runProcess(['mc', 'ls', "{$alias}/{$bucket}"], true);

        if ($list->getExitCode() !== 0) {
            $this->info("Creating bucket '{$bucket}'.");
            $this->runProcess(['mc', 'mb', "{$alias}/{$bucket}"]);
        } else {
            $this->info("Bucket '{$bucket}' already exists.");
        }
    }

    private function ensurePolicy(string $alias, string $bucket, string $accessKey): void
    {
        $policyDefinition = json_encode([
            'Version' => '2012-10-17',
            'Statement' => [
                [
                    'Effect' => 'Allow',
                    'Action' => ['s3:*'],
                    'Resource' => [
                        "arn:aws:s3:::{$bucket}",
                        "arn:aws:s3:::{$bucket}/*",
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT);

        $policyPath = tempnam(sys_get_temp_dir(), 'minio-policy-');
        file_put_contents($policyPath, $policyDefinition);

        $policyName = "{$bucket}-policy";

        try {
            $this->runProcess(['mc', 'admin', 'policy', 'create', $alias, $policyName, $policyPath], true);
            $this->runProcess(['mc', 'admin', 'user', 'policy', 'set', $alias, $accessKey, $policyName], true);
        } finally {
            @unlink($policyPath);
        }
    }

    private function verifyStorage(string $alias, string $bucket): void
    {
        $this->info('Verifying bucket write/delete access...');

        $testFile = sprintf('laravel-minio-check-%s.txt', Str::uuid());
        $testPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $testFile;
        file_put_contents($testPath, 'MinIO connectivity check from artisan command.');

        try {
            $this->runProcess(['mc', 'cp', $testPath, "{$alias}/{$bucket}/{$testFile}"]);
            $this->runProcess(['mc', 'rm', "{$alias}/{$bucket}/{$testFile}"]);
            $this->info('Verified: able to upload and delete objects.');
        } catch (ProcessFailedException $exception) {
            throw new ProcessFailedException($exception->getProcess());
        } finally {
            @unlink($testPath);
        }
    }

    private function commandExists(string $command): bool
    {
        $process = Process::fromShellCommandline("command -v {$command}");
        $process->run();

        return $process->isSuccessful();
    }

    private function runProcess(array $command, bool $allowFailure = false): Process
    {
        $process = new Process($command);
        $process->run();

        if (! $process->isSuccessful() && ! $allowFailure) {
            throw new ProcessFailedException($process);
        }

        return $process;
    }

    private function runShell(string $command): void
    {
        $process = Process::fromShellCommandline($command);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }
}
