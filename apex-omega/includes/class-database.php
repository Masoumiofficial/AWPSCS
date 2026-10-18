<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

class Database {
    const VERSION = '1.0.0';

    public static function table_history(): string {
        global $wpdb;
        return $wpdb->prefix . 'apex_omega_history';
    }

    public static function table_schedules(): string {
        global $wpdb;
        return $wpdb->prefix . 'apex_omega_schedules';
    }

    public static function table_locks(): string {
        global $wpdb;
        return $wpdb->prefix . 'apex_omega_locks';
    }

    public static function create_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $history = self::table_history();
        $sql1 = "CREATE TABLE $history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            field_type VARCHAR(32) NOT NULL,
            old_value LONGTEXT NULL,
            new_value LONGTEXT NULL,
            delta LONGTEXT NULL,
            source VARCHAR(32) NOT NULL DEFAULT 'manual',
            user_id BIGINT UNSIGNED NULL,
            execution_id VARCHAR(64) NULL,
            schedule_id BIGINT UNSIGNED NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            meta_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            created_at_gmt DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY field_type (field_type),
            KEY source (source),
            KEY created_at_gmt (created_at_gmt),
            KEY schedule_id (schedule_id)
        ) $charset;";

        $schedules = self::table_schedules();
        $sql2 = "CREATE TABLE $schedules (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            mode VARCHAR(32) NOT NULL DEFAULT 'scheduled',
            field_type VARCHAR(32) NOT NULL,
            current_value LONGTEXT NOT NULL,
            target_value LONGTEXT NOT NULL,
            min_allowed LONGTEXT NULL,
            max_allowed LONGTEXT NULL,
            max_step LONGTEXT NULL,
            max_percent_step DECIMAL(5,2) NULL,
            interval_seconds INT UNSIGNED NOT NULL DEFAULT 3600,
            start_at DATETIME NULL,
            start_at_gmt DATETIME NULL,
            end_at DATETIME NULL,
            end_at_gmt DATETIME NULL,
            last_run_at DATETIME NULL,
            last_run_at_gmt DATETIME NULL,
            next_run_at DATETIME NULL,
            next_run_at_gmt DATETIME NULL,
            last_value LONGTEXT NULL,
            require_confirm_large_drop TINYINT(1) NOT NULL DEFAULT 1,
            large_drop_threshold_percent DECIMAL(5,2) NOT NULL DEFAULT 20.00,
            large_drop_threshold_abs BIGINT NULL,
            execution_id VARCHAR(64) NULL,
            fail_count INT UNSIGNED NOT NULL DEFAULT 0,
            last_error LONGTEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            created_at_gmt DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            updated_at_gmt DATETIME NOT NULL,
            config_json LONGTEXT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY status (status),
            KEY field_type (field_type),
            KEY next_run_at_gmt (next_run_at_gmt),
            KEY execution_id (execution_id)
        ) $charset;";

        $locks = self::table_locks();
        $sql3 = "CREATE TABLE $locks (
            lock_key VARCHAR(191) NOT NULL,
            locked_at DATETIME NOT NULL,
            locked_at_gmt DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            expires_at_gmt DATETIME NOT NULL,
            owner VARCHAR(64) NOT NULL,
            PRIMARY KEY (lock_key),
            KEY expires_at_gmt (expires_at_gmt)
        ) $charset;";

        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);

        update_option('apex_omega_db_version', self::VERSION);
    }

    public static function drop_tables(bool $force = false): void {
        // فقط در uninstall با تأیید صریح
        if (!$force) return;
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS " . self::table_history());
        $wpdb->query("DROP TABLE IF EXISTS " . self::table_schedules());
        $wpdb->query("DROP TABLE IF EXISTS " . self::table_locks());
        delete_option('apex_omega_db_version');
    }

    /**
     * Acquire advisory lock using table_locks (DB-level, not transient) for concurrency safety
     */
    public static function acquire_lock(string $key, int $ttl_seconds = 120): bool {
        global $wpdb;
        $table = self::table_locks();
        $now = current_time('mysql');
        $now_gmt = current_time('mysql', true);
        $expires_gmt = gmdate('Y-m-d H:i:s', time() + $ttl_seconds);
        $expires = get_date_from_gmt($expires_gmt, 'Y-m-d H:i:s');
        $owner = apex_omega_generate_uuid();

        // پاکسازی قفل‌های منقضی
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE expires_at_gmt < %s", $now_gmt));

        // تلاش برای درج
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO $table (lock_key, locked_at, locked_at_gmt, expires_at, expires_at_gmt, owner) VALUES (%s,%s,%s,%s,%s,%s)",
            $key, $now, $now_gmt, $expires, $expires_gmt, $owner
        ));

        if ($result) {
            // ذخیره مالک برای رها سازی امن
            set_transient('apex_lock_' . md5($key), $owner, $ttl_seconds);
            return true;
        }
        return false;
    }

    public static function release_lock(string $key): void {
        global $wpdb;
        $table = self::table_locks();
        $owner = get_transient('apex_lock_' . md5($key));
        if ($owner) {
            $wpdb->delete($table, ['lock_key' => $key, 'owner' => $owner]);
            delete_transient('apex_lock_' . md5($key));
        } else {
            // fallback: حذف بدون بررسی مالک اگر منقضی شده
            $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE lock_key = %s", $key));
        }
    }

    public static function cleanup_expired_locks(): void {
        global $wpdb;
        $table = self::table_locks();
        $now_gmt = current_time('mysql', true);
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE expires_at_gmt < %s", $now_gmt));
    }
}
