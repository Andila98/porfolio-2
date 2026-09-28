#!/usr/bin/env bash
# Nightly MySQL backup for the production Droplet (run from the repo directory).
# Keeps 14 days of gzipped dumps in ./backups. Optionally copy them off the
# Droplet afterwards (e.g. `s3cmd put` to DigitalOcean Spaces).
set -euo pipefail

cd "$(dirname "$0")/.."
set -a; source .env; set +a

mkdir -p backups
file="backups/portfolio-$(date +%Y%m%d-%H%M%S).sql.gz"
docker compose exec -T db mysqldump --single-transaction --triggers --routines \
    -uroot -p"${DB_ROOT_PASS}" "${DB_NAME:-portfolio}" | gzip > "$file"
find backups -name 'portfolio-*.sql.gz' -mtime +14 -delete
echo "backup written: $file"
