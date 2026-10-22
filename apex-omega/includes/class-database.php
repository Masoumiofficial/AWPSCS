<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

class Database {
    const VERSION = '2.1.2'; // 2.1.2: fix comment distribution fair priority for novice پشمام + fast zero-fill + refined comments for >100k views without human intervention

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


    public static function table_jobs(): string {
        global $wpdb;
        return $wpdb->prefix . 'apex_omega_jobs';
    }


    public static function table_timeseries(): string {
        global $wpdb;
        return $wpdb->prefix . 'apex_omega_timeseries';
    }

    public static function table_profiles(): string {
        global $wpdb;
        return $wpdb->prefix . 'apex_omega_profiles';
    }

    public static function table_queue(): string {
        global $wpdb;
        return $wpdb->prefix . 'apex_omega_queue';
    }

    public static function table_snapshots(): string {
        global $wpdb;
        return $wpdb->prefix . 'apex_omega_snapshots';
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

        $jobs = self::table_jobs();
        $sql_jobs = "CREATE TABLE $jobs (\n            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n            execution_id VARCHAR(64) NOT NULL,\n            user_id BIGINT UNSIGNED NULL,\n            status VARCHAR(16) NOT NULL DEFAULT 'pending',\n            counts_json LONGTEXT NULL,\n            filters_json LONGTEXT NULL,\n            payload_json LONGTEXT NULL,\n            total INT UNSIGNED NOT NULL DEFAULT 0,\n            processed INT UNSIGNED NOT NULL DEFAULT 0,\n            chunk_size INT UNSIGNED NOT NULL DEFAULT 100,\n            last_error LONGTEXT NULL,\n            retry_count INT UNSIGNED NOT NULL DEFAULT 0,\n            created_at DATETIME NOT NULL,\n            created_at_gmt DATETIME NOT NULL,\n            updated_at DATETIME NOT NULL,\n            updated_at_gmt DATETIME NOT NULL,\n            completed_at DATETIME NULL,\n            completed_at_gmt DATETIME NULL,\n            PRIMARY KEY (id),\n            UNIQUE KEY execution_id (execution_id),\n            KEY status (status),\n            KEY created_at_gmt (created_at_gmt)\n        ) $charset;";

        $snaps = self::table_snapshots();
        $sql4 = "CREATE TABLE $snaps (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            execution_id VARCHAR(64) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            counts_json LONGTEXT NULL,
            filters_json LONGTEXT NULL,
            total_requested INT UNSIGNED NOT NULL DEFAULT 0,
            total_applied INT UNSIGNED NOT NULL DEFAULT 0,
            snapshot_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            created_at_gmt DATETIME NOT NULL,
            restored TINYINT(1) NOT NULL DEFAULT 0,
            restored_at DATETIME NULL,
            restored_at_gmt DATETIME NULL,
            is_complete TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY execution_id (execution_id),
            KEY created_at_gmt (created_at_gmt)
        ) $charset;";

        // --- 2.0-alpha tables ---
        $ts = self::table_timeseries();
        $sql_ts = "CREATE TABLE $ts (\n            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n            post_id BIGINT UNSIGNED NOT NULL,\n            metric VARCHAR(16) NOT NULL,\n            value DOUBLE NOT NULL,\n            sampled_at DATETIME NOT NULL,\n            sampled_at_gmt DATETIME NOT NULL,\n            source VARCHAR(32) NOT NULL DEFAULT 'evergreen',\n            granularity VARCHAR(8) NOT NULL DEFAULT '5m',\n            PRIMARY KEY (id),\n            KEY post_metric_time (post_id, metric, sampled_at_gmt),\n            KEY sampled_gmt (sampled_at_gmt)\n        ) $charset;";

        $pf = self::table_profiles();
        $sql_pf = "CREATE TABLE $pf (\n            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n            term_id BIGINT UNSIGNED NOT NULL,\n            artist_name VARCHAR(191) NOT NULL,\n            weight DOUBLE NOT NULL DEFAULT 50,\n            confidence DOUBLE NOT NULL DEFAULT 0.5,\n            samples INT UNSIGNED NOT NULL DEFAULT 0,\n            curve_json LONGTEXT NULL,\n            updated_at_gmt DATETIME NOT NULL,\n            PRIMARY KEY (id),\n            UNIQUE KEY term_id (term_id),\n            KEY confidence (confidence)\n        ) $charset;";

        $qu = self::table_queue();
        $sql_qu = "CREATE TABLE $qu (\n            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n            job_id VARCHAR(64) NOT NULL,\n            type VARCHAR(32) NOT NULL,\n            status VARCHAR(16) NOT NULL DEFAULT 'pending',\n            priority TINYINT NOT NULL DEFAULT 5,\n            payload_json LONGTEXT NULL,\n            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,\n            max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,\n            next_retry_gmt DATETIME NULL,\n            created_at_gmt DATETIME NOT NULL,\n            started_at_gmt DATETIME NULL,\n            completed_at_gmt DATETIME NULL,\n            PRIMARY KEY (id),\n            UNIQUE KEY job_id (job_id),\n            KEY status_prio (status, priority, next_retry_gmt)\n        ) $charset;";

        dbDelta($sql_ts);
        dbDelta($sql_pf);
        dbDelta($sql_qu);

        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql_jobs);
        dbDelta($sql4);

        update_option('apex_omega_db_version', self::VERSION);
    }

    public static function drop_tables(bool $force = false): void {
        // فقط در uninstall با تأیید صریح
        if (!$force) return;
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS " . self::table_queue());
        $wpdb->query("DROP TABLE IF EXISTS " . self::table_profiles());
        $wpdb->query("DROP TABLE IF EXISTS " . self::table_timeseries());
        $wpdb->query("DROP TABLE IF EXISTS " . self::table_jobs());
        $wpdb->query("DROP TABLE IF EXISTS " . self::table_history());
        $wpdb->query("DROP TABLE IF EXISTS " . self::table_schedules());
        $wpdb->query("DROP TABLE IF EXISTS " . self::table_locks());
        $wpdb->query("DROP TABLE IF EXISTS " . self::table_snapshots());
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

        // تلاش برای درج — 1.9.4 JOB-02: INSERT IGNORE برای جلوگیری از race دو process همزمان (خطای PK را سایلنت کن)
        $result = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO $table (lock_key, locked_at, locked_at_gmt, expires_at, expires_at_gmt, owner) VALUES (%s,%s,%s,%s,%s,%s)",
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

    // Snapshot helpers
    public static function create_snapshot(string $exec_id, int $user_id, array $counts, array $filters, int $requested, int $applied, array $snapshot_data): int {
        global $wpdb;
        $table = self::table_snapshots();
        $now = current_time('mysql');
        $now_gmt = current_time('mysql', true);
        $wpdb->insert($table, [
            'execution_id' => $exec_id,
            'user_id' => $user_id ?: null,
            'counts_json' => wp_json_encode($counts, JSON_UNESCAPED_UNICODE),
            'filters_json' => wp_json_encode($filters, JSON_UNESCAPED_UNICODE),
            'total_requested' => $requested,
            'total_applied' => $applied,
            'snapshot_json' => wp_json_encode($snapshot_data, JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'created_at_gmt' => $now_gmt,
            'restored' => 0,
            'is_complete' => 1,
        ]);
        return (int) $wpdb->insert_id;
    }

    public static function get_last_snapshot(): ?array {
        global $wpdb;
        $table = self::table_snapshots();
        $row = $wpdb->get_row("SELECT * FROM $table WHERE restored=0 AND is_complete=1 ORDER BY id DESC LIMIT 1", ARRAY_A);
        return $row ?: null;
    }

    public static function get_snapshot_by_exec(string $exec_id): ?array {
        global $wpdb;
        $table = self::table_snapshots();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE execution_id=%s LIMIT 1", $exec_id), ARRAY_A);
        return $row ?: null;
    }

    public static function mark_snapshot_restored(int $id): void {
        global $wpdb;
        $table = self::table_snapshots();
        $wpdb->update($table, [
            'restored' => 1,
            'restored_at' => current_time('mysql'),
            'restored_at_gmt' => current_time('mysql', true),
        ], ['id' => $id]);
    }


    // ─── Job (chunked distribution) — 1.9.3 COR-07 ───
    public static function create_job(string $exec_id, int $user_id, array $counts, array $filters, array $payload, int $total, int $chunk_size = 100): int {
        global $wpdb;
        $table = self::table_jobs();
        $now = current_time('mysql');
        $now_gmt = current_time('mysql', true);
        $wpdb->insert($table, [
            'execution_id' => $exec_id,
            'user_id' => $user_id ?: null,
            'status' => 'pending',
            'counts_json' => wp_json_encode($counts, JSON_UNESCAPED_UNICODE),
            'filters_json' => wp_json_encode($filters, JSON_UNESCAPED_UNICODE),
            'payload_json' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            'total' => $total,
            'processed' => 0,
            'chunk_size' => max(10, min(200, $chunk_size)),
            'created_at' => $now,
            'created_at_gmt' => $now_gmt,
            'updated_at' => $now,
            'updated_at_gmt' => $now_gmt,
        ]);
        return (int) $wpdb->insert_id;
    }

    public static function get_job(string $exec_id): ?array {
        global $wpdb;
        $table = self::table_jobs();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE execution_id=%s LIMIT 1", $exec_id), ARRAY_A);
        return $row ?: null;
    }

    public static function update_job_progress(string $exec_id, int $processed, string $status = 'running', ?string $error = null): void {
        global $wpdb;
        $table = self::table_jobs();
        $now = current_time('mysql');
        $now_gmt = current_time('mysql', true);
        $data = [
            'processed' => $processed,
            'status' => $status,
            'updated_at' => $now,
            'updated_at_gmt' => $now_gmt,
        ];
        if ($error !== null) $data['last_error'] = $error;
        if ($status === 'done' || $status === 'failed') {
            $data['completed_at'] = $now;
            $data['completed_at_gmt'] = $now_gmt;
        }
        $wpdb->update($table, $data, ['execution_id' => $exec_id]);
    }

    public static function increment_job_retry(string $exec_id): void {
        global $wpdb;
        $table = self::table_jobs();
        $wpdb->query($wpdb->prepare("UPDATE $table SET retry_count = retry_count + 1, updated_at=%s, updated_at_gmt=%s WHERE execution_id=%s", current_time('mysql'), current_time('mysql', true), $exec_id));
    }

    public static function prune_history(int $older_than_days = 90): int {
        global $wpdb;
        $table = self::table_history();
        $cutoff = gmdate('Y-m-d H:i:s', time() - $older_than_days * DAY_IN_SECONDS);
        $deleted = $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE created_at_gmt < %s LIMIT 5000", $cutoff));
        return (int)$deleted;
    }

    // Single transaction per post: update views + kksr atomically
    public static function atomic_update_post_meta(int $post_id, array $meta_updates): bool {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        try {
            foreach ($meta_updates as $key => $value) {
                update_post_meta($post_id, $key, $value);
            }
            $wpdb->query('COMMIT');
            return true;
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            error_log('[APEX Ω] atomic_update failed: ' . $e->getMessage());
            return false;
        }
    }
}
