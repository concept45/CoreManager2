ALTER TABLE `config_accounts` ADD COLUMN `JoinDate` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `TempPassword`;