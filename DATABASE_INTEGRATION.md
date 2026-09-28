<?php

/**
 * Database/Schema Alignment & Integration Guide
 * ==============================================
 *
 * This document describes the data flow and relationships between the unified
 * schema (database/init.sql) and the PHP domain models.
 *
 * ## Core Model-to-Table Mappings
 *
 * | Model | Primary Table(s) | Key Methods |
 * |-------|------------------|-------------|
 * | UserModel | users | findByEmail, create, getSummary, listAll, setActive |
 * | AccountModel | accounts | getByUser, getPrimary, findOwned, create, adjustBalance |
 * | TransactionModel | transactions | recentByUser, create |
 * | LoanModel | loans | recentByUser, create, updateStatus |
 * | PensionModel | pension_plans, pension_accounts, pension_contributions | plans, accountsByUser, contributionsByUser, createAccount, contribute |
 * | TradeModel | trades, trade_settlements | listByUser, createPaperTrade, closeOwnedTrade |
 * | AuditModel | audit_events | log, byUser, byResource |
 *
 * ## Cross-Domain Integrations
 *
 * ### 1. Pension Contributions → Banking Transactions
 *
 * When a user funds a pension account from a bank account:
 *
 * ```php
 * // In PensionController::contribute()
 * $pensionModel->contribute(
 *     $userId,
 *     $pensionAccountId,
 *     $amount,
 *     'Monthly contribution',
 *     $sourceAccountId  // <-- New: links to bank account
 * );
 *
 * // Behind the scenes (PensionModel::contribute):
 * // 1. Checks pension_accounts.status = 'active'
 * // 2. Debits accounts.balance (with row-level FOR UPDATE lock)
 * // 3. Creates transaction (type='pension_contribution', direction='outbound')
 * // 4. Updates pension_accounts.current_balance += amount
 * // 5. Inserts pension_contributions with source_account_id FK
 * // 6. Links transaction via reference_id='pension:' + accountId
 * ```
 *
 * **Database Flow:**
 * - `accounts` → balance -= amount (locked)
 * - `transactions` → INSERT with reference_id='pension:XXX'
 * - `pension_contributions` → INSERT with source_account_id=YYY
 * - `pension_accounts` → current_balance += amount
 *
 * **Compliance:** Every pension funding is auditable through the transaction ledger.
 *
 * ### 2. Trade Settlement → Banking Transactions
 *
 * When a paper trade closes with an exit price:
 *
 * ```php
 * // In TradeController::close()
 * $tradeModel->closeOwnedTrade(
 *     $userId,
 *     $tradeId,
 *     $accountId,      // <-- Settlement account
 *     $exitPrice       // <-- Exit price for P&L calculation
 * );
 *
 * // Behind the scenes (TradeModel::closeOwnedTrade):
 * // 1. Locks trades row (FOR UPDATE)
 * // 2. Calculates realized_pnl = (exitPrice - entryPrice) * quantity
 * // 3. Updates trades (status='closed', exit_price, realized_pnl)
 * // 4. Creates transaction (type='trade_settlement', direction=inbound/outbound)
 * // 5. Creates trade_settlements bridge with settlement_status='settled'
 * // 6. Links transaction via reference_id='trade:' + tradeId
 * ```
 *
 * **Database Flow:**
 * - `trades` → status='closed', exit_price, realized_pnl
 * - `transactions` → INSERT with reference_id='trade:XXX', metadata={pnl, exitPrice}
 * - `trade_settlements` → INSERT linking trade to transaction
 *
 * **Compliance:** Trade lifecycle is immutable and fully auditable.
 *
 * ### 3. Audit Trail Integration
 *
 * All sensitive operations are logged to `audit_events`:
 *
 * ```php
 * use App\Helpers\Audit;
 *
 * // Login
 * Audit::logUserAction($userId, 'login', ['ip' => $_SERVER['REMOTE_ADDR']]);
 *
 * // Account adjustment
 * Audit::logAccountAction($userId, $accountId, 'balance_adjusted', ['amount' => 100, 'reason' => 'transfer']);
 *
 * // Transaction created
 * Audit::logTransactionAction($userId, $transactionId, 'transaction_created', ['amount' => 500]);
 * ```
 *
 * ## Data Integrity Patterns
 *
 * ### Atomicity
 *
 * Multi-step operations use database transactions:
 *
 * ```php
 * $this->db->beginTransaction();
 * try {
 *     // Step 1: Lock source account
 *     $source = $this->db->prepare('... FOR UPDATE')->execute(...);
 *
 *     // Step 2: Debit
 *     $this->db->prepare('UPDATE accounts SET balance = balance - ...')->execute(...);
 *
 *     // Step 3: Create transaction ledger entry
 *     $transactionId = $this->db->lastInsertId();
 *
 *     // Step 4: Update pension
 *     $this->db->prepare('UPDATE pension_accounts SET ...');
 *
 *     $this->db->commit();
 * } catch (\Throwable $e) {
 *     $this->db->rollBack();
 *     throw $e;
 * }
 * ```
 *
 * ### Referential Integrity
 *
 * Foreign keys enforce relationships:
 * - `pension_contributions.source_account_id` → `accounts.id` (ON DELETE SET NULL)
 * - `trade_settlements.transaction_id` → `transactions.id` (ON DELETE SET NULL)
 * - `trade_settlements.trade_id` → `trades.id` (ON DELETE CASCADE)
 *
 * ### Version Tracking
 *
 * Applied migrations are recorded in `schema_migrations`:
 *
 * ```sql
 * SELECT * FROM schema_migrations ORDER BY applied_at DESC;
 * ```
 *
 * ## Production Readiness Checklist
 *
 * - [ ] Database: `mysql -u app_user -p < database/init.sql`
 * - [ ] Verify all tables created: `SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='hana_eunhaeng';`
 * - [ ] Set up user permissions: `GRANT SELECT, INSERT, UPDATE ON hana_eunhaeng.* TO 'app_user'@'localhost';`
 * - [ ] Enable application-level audit logging in controllers
 * - [ ] Configure error handling to log to audit_events
 * - [ ] Test cross-domain flows (pension funding, trade settlement)
 * - [ ] Implement reconciliation reports querying transactions + audit_events
 * - [ ] Set up DB backups before any live data
 * - [ ] Document retention policy for audit_events (≥7 years for compliance)
 *
 * ## Future Enhancements
 *
 * 1. **Double-Entry Bookkeeping** - Add GL (general ledger) and mapping accounts → GL accounts
 * 2. **Statement Generation** - Query transactions, format as PDF/CSV, archive
 * 3. **Dispute Handling** - Add disputes table linking to transactions
 * 4. **Reconciliation** - Add reconciliation table for batch/batch matching
 * 5. **Real-Time Notifications** - Log to audit_events, subscribe via WebSocket
 * 6. **Multi-Currency** - Enhance transactions to support exchange rates in metadata
 * 7. **Regulatory Reporting** - Query audit_events + transactions for compliance exports
 * 8. **Loan Amortization** - Implement scheduled transaction creation for loan payments
 *
 */
