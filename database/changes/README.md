# Schema changes

`database/schema.sql` always describes the full, current schema.

When the schema changes after the site is live:

1. Update `schema.sql` so fresh installs get the new shape.
2. Add a file here named `YYYY-MM-DD-short-description.sql` containing only the
   change (for example an `ALTER TABLE`).
3. Run it once, by hand, on each existing database:
   - XAMPP: phpMyAdmin → select `portfolio` → Import.
   - Production: `docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" portfolio < database/changes/<file>.sql`
