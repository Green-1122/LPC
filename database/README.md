# Liberty Point Capital database migrations

## New installation

Use the unified schema. It creates the tables expected by the current PHP models and adds the trade settlement bridge:

```bash
mysql -u root -p < database/init.sql
```

The runtime model-to-table contracts are:

- `UserModel` → `users`
- `AccountModel` → `accounts`
- `TransactionModel` → `transactions`
- `LoanModel` → `loans`
- `PensionModel` → `pension_plans`, `pension_accounts`, `pension_contributions`
- `TradeModel` → `trades`

## Existing installation

If the database was created with the original root-level SQL files, back it up first and run the compatibility migration:

```bash
mysqldump -u root -p hana_eunhaeng > hana_eunhaeng-before-model-alignment.sql
mysql -u root -p hana_eunhaeng < database/model-compatibility.sql
```

The compatibility migration adds the newer columns used by the PHP models, creates the trade-to-transaction settlement bridge, and records version `1.0.1` in `schema_migrations`.

## Important

`database/init.sql` is the source of truth for fresh installs. Do not apply `schema.sql`, `pension.sql`, `trading.sql`, and `security.sql` after `init.sql`; they are the pre-unification source files and can introduce duplicate indexes or schema drift.

The migration uses MySQL 8.0.29+ `IF NOT EXISTS` support for columns and indexes. Review foreign-key names and take a backup before applying it to an existing production database.
