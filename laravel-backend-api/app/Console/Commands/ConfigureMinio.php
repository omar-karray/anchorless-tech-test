<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ConfigureMinio extends Command
{
    protected $signature = 'configure-minio {--force : Delete and recreate the bucket}';
    protected $description = 'Configure MinIO: create bucket and set policy';

    public function handle()
    {
        $endpoint = env('MINIO_ENDPOINT', 'http://minio:9000');
        $accessKey = env('MINIO_ACCESS_KEY_ID', 'sail');
        $secretKey = env('MINIO_SECRET_ACCESS_KEY', 'password');
        $bucket = env('MINIO_BUCKET', 'uploads');
        $alias = 'minio';

        $this->info('Configuring MinIO...');

        // Check if mc is installed
        if (!shell_exec('command -v mc')) {
            $this->info('Installing MinIO client (mc)...');
            shell_exec('curl -sSL https://dl.min.io/client/mc/release/linux-amd64/mc -o /usr/local/bin/mc && chmod +x /usr/local/bin/mc');
        }

        // Set mc alias
        shell_exec("mc alias set $alias $endpoint $accessKey $secretKey");

    // Handle --force: delete and recreate bucket
    if ($this->option('force')) {
      $this->info("--force specified: deleting bucket '$bucket' if it exists...");
      shell_exec("mc rb --force $alias/$bucket");
      $this->info("Recreating bucket: $bucket");
      shell_exec("mc mb $alias/$bucket");
    } else {
      // Create bucket if not exists
      $bucketExists = shell_exec("mc ls $alias/$bucket 2>/dev/null");
      if (!$bucketExists) {
        $this->info("Creating bucket: $bucket");
        shell_exec("mc mb $alias/$bucket");
      }
    }

        // Create policy file
        $policy = '{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": ["s3:*"],
      "Resource": ["arn:aws:s3:::' . $bucket . '/*", "arn:aws:s3:::' . $bucket . '"]
    }
  ]
}';
        file_put_contents('/tmp/minio-policy.json', $policy);

  // Create and set policy using the new command
  shell_exec("mc admin policy create $alias {$bucket}-policy /tmp/minio-policy.json || true");
  shell_exec("mc admin user policy set $alias $accessKey {$bucket}-policy");

        $this->info('MinIO bucket and policy setup complete.');
    }
}
