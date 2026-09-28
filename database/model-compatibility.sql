-- ============================================================================
-- Hana-Eunhaeng | Legacy Schema Compatibility Migration
-- ============================================================================
-- Use this only when upgrading a database created from the original root-level
-- schema.sql, pension.sql, trading.sql, and security.sql files.
--
-- New installations should use database/init.sql instead:
--   mysql -u root -p < database/init.sql
--
-- This migration brings legacy tables up to the column contract used by the
-- current PHP models: AccountModel, TransactionModel, LoanModel, PensionModel,
-- and TradeModel.
--
-- Requires MySQL 8.0.29+ for ADD COLUMN/INDEX IF NOT EXISTS.
-- Back up the database before applying it.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `hana_eunhaeng` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hana_eunhaeng`;

-- ---------------------------------------------------------------------------
-- Core model compatibility
-- ---------------------------------------------------------------------------

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  ADD INDEX IF NOT EXISTS `idx_users_active_role` (`is_active`, `role`);

ALTER TABLE `accounts`
  ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  ADD INDEX IF NOT EXISTS `idx_accounts_user_status` (`user_id`, `status`);

ALTER TABLE `transactions`
  ADD COLUMN IF NOT EXISTS `reference_id` VARCHAR(80) NULL COMMENT 'External reference: trade_id, transfer_id, etc.',
  ADD COLUMN IF NOT EXISTS `metadata` JSON NULL COMMENT 'Additional context for auditing',
  ADD INDEX IF NOT EXISTS `idx_transactions_user_date` (`user_id`, `created_at`),
  ADD INDEX IF NOT EXISTS `idx_transactions_reference` (`reference_id`);

ALTER TABLE `loans`
  ADD COLUMN IF NOT EXISTS `remaining_balance` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS `annual_rate` DECIMAL(8,3) NOT NULL DEFAULT 0.000,
  ADD COLUMN IF NOT EXISTS `term_months` SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Existing loans created before remaining_balance was introduced should start
-- with their principal as the remaining balance, not zero.
UPDATE `loans`
SET `remaining_balance` = `principal_amount`
WHERE `remaining_balance` = 0 AND `status` NOT IN ('paid_off', 'closed');

-- ---------------------------------------------------------------------------
-- Pension model compatibility
-- ---------------------------------------------------------------------------

ALTER TABLE `pension_plans`
  ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `pension_accounts`
  ADD COLUMN IF NOT EXISTS `employer_contribution_monthly` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `pension_contributions`
  ADD COLUMN IF NOT EXISTS `source_account_id` INT UNSIGNED NULL COMMENT 'Banking account source (if applicable)',
  ADD INDEX IF NOT EXISTS `idx_pension_contributions_account_date` (`pension_account_id`, `created_at`);

ALTER TABLE `pension_contributions`
  ADD CONSTRAINT `fk_pension_contributions_source`
  FOREIGN KEY (`source_account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL;

-- ---------------------------------------------------------------------------
-- Trading model compatibility and settlement bridge
-- ---------------------------------------------------------------------------

ALTER TABLE `trades`
  ADD COLUMN IF NOT EXISTS `exit_price` DECIMAL(24,8) NULL,
  ADD COLUMN IF NOT EXISTS `realized_pnl` DECIMAL(24,8) NULL;

CREATE TABLE IF NOT EXISTS `trade_settlements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `trade_id` BIGINT UNSIGNED NOT NULL,
  `transaction_id` INT UNSIGNED NULL,
  `account_id` INT UNSIGNED NOT NULL,
  `settlement_status` VARCHAR(30) NOT NULL DEFAULT 'pending',
  `settlement_date` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_trade_settlement` (`trade_id`),
  KEY `idx_trade_settlements_account_date` (`account_id`, `created_at`),
  CONSTRAINT `fk_trade_settlements_trade` FOREIGN KEY (`trade_id`) REFERENCES `trades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_trade_settlements_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_trade_settlements_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Migration tracking
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `version` VARCHAR(40) NOT NULL UNIQUE,
  `description` VARCHAR(255) NOT NULL,
  `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `schema_migrations` (`version`, `description`)
VALUES ('1.0.1', 'Align legacy schema with current PHP model contracts')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`), `applied_at` = CURRENT_TIMESTAMP;
