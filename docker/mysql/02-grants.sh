#!/bin/bash
# Runs once, on the MySQL container's first start (after 01-schema.sql).
# Creates the application user with the least privileges it needs.
# tip_ledger gets SELECT + INSERT only: the app can never rewrite the ledger.
set -euo pipefail

mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<SQL
CREATE USER IF NOT EXISTS '${APP_DB_USER}'@'%' IDENTIFIED BY '${APP_DB_PASS}';
GRANT SELECT, INSERT                 ON \`${MYSQL_DATABASE}\`.tip_ledger   TO '${APP_DB_USER}'@'%';
GRANT SELECT, INSERT, UPDATE         ON \`${MYSQL_DATABASE}\`.messages     TO '${APP_DB_USER}'@'%';
GRANT SELECT, INSERT                 ON \`${MYSQL_DATABASE}\`.cv_downloads TO '${APP_DB_USER}'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON \`${MYSQL_DATABASE}\`.rate_limits  TO '${APP_DB_USER}'@'%';
FLUSH PRIVILEGES;
SQL
