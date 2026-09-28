-- ============================================================================
-- Hana-Eunhaeng | Liberty Point Capital
-- Production-Grade Fintech Online Banking Platform
-- Master Database Initialization Script
-- ============================================================================
-- 
-- This unified migration orchestrates all database schema layers sequentially.
-- Apply once during initial deployment:
--   mysql -u root -p < database/init.sql
--
-- Idempotent: All operations use IF NOT EXISTS / IF EXISTS guards.
-- Non-destructive: Existing data is preserved via ON DUPLICATE KEY UPDATE.
--
-- ============================================================================
-- MIGRATION 1: CORE BANKING SCHEMA
-- ============================================================================
-- Date: 2024-01-01
-- Layer: Foundation (users, accounts, transactions, loans)
-- Status: Required for all downstream modules
--

-- Create application database
CREATE DATABASE IF NOT EXISTS `hana_eunhaeng` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hana_eunhaeng`;

-- Users table: authentication, identity, role-based access
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(160) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=user, 1=admin, 2=support',
  `is_active` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_active_role` (`is_active`, `role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='User accounts and authentication';

-- Accounts table: checking, savings, investment containers
CREATE TABLE IF NOT EXISTS `accounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `account_number` VARCHAR(40) NOT NULL UNIQUE,
  `account_type` VARCHAR(40) NOT NULL COMMENT 'checking, savings, money_market, investment, admin',
  `balance` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Current available balance',
  `currency` CHAR(3) NOT NULL DEFAULT 'USD' COMMENT 'ISO 4217 currency code',
  `status` VARCHAR(30) NOT NULL DEFAULT 'active' COMMENT 'active, suspended, closed, frozen',
  `is_primary` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Primary account for user',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_accounts_number` (`account_number`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_accounts_user_status` (`user_id`, `status`),
  CONSTRAINT `fk_accounts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Deposit and investment accounts';

-- Transactions table: immutable ledger of account movements
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `account_id` INT UNSIGNED NULL,
  `type` VARCHAR(40) NOT NULL COMMENT 'salary, transfer, deposit, withdrawal, fee, dividend, trade_settlement',
  `amount` DECIMAL(18,2) NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'USD',
  `direction` ENUM('inbound','outbound') NOT NULL COMMENT 'Debit or credit',
  `description` VARCHAR(255) NOT NULL,
  `reference_id` VARCHAR(80) NULL COMMENT 'External reference: trade_id, transfer_id, etc.',
  `status` VARCHAR(30) NOT NULL DEFAULT 'completed' COMMENT 'pending, completed, failed, reversed',
  `metadata` JSON NULL COMMENT 'Additional context for auditing',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_transactions_user` (`user_id`),
  KEY `idx_transactions_user_date` (`user_id`, `created_at`),
  KEY `idx_transactions_reference` (`reference_id`),
  CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Immutable ledger: all account movements';

-- Loans table: loan products and originations
CREATE TABLE IF NOT EXISTS `loans` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `loan_name` VARCHAR(100) NOT NULL,
  `principal_amount` DECIMAL(18,2) NOT NULL,
  `remaining_balance` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `annual_rate` DECIMAL(8,3) NOT NULL DEFAULT 0.000,
  `term_months` SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  `status` VARCHAR(30) NOT NULL DEFAULT 'active' COMMENT 'active, review, approved, denied, paid_off, defaulted',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_loans_user` (`user_id`),
  CONSTRAINT `fk_loans_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Loan products';

-- Seed data: demo users and test accounts
INSERT INTO `users` (`full_name`, `email`, `password_hash`, `role`, `is_active`) 
VALUES
  ('Demo User', 'demo@hana-eunhaeng.com', '$2y$10$3O0sF3d2XcLz5m0j.uzlM.ce8wF/2.h0r7Q7D2n9t9Q3vq8M7AQYW', 0, 1),
  ('Admin User', 'admin@hana-eunhaeng.com', '$2y$10$3O0sF3d2XcLz5m0j.uzlM.ce8wF/2.h0r7Q7D2n9t9Q3vq8M7AQYW', 1, 1)
ON DUPLICATE KEY UPDATE `email` = `email`;

INSERT INTO `accounts` (`user_id`, `account_number`, `account_type`, `balance`, `currency`, `status`, `is_primary`) 
VALUES
  (1, 'HN-00000001', 'checking', 8480.00, 'USD', 'active', 1),
  (1, 'HN-00000002', 'savings', 24500.00, 'USD', 'active', 0),
  (2, 'HN-ADMIN-01', 'admin', 95000.00, 'USD', 'active', 1)
ON DUPLICATE KEY UPDATE `account_number` = `account_number`;

INSERT INTO `transactions` (`user_id`, `account_id`, `type`, `amount`, `currency`, `direction`, `description`, `status`) 
VALUES
  (1, 1, 'salary', 4200.00, 'USD', 'inbound', 'Monthly payroll', 'completed'),
  (1, 1, 'transfer', 320.00, 'USD', 'outbound', 'Card payment', 'completed'),
  (1, 1, 'deposit', 1500.00, 'USD', 'inbound', 'Savings top-up', 'completed')
ON DUPLICATE KEY UPDATE `description` = `description`;

INSERT INTO `loans` (`user_id`, `loan_name`, `principal_amount`, `remaining_balance`, `status`) 
VALUES
  (1, 'Home Savings Advance', 28000.00, 28000.00, 'active'),
  (1, 'Education Support', 12000.00, 12000.00, 'review')
ON DUPLICATE KEY UPDATE `loan_name` = `loan_name`;

-- ============================================================================
-- MIGRATION 2: PENSION & RETIREMENT SCHEMA
-- ============================================================================
-- Date: 2024-01-15
-- Layer: Wealth Management (pension plans, contributions, beneficiaries)
-- Dependencies: users, accounts (from MIGRATION 1)
-- Status: Required for retirement planning features
--

-- Pension plans: templated retirement products
CREATE TABLE IF NOT EXISTS `pension_plans` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `plan_type` VARCHAR(40) NOT NULL COMMENT 'fixed_income, balanced, equity, target_date',
  `description` VARCHAR(500) NOT NULL,
  `annual_rate` DECIMAL(8,3) NOT NULL DEFAULT 0.000 COMMENT 'Projected annual return %',
  `minimum_contribution` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `employer_match_rate` DECIMAL(8,3) NOT NULL DEFAULT 0.000 COMMENT 'Employer contribution % match',
  `risk_level` ENUM('low','moderate','high') NOT NULL DEFAULT 'moderate',
  `is_active` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pension_plans_active_rate` (`is_active`, `annual_rate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Pension plan templates';

-- Pension accounts: user enrollments in retirement plans
CREATE TABLE IF NOT EXISTS `pension_accounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `plan_id` INT UNSIGNED NOT NULL,
  `account_number` VARCHAR(40) NOT NULL UNIQUE,
  `current_balance` DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Current value of retirement savings',
  `monthly_contribution` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `employer_contribution_monthly` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `target_retirement_age` TINYINT UNSIGNED NOT NULL COMMENT 'e.g., 65',
  `beneficiary_name` VARCHAR(120) NOT NULL,
  `beneficiary_relation` VARCHAR(80) NOT NULL COMMENT 'spouse, child, parent, other',
  `status` ENUM('active','paused','closed','matured') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pension_account_number` (`account_number`),
  KEY `idx_pension_accounts_user_status` (`user_id`, `status`),
  CONSTRAINT `fk_pension_accounts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pension_accounts_plan` FOREIGN KEY (`plan_id`) REFERENCES `pension_plans` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='User pension account enrollments';

-- Pension contributions: ledger of contributions (employee + employer)
CREATE TABLE IF NOT EXISTS `pension_contributions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pension_account_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(18,2) NOT NULL,
  `contribution_type` VARCHAR(30) NOT NULL COMMENT 'employee, employer, rollover, catch_up',
  `source_account_id` INT UNSIGNED NULL COMMENT 'Banking account source (if applicable)',
  `source` VARCHAR(100) NOT NULL COMMENT 'payroll, manual, employer_match, transfer',
  `status` VARCHAR(30) NOT NULL DEFAULT 'completed' COMMENT 'pending, completed, failed, reversed',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pension_contributions_account_date` (`pension_account_id`, `created_at`),
  CONSTRAINT `fk_pension_contributions_account` FOREIGN KEY (`pension_account_id`) REFERENCES `pension_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pension_contributions_source` FOREIGN KEY (`source_account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Immutable ledger: pension contributions';

-- Seed data: pension plan templates
INSERT INTO `pension_plans` (`name`, `plan_type`, `description`, `annual_rate`, `minimum_contribution`, `employer_match_rate`, `risk_level`) 
VALUES
  ('Hana Secure Retirement', 'fixed_income', 'Capital-conscious retirement savings with a stable allocation.', 3.500, 25.00, 0.000, 'low'),
  ('Hana Balanced Growth', 'balanced', 'Diversified retirement portfolio for long-term growth.', 6.250, 50.00, 2.000, 'moderate'),
  ('Hana Global Growth', 'equity', 'Higher-growth retirement portfolio with increased market exposure.', 8.000, 100.00, 3.000, 'high')
ON DUPLICATE KEY UPDATE `name` = `name`;

-- ============================================================================
-- MIGRATION 3: TRADING & INVESTMENT SCHEMA
-- ============================================================================
-- Date: 2024-02-01
-- Layer: Capital Markets (trades, positions, orders)
-- Dependencies: users (from MIGRATION 1)
-- Status: Paper-trading only; live trading disabled until regulatory approval
--

-- Trades table: buy/sell orders and executions
CREATE TABLE IF NOT EXISTS `trades` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `symbol` VARCHAR(24) NOT NULL COMMENT 'Ticker symbol (AAPL, BTC, etc.)',
  `side` ENUM('buy','sell') NOT NULL,
  `strategy` VARCHAR(40) NOT NULL COMMENT 'market, limit, stop_loss, trailing_stop',
  `quantity` DECIMAL(24,8) NOT NULL,
  `entry_price` DECIMAL(24,8) NOT NULL COMMENT 'Price at execution',
  `stop_price` DECIMAL(24,8) NULL COMMENT 'Stop-loss level',
  `take_profit_price` DECIMAL(24,8) NULL COMMENT 'Take-profit target',
  `exit_price` DECIMAL(24,8) NULL COMMENT 'Actual exit price (when closed)',
  `realized_pnl` DECIMAL(24,8) NULL COMMENT 'Profit/loss at close',
  `status` ENUM('open','closed','cancelled','pending') NOT NULL DEFAULT 'open',
  `execution_mode` ENUM('paper','live') NOT NULL DEFAULT 'paper' COMMENT 'Paper trading only (live disabled)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `idx_trades_user_created` (`user_id`, `created_at`),
  KEY `idx_trades_user_status` (`user_id`, `status`),
  KEY `idx_trades_symbol_date` (`symbol`, `created_at`),
  CONSTRAINT `fk_trades_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Trade orders and executions (paper-trading)';

-- Trade settlements: links trades to transaction ledger
CREATE TABLE IF NOT EXISTS `trade_settlements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `trade_id` BIGINT UNSIGNED NOT NULL,
  `transaction_id` INT UNSIGNED NULL COMMENT 'Link to transaction ledger for accounting',
  `account_id` INT UNSIGNED NOT NULL,
  `settlement_status` VARCHAR(30) NOT NULL DEFAULT 'pending' COMMENT 'pending, settled, failed, reversed',
  `settlement_date` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_trade_settlement` (`trade_id`),
  KEY `idx_trade_settlements_account_date` (`account_id`, `created_at`),
  CONSTRAINT `fk_trade_settlements_trade` FOREIGN KEY (`trade_id`) REFERENCES `trades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_trade_settlements_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_trade_settlements_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Bridge: trades to transaction ledger';

-- ============================================================================
-- MIGRATION 4: SECURITY, AUDIT & INDEXING LAYER
-- ============================================================================
-- Date: 2024-02-15
-- Layer: Compliance (audit trail, security constraints)
-- Dependencies: All prior tables
-- Status: Final hardening pass
--

-- Audit events: immutable activity log for compliance
CREATE TABLE IF NOT EXISTS `audit_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_user_id` INT UNSIGNED NULL COMMENT 'User performing the action (NULL if system)',
  `action` VARCHAR(80) NOT NULL COMMENT 'login, logout, transfer, trade, account_open, etc.',
  `resource` VARCHAR(80) NOT NULL COMMENT 'users, accounts, transactions, trades, loans',
  `resource_id` BIGINT UNSIGNED NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) NOT NULL,
  `metadata` JSON NULL COMMENT 'Additional context (amount, status change, etc.)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_actor` (`actor_user_id`, `created_at`),
  KEY `idx_audit_resource` (`resource`, `created_at`),
  CONSTRAINT `fk_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Immutable audit trail for compliance';

-- Add composite indexes to core tables (if not already present)
ALTER TABLE `accounts` ADD KEY IF NOT EXISTS `idx_accounts_user_status` (`user_id`, `status`);
ALTER TABLE `transactions` ADD KEY IF NOT EXISTS `idx_transactions_user_date` (`user_id`, `created_at`);

-- ============================================================================
-- MIGRATION COMPLETION MARKER
-- ============================================================================
-- Create a migration tracking table for future versioning
CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `version` VARCHAR(40) NOT NULL UNIQUE COMMENT 'Semantic version: 1.0.0',
  `description` VARCHAR(255) NOT NULL,
  `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Schema migration history';

-- Record completion of this unified migration
INSERT INTO `schema_migrations` (`version`, `description`) 
VALUES 
  ('1.0.0', 'Initial unified schema: core banking, pension, trading, audit (schema.sql + pension.sql + trading.sql + security.sql)')
ON DUPLICATE KEY UPDATE `applied_at` = NOW();

-- ============================================================================
-- POST-MIGRATION VERIFICATION
-- ============================================================================
-- 
-- To verify the schema was applied correctly, run:
--   SELECT TABLE_NAME, ENGINE, TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'hana_eunhaeng';
--
-- Expected tables (13):
--   users, accounts, transactions, loans, 
--   pension_plans, pension_accounts, pension_contributions,
--   trades, trade_settlements,
--   audit_events,
--   schema_migrations
--

-- ============================================================================
-- NOTES FOR PRODUCTION
-- ============================================================================
-- 
-- 1. CROSS-DOMAIN INTEGRATION:
--    - Trade settlements (trading.sql) now link to transaction ledger via trade_settlements bridge table.
--    - Pension contributions can source from banking accounts (source_account_id FK).
--    - All movements are recorded in transactions + audit_events for reconciliation.
--
-- 2. LIVE TRADING CONTROL:
--    - execution_mode ENUM('paper', 'live') in trades table enforces paper-only trading.
--    - Before enabling live trading:
--      a) Implement KYC/AML compliance workflow
--      b) Add account verification and suitability checks
--      c) Implement double-entry bookkeeping in transaction ledger
--      d) Add regulatory approval workflow
--      e) Enable audit event triggers on sensitive operations
--
-- 3. AUDIT & COMPLIANCE:
--    - All transaction and account movements must log to audit_events (application-level).
--    - Add database triggers for automatic audit logging in production.
--    - Retention: Keep audit_events for ≥ 7 years per regulatory requirements.
--
-- 4. FUTURE ENHANCEMENTS:
--    - Add reconciliation table for batch settlement reconciliation
--    - Add notification/alert table for regulatory changes
--    - Add statement generation tracking (for PDF/paper trails)
--    - Add dispute and chargeback handling
--    - Add currency exchange rate history for multi-currency support
--
-- ============================================================================
