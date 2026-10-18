<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

class History {
    public static function log(int $post_id, string $field_type, $old_value, $new_value, string $source = 'manual', ?int $user_id = null, ?string $execution_id = null, ?int $schedule_id = null, ?array $meta = null): int {
        global $wpdb;
        $table = Database::table_history();
        if ($user_id === null) $user_id = get_current_user_id() ?: null;
        if ($execution_id === null) $execution_id = apex_omega_generate_uuid();

        $delta = null;
        if (is_numeric($old_value) && is_numeric($new_value)) {
            $delta = $new_value - $old_value;
        }

        $data = [
            'post_id' => $post_id,
            'field_type' => $field_type,
            'old_value' => is_scalar($old_value) ? (string) $old_value : wp_json_encode($old_value, JSON_UNESCAPED_UNICODE),
            'new_value' => is_scalar($new_value) ? (string) $new_value : wp_json_encode($new_value, JSON_UNESCAPED_UNICODE),
            'delta' => $delta !== null ? (string) $delta : null,
            'source' => $source,
            'user_id' => $user_id,
            'execution_id' => $execution_id,
            'schedule_id' => $schedule_id,
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT']), 0, 255) : null,
            'meta_json' => $meta ? wp_json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => current_time('mysql'),
            'created_at_gmt' => current_time('mysql', true),
        ];

        $wpdb->insert($table, $data);
        $id = (int) $wpdb->insert_id;

        // همچنین لاگ در پست متا برای دسترسی سریع (آخرین 5)
        $last_key = '_apex_history_last';
        $recent = get_post_meta($post_id, $last_key, true);
        if (!is_array($recent)) $recent = [];
        array_unshift($recent, [
            'id' => $id,
            'field' => $field_type,
            'old' => $old_value,
            'new' => $new_value,
            'source' => $source,
            'at' => current_time('mysql', true),
            'exec' => $execution_id,
        ]);
        $recent = array_slice($recent, 0, 5);
        update_post_meta($post_id, $last_key, $recent);

        /**
         * هوک برای شنوندگان خارجی
         */
        do_action('apex_omega_history_logged', $id, $data);

        return $id;
    }

    public static function get_for_post(int $post_id, int $limit = 50, int $offset = 0, ?string $field_type = null): array {
        global $wpdb;
        $table = Database::table_history();
        $where = $wpdb->prepare("post_id = %d", $post_id);
        if ($field_type) {
            $where .= $wpdb->prepare(" AND field_type = %s", $field_type);
        }
        $rows = $wpdb->get_results("SELECT * FROM $table WHERE $where ORDER BY created_at_gmt DESC LIMIT $limit OFFSET $offset", ARRAY_A);
        return $rows ?: [];
    }

    public static function get_all_recent(int $limit = 20): array {
        global $wpdb;
        $table = Database::table_history();
        return $wpdb->get_results("SELECT h.*, p.post_title FROM $table h LEFT JOIN {$wpdb->posts} p ON p.ID = h.post_id ORDER BY h.created_at_gmt DESC LIMIT $limit", ARRAY_A) ?: [];
    }

    public static function rollback(int $history_id): array {
        global $wpdb;
        $table = Database::table_history();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $history_id), ARRAY_A);
        if (!$row) return ['success' => false, 'message' => 'رکورد تاریخچه یافت نشد.'];

        $post_id = (int) $row['post_id'];
        if (!get_post($post_id)) return ['success' => false, 'message' => 'نوشته مرتبط یافت نشد.'];

        $field = $row['field_type'];
        $old_value = $row['old_value'];

        // قفل همزمانی
        $lock_key = "rollback_{$post_id}_{$field}";
        if (!Database::acquire_lock($lock_key, 30)) {
            return ['success' => false, 'message' => 'عملیات دیگری در حال اجراست؛ لطفاً چند ثانیه دیگر تلاش کنید.'];
        }

        try {
            $current = null;
            if ($field === 'views') {
                $current = Adapter_Views::get_views($post_id);
                $success = Adapter_Views::set_views($post_id, (int) $old_value, 'restore');
            } elseif (in_array($field, ['kksr_casts', 'kksr_ratings', 'kksr_avg', 'kksr_combined'], true)) {
                $stats = Adapter_KKSR::get_stats($post_id);
                $current = $stats;
                // برای combined، old_value یک JSON است
                if ($field === 'kksr_combined') {
                    $decoded = json_decode($old_value, true);
                    if (!$decoded) $decoded = json_decode($row['old_value'], true);
                    if (is_array($decoded) && isset($decoded['casts'])) {
                        $success = Adapter_KKSR::set_stats($post_id, (int)$decoded['casts'], (float)$decoded['ratings'], isset($decoded['avg']) ? (float)$decoded['avg'] : null, 'restore');
                        $old_value = $decoded;
                    } else {
                        Database::release_lock($lock_key);
                        return ['success' => false, 'message' => 'داده بازگردانی نامعتبر است.'];
                    }
                } else {
                    // تک فیلدی: برای سادگی avg را محاسبه می‌کنیم
                    if ($field === 'kksr_casts') {
                        // نیاز به ratings فعلی برای سازگاری
                        $success = Adapter_KKSR::set_stats($post_id, (int)$old_value, $stats['ratings'], null, 'restore');
                    } elseif ($field === 'kksr_ratings') {
                        $success = Adapter_KKSR::set_stats($post_id, $stats['casts'], (float)$old_value, null, 'restore');
                    } else { // avg
                        // avg به تنهایی قابل بازگردانی نیست بدون casts/ratings؛ هشدار
                        Database::release_lock($lock_key);
                        return ['success' => false, 'message' => 'بازگردانی میانگین به‌تنهایی ممکن نیست؛ لطفاً combined را بازگردانی کنید.'];
                    }
                }
            } else {
                Database::release_lock($lock_key);
                return ['success' => false, 'message' => 'نوع فیلد ناشناخته است.'];
            }

            if (!$success) {
                Database::release_lock($lock_key);
                return ['success' => false, 'message' => 'خطا در بازگردانی؛ عملیات پایگاه داده ناموفق بود.'];
            }

            $exec = apex_omega_generate_uuid();
            self::log($post_id, $field, $current['casts'] ?? $current, $old_value, 'restore', get_current_user_id(), $exec, null, ['rollback_of' => $history_id]);

            Database::release_lock($lock_key);
            return ['success' => true, 'message' => 'بازگردانی با موفقیت انجام شد.', 'execution_id' => $exec];
        } catch (\Throwable $e) {
            Database::release_lock($lock_key);
            return ['success' => false, 'message' => 'خطای داخلی: ' . $e->getMessage()];
        }
    }

    public static function stats_summary(): array {
        global $wpdb;
        $h = Database::table_history();
        $s = Database::table_schedules();
        $total_changes = (int) $wpdb->get_var("SELECT COUNT(*) FROM $h");
        $today = gmdate('Y-m-d');
        $today_changes = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $h WHERE DATE(created_at_gmt) = %s", $today));
        $active_schedules = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $s WHERE status = %s", 'active'));
        $failed = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $h WHERE source = %s", 'error'));
        return [
            'total_changes' => $total_changes,
            'today_changes' => $today_changes,
            'active_schedules' => $active_schedules,
            'failed' => $failed,
        ];
    }
}
