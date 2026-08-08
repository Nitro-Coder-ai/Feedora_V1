---
name: feedora-schema-migration
description: Design Feedora MySQL schema, write idempotent dated migrations in scripts/, and the includes/*Repository.php access layer. Use whenever a feature needs a new table/column/index, a migration script, or repository read/write methods. Also triggers on "add a field", "store X", "schema change", "migration", "index this", and on re-runs to refine an existing schema contract.
---

# Feedora Schema & Migration

Build the data layer for a feature: schema, migration, repository. You produce the **data contract** the backend builds on, so correctness of column names/types is paramount.

## Workflow
1. **Survey first.** Read `Feedora.sql` and any sibling tables for the feature area, plus the relevant `includes/*Repository.php`, to match naming/types and avoid duplicate columns. Check the existing PDO/mysqli connection style in `config/config.php`.
2. **Design tables.** UPPER_SNAKE table names, columns matching sibling conventions. Add a FK + index for every relationship and every column hit by a WHERE/ORDER BY (this app is read-heavy).
3. **Write the migration** to `scripts/YYYY_<feature>.sql` (current convention: dated prefix, e.g. `2026_<feature>.sql`). Make it **idempotent**: `CREATE TABLE IF NOT EXISTS`, guarded `ADD COLUMN`. Re-running must be safe.
4. **Write/extend the Repository** in `includes/<Thing>Repository.php` — prepared statements only, one class, methods returning explicit shapes. No raw SQL outside repositories.
5. **Emit the data contract** to `_workspace/01_schema_<feature>.md`: per table → columns + types + nullability + the Repository method that exposes them; plus repo method signatures and return shapes.
6. **Finalize:** only after the migration is stable, append the new objects to `Feedora.sql` (the canonical dump). Never rewrite `Feedora.sql` destructively.

## Why these rules
Idempotent migrations let the shared MySQL be re-synced without breaking. Repository-only SQL keeps prepared statements the single chokepoint, which is what blocks injection. The data contract exists because the #1 class of bug here is the backend reading a column the migration never created — an explicit contract makes that diffable by QA.

## Done when
Migration runs clean on a fresh and an already-migrated DB; every repository read maps to a real column; the contract file is written for the backend agent.
