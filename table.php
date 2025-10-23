<?php
$randomString = bin2hex(random_bytes(3));
require_once 'config.php';

$connect = new mysqli($botDbHost, $botDbUser, $botDbPass, $botDbName);
if ($connect->connect_error) {
    file_put_contents("$randomString.txt", date('Y-m-d H:i:s') . " - Bot DB connection failed: " . $connect->connect_error . "\n", FILE_APPEND);
    exit(1);
}
$connect->set_charset("utf8");

function addFieldToTable($connect, $tableName, $columnName, $defaultValue, $columnType) {
    $result = $connect->query("SHOW COLUMNS FROM `$tableName` LIKE '$columnName'");
    if ($result->num_rows == 0) {
        $query = "ALTER TABLE `$tableName` ADD `$columnName` $columnType DEFAULT '$defaultValue'";
        if (!$connect->query($query)) {
            echo "Error adding column '$columnName' to table '$tableName': " . $connect->error . "\n";
            return false;
        }
        echo "Column '$columnName' added to table '$tableName' ✅\n";
    }
    return true;
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'admin_settings'");
    $table_exists = ($result->num_rows > 0);
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE admin_settings (
            admin_id INT NOT NULL,
            total_traffic BIGINT DEFAULT NULL,
            used_traffic BIGINT DEFAULT 0,
            expiry_date DATE DEFAULT NULL,
            status JSON,
            user_limit BIGINT DEFAULT NULL,
            hashed_password_before VARCHAR(255) DEFAULT NULL,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_expiry_notification TIMESTAMP NULL DEFAULT NULL,
            last_traffic_notification INT DEFAULT NULL,
            last_traffic_notify INT DEFAULT NULL,
            calculate_volume VARCHAR(50) DEFAULT 'used_traffic',
            PRIMARY KEY (admin_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");
        if (!$result) {
            echo "table admin_settings: " . $connect->error . "\n";
        }
        $connect->query("
            DROP TRIGGER IF EXISTS set_default_status;
            CREATE TRIGGER set_default_status
            BEFORE INSERT ON admin_settings
            FOR EACH ROW
            BEGIN
                IF NEW.status IS NULL THEN
                    SET NEW.status = '{\"data\": \"active\", \"time\": \"active\", \"users\": \"active\"}';
                END IF;
            END;
        ");
    } else {
        addFieldToTable($connect, 'admin_settings', 'hashed_password_before', 'NULL', 'VARCHAR(255)');
        addFieldToTable($connect, 'admin_settings', 'last_expiry_notification', 'NULL', 'TIMESTAMP NULL');
        addFieldToTable($connect, 'admin_settings', 'last_traffic_notification', 'NULL', 'INT');
        addFieldToTable($connect, 'admin_settings', 'last_traffic_notify', 'NULL', 'INT');
        addFieldToTable($connect, 'admin_settings', 'used_traffic', '0', 'BIGINT');
        addFieldToTable($connect, 'admin_settings', 'calculate_volume', 'used_traffic', 'VARCHAR(50)');

        $columnStatusType = $connect->query("SHOW COLUMNS FROM `admin_settings` LIKE 'status'")->fetch_assoc();
        if ($columnStatusType && strpos($columnStatusType['Type'], 'json') === false) {
            $connect->query("UPDATE `admin_settings` SET `status` = NULL WHERE `status` IS NOT NULL AND JSON_VALID(`status`) = 0");
            $connect->query("ALTER TABLE `admin_settings` MODIFY `status` JSON");
            $connect->query("UPDATE `admin_settings` SET `status` = '{\"data\": \"active\", \"time\": \"active\", \"users\": \"active\"}' WHERE `status` IS NULL");
        }
    }
} catch (Exception $e) {
    file_put_contents("$randomString.txt", $e->getMessage());
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'user_states'");
    $table_exists = ($result->num_rows > 0);
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE user_states (
            user_id BIGINT NOT NULL,
            username VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            lang VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
            state VARCHAR(50) DEFAULT NULL,
            admin_id INT DEFAULT NULL,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            data TEXT,
            message_id INT DEFAULT NULL,
            template_index INT DEFAULT 0,
            PRIMARY KEY (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        if (!$result) {
            echo "table user_states: " . $connect->error . "\n";
        }
    } else {
        addFieldToTable($connect, 'user_states', 'username', 'NULL', 'VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
        addFieldToTable($connect, 'user_states', 'lang', 'NULL', 'VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
        addFieldToTable($connect, 'user_states', 'state', 'NULL', 'VARCHAR(50)');
        addFieldToTable($connect, 'user_states', 'admin_id', 'NULL', 'INT');
        addFieldToTable($connect, 'user_states', 'updated_at', 'NULL', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        addFieldToTable($connect, 'user_states', 'data', 'NULL', 'TEXT');
        addFieldToTable($connect, 'user_states', 'message_id', 'NULL', 'INT');
        addFieldToTable($connect, 'user_states', 'template_index', '0', 'INT');
    }
} catch (Exception $e) {
    file_put_contents("$randomString.txt", $e->getMessage());
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'user_temporaries'");
    $table_exists = ($result->num_rows > 0);
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE user_temporaries (
            user_id BIGINT NOT NULL,
            user_key VARCHAR(50) NOT NULL,
            value TEXT,
            PRIMARY KEY (user_id, user_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        if (!$result) {
            echo "table user_temporaries: " . $connect->error . "\n";
        }
    } else {
        addFieldToTable($connect, 'user_temporaries', 'value', 'NULL', 'TEXT');
        $connect->query("ALTER TABLE `user_temporaries` MODIFY `user_id` BIGINT NOT NULL");
    }
} catch (Exception $e) {
    file_put_contents("$randomString.txt", $e->getMessage());
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'admin_usage'");
    $table_exists = ($result->num_rows > 0);
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE admin_usage (
            id BIGINT NOT NULL AUTO_INCREMENT,
            admin_id INT NOT NULL,
            used_traffic_gb DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if (!$result) {
            echo "table admin_usage: " . $connect->error . "\n";
        }
    }
} catch (Exception $e) {
    file_put_contents("$randomString.txt", $e->getMessage());
}

try {
    $result = $connect->query("SHOW TABLES LIKE 'marzhelp_limits'");
    $table_exists = ($result->num_rows > 0);
    if (!$table_exists) {
        $result = $connect->query("CREATE TABLE marzhelp_limits (
            id INT NOT NULL AUTO_INCREMENT,
            type ENUM('exclude','dedicated') COLLATE utf8mb4_unicode_ci NOT NULL,
            admin_id INT NOT NULL,
            inbound_tag VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY unique_limit (type, admin_id, inbound_tag),
            KEY admin_id (admin_id),
            CONSTRAINT marzhelp_limits_ibfk_1 FOREIGN KEY (admin_id) REFERENCES admins (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if (!$result) {
            echo "table marzhelp_limits: " . $connect->error . "\n";
        }
    }
} catch (Exception $e) {
    file_put_contents("$randomString.txt", $e->getMessage());
}

$connect->close();
?>