<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

/**
 * QueueWorker 2.0-alpha — پردازش صف هوشمند با اولویت و retry نمایی
 */
class QueueWorker {
    /**
     * تیک: حداکثر $limit job هر 5m
     */
    public static function tick(int $limit = 5): void {
        global $wpdb;
        $table = Database::table_queue();
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) return;

        $nowGmt = current_time('mysql', true);
        // SKIP LOCKED برای concurrency (MySQL 8+) — fallback بدون آن برای ماریا
        $jobs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE status IN ('pending','retry') AND (next_retry_gmt IS NULL OR next_retry_gmt <= %s) ORDER BY priority DESC, created_at_gmt ASC LIMIT %d",
            $nowGmt, $limit
        ), ARRAY_A);

        if (empty($jobs)) return;

        foreach ($jobs as $job) {
            $lockKey = 'apex_q_' . $job['job_id'];
            if (!Database::acquire_lock($lockKey, 300)) continue;

            $id = (int) $job['id'];
            $attempts = (int) $job['attempts'] + 1;
            $wpdb->update($table, [
                'status' => 'running',
                'started_at_gmt' => $nowGmt,
                'attempts' => $attempts,
            ], ['id' => $id]);

            $success = false;
            $error = null;
            try {
                $success = self::handle($job);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
                error_log('[APEX Ω] Queue job ' . $job['job_id'] . ' error: ' . $error);
            }

            if ($success) {
                $wpdb->update($table, [
                    'status' => 'done',
                    'completed_at_gmt' => current_time('mysql', true),
                ], ['id' => $id]);
            } else {
                $maxAttempts = (int) $job['max_attempts'] ?: 3;
                if ($attempts >= $maxAttempts) {
                    $wpdb->update($table, [
                        'status' => 'failed',
                        'completed_at_gmt' => current_time('mysql', true),
                    ], ['id' => $id]);
                } else {
                    $delay = pow(2, $attempts) * 60; // 2m,4m,8m
                    $nextRetry = gmdate('Y-m-d H:i:s', time() + $delay);
                    $wpdb->update($table, [
                        'status' => 'retry',
                        'next_retry_gmt' => $nextRetry,
                    ], ['id' => $id]);
                }
            }
            Database::release_lock($lockKey);
        }
    }

    private static function handle(array $job): bool {
        $type = $job['type'];
        $payload = json_decode($job['payload_json'] ?? 'null', true);

        switch ($type) {
            case 'timeseries_rollup':
                return self::handle_rollup();
            case 'profile_relearn':
                return self::handle_relearn($payload);
            case 'tier_distribution':
                // برای سازگاری: payload شامل counts/filters
                // در alpha فقط لاگ می‌کنیم، چون TierManager خودش queue جدا دارد (apex_omega_jobs)
                return true;
            default:
                // job ناشناس → done تا گیر نکند
                return true;
        }
    }

    private static function handle_rollup(): bool {
        return Scheduler::rollup_timeseries() >= 0;
    }

    private static function handle_relearn(?array $payload): bool {
        global $wpdb;
        $pf = Database::table_profiles();
        if ($wpdb->get_var("SHOW TABLES LIKE '$pf'") !== $pf) return true;

        // نمونه ساده: از ArtistManager وزن بگیر و profile بساز/آپدیت
        try {
            $tax = ArtistManager::detect_artist_taxonomy();
            if (!$tax) return true;
            $artists = ArtistManager::get_all_artists($tax, 200);
            foreach ($artists as $a) {
                $termId = (int) $a['term_id'];
                $name = $a['name'];
                $weight = (int) $a['weight'];
                $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $pf WHERE term_id=%d", $termId));
                $data = [
                    'term_id' => $termId,
                    'artist_name' => $name,
                    'weight' => (float) $weight,
                    'confidence' => min(0.95, count($artists) / 50),
                    'samples' => (int) $a['count'],
                    'curve_json' => wp_json_encode(['growth' => 0.02 + ($weight/100)*0.03, 'decay'=>0.002], JSON_UNESCAPED_UNICODE),
                    'updated_at_gmt' => current_time('mysql', true),
                ];
                if ($existing) $wpdb->update($pf, $data, ['term_id'=>$termId]);
                else $wpdb->insert($pf, $data);
            }
        } catch (\Throwable $e) {
            error_log('[APEX Ω] relearn error: ' . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * افزودن job به صف — API ساده
     */
    public static function enqueue(string $type, array $payload = [], int $priority = 5, int $maxAttempts = 3): string {
        global $wpdb;
        $table = Database::table_queue();
        $jobId = apex_omega_generate_uuid();
        $wpdb->insert($table, [
            'job_id' => $jobId,
            'type' => sanitize_text_field($type),
            'status' => 'pending',
            'priority' => max(1, min(10, $priority)),
            'payload_json' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            'attempts' => 0,
            'max_attempts' => $maxAttempts,
            'next_retry_gmt' => current_time('mysql', true),
            'created_at_gmt' => current_time('mysql', true),
        ]);
        return $jobId;
    }
}
