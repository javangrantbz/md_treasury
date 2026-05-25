# TRS Database Migrations

This system manages database schema changes automatically. Instead of running manual SQL commands on the live server, create a migration file.

## How to use

### 1. Create a new migration
Add a new `.sql` file to `database/migrations/`. 
Use a numbered prefix and descriptive name, e.g., `003_add_index_to_expenses.sql`.

**Example:**
```sql
ALTER TABLE expense_entries ADD INDEX idx_status (status);
```

### 2. Run migrations
Run the following command from the project root:
```bash
php database/migrate.php
```

The runner will:
1. Detect any new files in `database/migrations/`.
2. Execute the SQL.
3. Record the filename in the `migrations` table so it never runs twice.

## Rules
- **Never modify a migration file after it has been pushed/applied.** If you need to change something, create a *new* migration.
- **Use `IF NOT EXISTS`** where possible to make migrations safe and idempotent.
- **No Transactions for DDL:** MySQL/MariaDB performs implicit commits on `ALTER`, `CREATE`, and `DROP` statements, so migrations containing these cannot be rolled back automatically.
