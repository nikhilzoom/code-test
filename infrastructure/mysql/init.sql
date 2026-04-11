-- Fund Transfer System — database initialisation
-- Creates one database per service so each service owns its schema.

CREATE DATABASE IF NOT EXISTS `user_service`        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `account_service`     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `transaction_service` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `ledger_service`      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON `user_service`.*        TO 'root'@'%';
GRANT ALL PRIVILEGES ON `account_service`.*     TO 'root'@'%';
GRANT ALL PRIVILEGES ON `transaction_service`.* TO 'root'@'%';
GRANT ALL PRIVILEGES ON `ledger_service`.*      TO 'root'@'%';

FLUSH PRIVILEGES;
