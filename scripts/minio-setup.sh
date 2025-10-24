#!/bin/sh
# MinIO setup script: creates bucket and sets policy for Laravel

set -e

# Config from environment or defaults
MINIO_ALIAS="minio"
MINIO_ENDPOINT="${MINIO_ENDPOINT:-http://minio:9000}"
MINIO_ACCESS_KEY="${MINIO_ACCESS_KEY_ID:-sail}"
MINIO_SECRET_KEY="${MINIO_SECRET_ACCESS_KEY:-password}"
MINIO_BUCKET="${MINIO_BUCKET:-uploads}"

# Download mc if not present
if ! command -v mc >/dev/null 2>&1; then
  echo "Installing MinIO client (mc)..."
  curl -sSL https://dl.min.io/client/mc/release/linux-amd64/mc -o /usr/local/bin/mc
  chmod +x /usr/local/bin/mc
fi

# Configure mc alias
mc alias set "$MINIO_ALIAS" "$MINIO_ENDPOINT" "$MINIO_ACCESS_KEY" "$MINIO_SECRET_KEY"

# Create bucket if not exists
if ! mc ls "$MINIO_ALIAS/$MINIO_BUCKET" >/dev/null 2>&1; then
  echo "Creating bucket: $MINIO_BUCKET"
  mc mb "$MINIO_ALIAS/$MINIO_BUCKET"
fi

# Set full access policy for bucket
cat > /tmp/minio-policy.json <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": ["s3:*"],
      "Resource": ["arn:aws:s3:::$MINIO_BUCKET/*", "arn:aws:s3:::$MINIO_BUCKET"]
    }
  ]
}
EOF
mc admin policy add "$MINIO_ALIAS" "$MINIO_BUCKET-policy" /tmp/minio-policy.json || true
mc admin user policy set "$MINIO_ALIAS" "$MINIO_ACCESS_KEY" "$MINIO_BUCKET-policy"

echo "MinIO bucket and policy setup complete."
