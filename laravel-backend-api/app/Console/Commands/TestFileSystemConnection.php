<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestFileSystemConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-fs-connection {--persist : Do not delete the test file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $disk = 'minio';
        $filename = 'test-connection.txt';
        $contents = 'MinIO connection test at ' . now();

        // Dump disk config for debug
        $config = config('filesystems.disks.' . $disk);
        $this->info('MinIO disk config:');
        foreach ($config as $key => $value) {
            $this->line("  $key: " . var_export($value, true));
        }


        try {
            $result = \Storage::disk($disk)->put($filename, $contents);
            $this->info("put() result: " . var_export($result, true));
            $this->info("File written to '$disk' disk: $filename");
        } catch (\Exception $e) {
            $this->error("Failed to write file: " . $e->getMessage());
            $this->error("Exception trace: " . $e->getTraceAsString());
            return 1;
        }

        // List files in the bucket for debug
        try {
            $files = \Storage::disk($disk)->files();
            $this->info("Files in '$disk' bucket:");
            foreach ($files as $file) {
                $this->line("- $file");
            }
        } catch (\Exception $e) {
            $this->error("Failed to list files: " . $e->getMessage());
            $this->error("Exception trace: " . $e->getTraceAsString());
        }

        try {
            $exists = \Storage::disk($disk)->exists($filename);
            $this->info("exists() result: " . var_export($exists, true));
            if ($exists) {
                $this->info("File exists on '$disk': $filename");
            } else {
                $this->error("File does NOT exist on '$disk': $filename");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("Failed to check file existence: " . $e->getMessage());
            $this->error("Exception trace: " . $e->getTraceAsString());
            return 1;
        }

        if (!$this->option('persist')) {
            try {
                \Storage::disk($disk)->delete($filename);
                $this->info("Test file deleted from '$disk': $filename");
            } catch (\Exception $e) {
                $this->error("Failed to delete test file: " . $e->getMessage());
                return 1;
            }
        } else {
            $this->info("--persist specified: test file will remain in '$disk': $filename");
        }

        $this->info('MinIO filesystem test completed successfully.');
        return 0;
    }
}
