<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

class Scheduler {
    const CRON_HOOK = 'apex_omega_tick';
    const CRON_INTERVAL = 'apex_omega_five_min';

    const WEEKLY_HOOK = 'apex_omega_weekly_report';
    const SITE_HEALTH_HOOK = 'apex_omega_site_health';

    public static function init(): void {
        add_filter('cron_schedules', [__CLASS__, 'add_cron_schedules']);
        add_action(self::CRON_HOOK, [__CLASS__, 'tick']);
        add_action(self::COMMENT_HOOK, ['\\ApexOmega\\Core\\CommentGuardian', 'tick']);
        // پاکسازی قفل‌های منقضی روزانه
        add_action('apex_omega_cleanup', [Database::class, 'cleanup_expired_locks']);
        if (!wp_next_scheduled('apex_omega_cleanup')) {
            wp_schedule_event(time(), 'daily', 'apex_omega_cleanup');
        }
        // 1.9.4 JOB-05: پاکسازی تاریخچه قدیمی (90 روز) — روزانه
        // actual prune hook
        add_action('apex_omega_prune_history_daily', function(){ \ApexOmega\Core\Database::prune_history(90); });
        if (!wp_next_scheduled('apex_omega_prune_history_daily')) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'apex_omega_prune_history_daily');
        }
        // 2.0-alpha: rollup timeseries روزانه 02:00
        add_action('apex_omega_rollup_daily', function(){
            if (class_exists('\\ApexOmega\\Core\\QueueWorker')) {
                \ApexOmega\Core\QueueWorker::enqueue('timeseries_rollup', [], 2);
            } else {
                \ApexOmega\Core\Scheduler::rollup_timeseries();
            }
        });
        if (!wp_next_scheduled('apex_omega_rollup_daily')) {
            // فردا 02:00
            $tomorrow2am = strtotime('tomorrow 02:00:00');
            wp_schedule_event($tomorrow2am, 'daily', 'apex_omega_rollup_daily');
        }
        // گزارش هفتگی
        add_action(self::WEEKLY_HOOK, [__CLASS__, 'send_weekly_report']);
        if (!wp_next_scheduled(self::WEEKLY_HOOK)) {
            wp_schedule_event(time() + WEEK_IN_SECONDS, 'weekly', self::WEEKLY_HOOK);
        }
        // خودترمیم: اگر رویداد اصلی حذف یا تکی شده بود، دوباره بساز (مهم برای بعد از غیرفعال/فعال یا دستکاری WP Crontrol)
        add_action('init', [__CLASS__, 'maybe_heal_cron'], 20);
        add_action('admin_init', [__CLASS__, 'maybe_heal_cron'], 20);
        // Site Health
        add_filter('site_status_tests', [__CLASS__, 'register_site_health_tests']);
    }

    public static function maybe_heal_cron(): void {
        // در پیشخوان بدون تاخیر ترمیم کن؛ در فرانت حداکثر هر 5 دقیقه
        $is_admin = is_admin();
        $ttl = $is_admin ? 60 : 300;
        $last = get_transient('apex_omega_heal_last');
        if ($last && !$is_admin) return;
        if ($last && $is_admin && (time() - (int)get_option('_apex_heal_last_ts', 0) < 60)) return;
        set_transient('apex_omega_heal_last', 1, $ttl);
        update_option('_apex_heal_last_ts', time(), false);
        // فقط در پیشخوان یا هر init، سبک و idempotent
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            self::ensure_cron();
            return;
        }
        // اگر به‌اشتباه به‌صورت non-repeating ثبت شده (توسط WP Crontrol دستی)
        $sched = wp_get_schedule(self::CRON_HOOK);
        if ($sched !== self::CRON_INTERVAL) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
            self::ensure_cron();
        }
    }

    const COMMENT_INTERVAL = 'apex_omega_six_hours';
    const COMMENT_HOOK = 'apex_omega_commentor_tick';

    public static function add_cron_schedules(array $schedules): array {
        $schedules[self::CRON_INTERVAL] = [
            'interval' => 300,
            'display' => __('هر ۵ دقیقه — APEX Ω', 'apex-omega'),
        ];
        $schedules[self::COMMENT_INTERVAL] = [
            'interval' => 21600,
            'display' => __('هر ۶ ساعت — APEX Ω کامنت', 'apex-omega'),
        ];
        return $schedules;
    }

    public static function ensure_cron(): void {
        $ts = wp_next_scheduled(self::CRON_HOOK);
        if (!$ts) {
            wp_schedule_event(time() + 60, self::CRON_INTERVAL, self::CRON_HOOK);
        } else {
            $sched = wp_get_schedule(self::CRON_HOOK);
            if ($sched !== self::CRON_INTERVAL) {
                wp_clear_scheduled_hook(self::CRON_HOOK);
                wp_schedule_event(time() + 60, self::CRON_INTERVAL, self::CRON_HOOK);
            }
        }
        // نگهبان کامنت — هر ۶ ساعت (۴ بار/روز) همهٔ پست‌ها
        $ts_c = wp_next_scheduled(self::COMMENT_HOOK);
        if (!$ts_c) {
            wp_schedule_event(time() + 3600, self::COMMENT_INTERVAL, self::COMMENT_HOOK);
        } else {
            $sched_c = wp_get_schedule(self::COMMENT_HOOK);
            if ($sched_c !== self::COMMENT_INTERVAL) {
                wp_clear_scheduled_hook(self::COMMENT_HOOK);
                wp_schedule_event(time() + 3600, self::COMMENT_INTERVAL, self::COMMENT_HOOK);
            }
        }
    }

    public static function ajax_heal_cron(): void {
        // جلوگیری از نشت HTML قبل از JSON (Warningها)
        while (ob_get_level() > 0) { ob_end_clean(); }
        $nonce = sanitize_text_field($_REQUEST['nonce'] ?? $_REQUEST['_ajax_nonce'] ?? ''); // SEC-02
        $valid = wp_verify_nonce($nonce, 'apex-omega-nonce') || wp_verify_nonce($nonce, 'apex_omega_nonce') || wp_verify_nonce($nonce, 'apex-omega-nonce');
        // همچنین dash/underscore هر دو — سازگاری عقب‌رو
        if (!$valid || !$nonce) {
            wp_send_json_error(['msg'=>'نشانه امنیتی نامعتبر — صفحه را رفرش کنید (nonce).','code'=>'bad_nonce']);
        }
        if (!current_user_can('apex_manage_statistics')) {
            wp_send_json_error(['msg'=>'دسترسی غیرمجاز']);
        }
        self::ensure_cron();
        $next = wp_next_scheduled(self::CRON_HOOK);
        $next_c = wp_next_scheduled(self::COMMENT_HOOK);
        if ($next) {
            wp_send_json_success(['next'=> $next, 'next_comment'=> $next_c, 'human'=> get_date_from_gmt(gmdate('Y-m-d H:i:s', $next), 'Y-m-d H:i:s')]);
        }
        wp_send_json_error(['msg'=>'ترمیم ناموفق — Cron سرور را بررسی کنید.']);
    }

    public static function clear_cron(): void {
        $ts = wp_next_scheduled(self::CRON_HOOK);
        if ($ts) wp_unschedule_event($ts, self::CRON_HOOK);
        $ts2 = wp_next_scheduled('apex_omega_cleanup');
        if ($ts2) wp_unschedule_event($ts2, 'apex_omega_cleanup');
        $ts3 = wp_next_scheduled(self::WEEKLY_HOOK);
        if ($ts3) wp_unschedule_event($ts3, self::WEEKLY_HOOK);
        $ts4 = wp_next_scheduled('apex_omega_auto_tier_tick');
        if ($ts4) wp_unschedule_event($ts4, 'apex_omega_auto_tier_tick');
        $ts5 = wp_next_scheduled(self::COMMENT_HOOK);
        if ($ts5) wp_unschedule_event($ts5, self::COMMENT_HOOK);
        $ts6 = wp_next_scheduled('apex_omega_prune_history_daily');
        if ($ts6) wp_unschedule_event($ts6, 'apex_omega_prune_history_daily');
        $ts7 = wp_next_scheduled('apex_omega_rollup_daily');
        if ($ts7) wp_unschedule_event($ts7, 'apex_omega_rollup_daily');
    }

    public static function send_weekly_report(): void {
        $to = get_option('admin_email');
        $counts = History::stats_summary();
        $last = get_option(\ApexOmega\Core\TierManager::OPTION_LAST_DISTRIBUTION, []);
        $active = self::get_all_active(5);
        $subject = 'APEX Ω — گزارش هفتگی: ' . get_bloginfo('name');
        $body = "گزارش هفتگی APEX Ω\n";
        $body .= "تاریخ: " . current_time('mysql') . "\n";
        $body .= "کل تغییرات: {$counts['total_changes']} | امروز: {$counts['today_changes']} | فعال: {$counts['active_schedules']}\n";
        if (!empty($last)) {
            $body .= "آخرین توزیع: {$last['at']} — {$last['applied']} آهنگ — exec {$last['exec']}\n";
            $body .= "دسته‌ها: " . wp_json_encode($last['counts'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
        }
        if ($active) {
            $body .= "برنامه‌های فعال:\n";
            foreach ($active as $s) $body .= " - #{$s['id']} پست {$s['post_id']} فیلد {$s['field_type']} مقصد {$s['target_value']}\n";
        }
        $body .= "\nمدیریت: " . admin_url('admin.php?page=apex-omega') . "\n";
        wp_mail($to, $subject, $body);
    }

    public static function register_site_health_tests(array $tests): array {
        $tests['direct']['apex_omega_cron'] = [
            'label' => 'APEX Ω — زمان‌بندی',
            'test'  => [__CLASS__, 'site_health_cron'],
        ];
        $tests['direct']['apex_omega_tables'] = [
            'label' => 'APEX Ω — جداول دیتابیس',
            'test'  => [__CLASS__, 'site_health_tables'],
        ];
        return $tests;
    }
    public static function site_health_cron(): array {
        $next = wp_next_scheduled(self::CRON_HOOK);
        $status = $next ? 'good' : 'critical';
        return [
            'label' => $next ? 'کرون APEX Ω فعال است' : 'کرون APEX Ω غیرفعال است',
            'status' => $status,
            'badge' => ['label'=>'APEX Ω','color'=>'blue'],
            'description' => $next ? '<p>اجرای بعدی: '.esc_html(get_date_from_gmt(gmdate('Y-m-d H:i:s',$next),'Y-m-d H:i:s')).'</p>' : '<p>رویداد apex_omega_tick زمان‌بندی نشده. افزونه را غیرفعال/فعال کنید.</p>',
            'actions' => $next ? '' : '<a href="'.esc_url(admin_url('admin.php?page=apex-omega')).'">رفتن به داشبورد</a>',
            'test' => 'apex_omega_cron',
        ];
    }
    public static function site_health_tables(): array {
        global $wpdb;
        $h = Database::table_history();
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$h'") === $h;
        $status = $exists ? 'good' : 'critical';
        return [
            'label' => $exists ? 'جداول APEX Ω موجود است' : 'جدول تاریخچه APEX Ω یافت نشد',
            'status' => $status,
            'badge' => ['label'=>'APEX Ω','color'=>'blue'],
            'description' => $exists ? '<p>جداول به‌درستی ساخته شده‌اند.</p>' : '<p>جدول '.$h.' وجود ندارد. افزونه را دوباره فعال کنید.</p>',
            'test' => 'apex_omega_tables',
        ];
    }

    /**
     * ایجاد برنامه جدید
     */
    public static function create_schedule(array $data): array {
        $errors = Validator::validate_schedule($data);
        if ($errors) return ['success' => false, 'errors' => $errors];

        $post_id = (int) $data['post_id'];
        if (!get_post($post_id)) return ['success' => false, 'errors' => ['نوشته یافت نشد.']];

        // مقدار فعلی
        $current = self::get_current_value($post_id, $data['field_type']);
        if ($current === null) return ['success' => false, 'errors' => ['نوع فیلد نامعتبر است.']];

        $target = $data['target_value'];

        // اگر هدف برابر فعلی
        if ((string)$target === (string)$current) {
            return ['success' => false, 'errors' => ['مقدار هدف برابر مقدار فعلی است؛ برنامه‌ای لازم نیست.']];
        }

        // بررسی نوسان اولیه
        $is_views = $data['field_type'] === 'views';
        $old_int = $is_views ? (int)$current : (int)$current; // برای سادگی
        $new_int = (int) $target;
        // خالی یا 0 = بدون محدودیت؛ قبلاً 0 باعث باگ سقف 0 می‌شد
        $max_step_for_check = (isset($data['max_step']) && $data['max_step'] !== '' && (int)$data['max_step'] > 0) ? (int)$data['max_step'] : null;
        $max_percent_for_check = (isset($data['max_percent_step']) && $data['max_percent_step'] !== '' && (float)$data['max_percent_step'] > 0) ? (float)$data['max_percent_step'] : null;
        $vol = Validator::check_volatility($old_int, $new_int, $max_step_for_check, $max_percent_for_check);
        $needs_confirm = $vol['requires_confirm'];
        $large_drop = $vol['delta'] < 0 && abs($vol['percent']) >= 20;

        // اگر کاهش بزرگ و تأیید نشده
        if ($large_drop && empty($data['confirm_large_drop'])) {
            return [
                'success' => false,
                'requires_confirm' => true,
                'warnings' => $vol['warnings'],
                'message' => 'کاهش بزرگ شناسایی شد. لطفاً تأیید صریح را علامت بزنید.',
            ];
        }

        // اگر نوسان زیاد و تأیید نشده
        if ($needs_confirm && empty($data['confirm_volatility'])) {
            return [
                'success' => false,
                'requires_confirm' => true,
                'warnings' => $vol['warnings'],
                'message' => 'تغییر از سقف مجاز بیشتر است. لطفاً تأیید کنید.',
            ];
        }

        global $wpdb;
        $table = Database::table_schedules();
        $now = current_time('mysql');
        $now_gmt = current_time('mysql', true);

        $start_gmt = $data['start_at_gmt'] ?? $now_gmt;
        $end_gmt = $data['end_at_gmt'] ?? gmdate('Y-m-d H:i:s', strtotime($now_gmt) + 30*DAY_IN_SECONDS);
        $start = $data['start_at'] ?? get_date_from_gmt($start_gmt, 'Y-m-d H:i:s');
        $end = $data['end_at'] ?? get_date_from_gmt($end_gmt, 'Y-m-d H:i:s');

        $next_gmt = $start_gmt;
        // اگر شروع در گذشته، next را الان بگذار
        if (strtotime($next_gmt) < time()) $next_gmt = gmdate('Y-m-d H:i:s', time() + 60);
        $next = get_date_from_gmt($next_gmt, 'Y-m-d H:i:s');

        $config = [
            'field_type' => $data['field_type'],
            'warnings_initial' => $vol['warnings'],
            'is_decrease' => $vol['delta'] < 0,
        ];
        if (isset($data['kksr_avg_target'])) $config['kksr_avg_target'] = $data['kksr_avg_target'];
        if (isset($data['kksr_casts_target'])) $config['kksr_casts_target'] = $data['kksr_casts_target'];

        $insert = [
            'post_id' => $post_id,
            'status' => 'active',
            'mode' => $data['mode'] ?? 'scheduled',
            'field_type' => $data['field_type'],
            'current_value' => (string) $current,
            'target_value' => (string) $target,
            'min_allowed' => isset($data['min_allowed']) ? (string)$data['min_allowed'] : null,
            'max_allowed' => isset($data['max_allowed']) ? (string)$data['max_allowed'] : null,
            'max_step' => isset($data['max_step']) ? (string)$data['max_step'] : null,
            'max_percent_step' => isset($data['max_percent_step']) ? (float)$data['max_percent_step'] : null,
            'interval_seconds' => (int) $data['interval_seconds'],
            'start_at' => $start,
            'start_at_gmt' => $start_gmt,
            'end_at' => $end,
            'end_at_gmt' => $end_gmt,
            'last_run_at' => null,
            'last_run_at_gmt' => null,
            'next_run_at' => $next,
            'next_run_at_gmt' => $next_gmt,
            'last_value' => (string) $current,
            'require_confirm_large_drop' => !empty($data['require_confirm_large_drop']) ? 1 : 1,
            'large_drop_threshold_percent' => isset($data['large_drop_threshold_percent']) ? (float)$data['large_drop_threshold_percent'] : 20.00,
            'large_drop_threshold_abs' => isset($data['large_drop_threshold_abs']) ? (int)$data['large_drop_threshold_abs'] : null,
            'execution_id' => apex_omega_generate_uuid(),
            'fail_count' => 0,
            'last_error' => null,
            'created_by' => get_current_user_id(),
            'updated_by' => get_current_user_id(),
            'created_at' => $now,
            'created_at_gmt' => $now_gmt,
            'updated_at' => $now,
            'updated_at_gmt' => $now_gmt,
            'config_json' => wp_json_encode($config, JSON_UNESCAPED_UNICODE),
        ];

        $wpdb->insert($table, $insert);
        $id = (int) $wpdb->insert_id;

        // اطمینان از کرون
        self::ensure_cron();

        // ذخیره حالت مدیریت روی پست
        update_post_meta($post_id, '_apex_mode', 'scheduled');
        update_post_meta($post_id, '_apex_schedule_id', $id);

        return ['success' => true, 'id' => $id, 'warnings' => $vol['warnings']];
    }

    public static function get_current_value(int $post_id, string $field_type) {
        if ($field_type === 'views') {
            return Adapter_Views::get_views($post_id);
        } elseif (in_array($field_type, ['kksr_casts','kksr_ratings','kksr_avg'], true)) {
            $stats = Adapter_KKSR::get_stats($post_id);
            if ($field_type === 'kksr_casts') return $stats['casts'];
            if ($field_type === 'kksr_ratings') return $stats['ratings'];
            if ($field_type === 'kksr_avg') return $stats['avg'];
        } elseif ($field_type === 'kksr_combined') {
            $stats = Adapter_KKSR::get_stats($post_id);
            return wp_json_encode(['casts'=>$stats['casts'],'ratings'=>$stats['ratings'],'avg'=>$stats['avg']], JSON_UNESCAPED_UNICODE);
        }
        return null;
    }

    /**
     * تابع اصلی تیک کرون — هر 5 دقیقه (افزایش تدریجی + Evergreen)
     */
    public static function tick(): void {
        global $wpdb;
        $table = Database::table_schedules();
        $now_gmt = current_time('mysql', true);

        // فقط برنامه‌های فعال که زمان‌شان رسیده
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE status = %s AND next_run_at_gmt <= %s AND start_at_gmt <= %s AND (end_at_gmt IS NULL OR end_at_gmt >= %s) ORDER BY next_run_at_gmt ASC LIMIT 20",
            'active', $now_gmt, $now_gmt, $now_gmt
        ), ARRAY_A);

        if ($rows) {
            foreach ($rows as $schedule) {
                self::execute_schedule($schedule);
            }
        }
        // نگهبان خودکار پست‌های تازه — هر 5 دقیقه چک کند (درخواست کاربر: پست‌های <10K)
        if (class_exists('\\ApexOmega\\Core\\AutoWatch')) {
            try { \ApexOmega\Core\AutoWatch::tick(); } catch (\Throwable $e) { error_log('[APEX Ω] AutoWatch tick error: '.$e->getMessage()); }
        }
        // رشد مداوم Evergreen (برای تمام پست‌ها، روزانه و مداوم)
        if (class_exists('\\ApexOmega\\Core\\Evergreen')) {
            try { \ApexOmega\Core\Evergreen::tick(); } catch (\Throwable $e) { error_log('[APEX Ω] Evergreen tick error: '.$e->getMessage()); }
        }
        // 💬 نگهبان کامنت حالا هر ۶ ساعت جدا اجرا می‌شود (apex_omega_commentor_tick) — اما fallback ایمنی هر ۵ دقیقه اگر گیت ۶ساعته جا ماند
        if (class_exists('\\ApexOmega\\Core\\CommentGuardian') && \ApexOmega\Core\CommentGuardian::is_enabled()) {
            try {
                $last_ts = (int) get_option('apex_omega_commentor_last_tick_ts', 0);
                if (!$last_ts || (time() - $last_ts >= 21600)) {
                    \ApexOmega\Core\CommentGuardian::tick();
                }
            } catch (\Throwable $e) { error_log('[APEX Ω] Commentor fallback tick error: '.$e->getMessage()); }
        }
        // 2.0-alpha: پردازش صف هوشمند (queue worker) — 5 job هر تیک با اولویت
        if (class_exists('\\ApexOmega\\Core\\QueueWorker')) {
            try { \ApexOmega\Core\QueueWorker::tick(5); } catch (\Throwable $e) { error_log('[APEX Ω] Queue tick error: '.$e->getMessage()); }
        } else {
            // fallback سبک: اگر QueueWorker هنوز لود نشده (برای سازگاری)
            try { self::process_queue_fallback(5); } catch (\Throwable $e) {}
        }
    }

    /**
     * Fallback ساده صف — اگر کلاس QueueWorker موجود نباشد (alpha)
     */
    public static function process_queue_fallback(int $limit = 5): void {
        global $wpdb;
        $table = Database::table_queue();
        // اگر جدول وجود ندارد (هنوز migrate نشده) خارج شو
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) return;
        $nowGmt = current_time('mysql', true);
        $jobs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE status IN ('pending','retry') AND (next_retry_gmt IS NULL OR next_retry_gmt <= %s) ORDER BY priority DESC, created_at_gmt ASC LIMIT %d",
            $nowGmt, $limit
        ), ARRAY_A);
        if (empty($jobs)) return;
        foreach ($jobs as $job) {
            $lockKey = 'apex_queue_' . $job['job_id'];
            if (!Database::acquire_lock($lockKey, 300)) continue;
            $wpdb->update($table, ['status'=>'running','started_at_gmt'=>$nowGmt,'attempts'=> (int)$job['attempts']+1], ['id'=>$job['id']]);
            // فعلا stub: فقط done کن (در نسخه کامل بر اساس type پردازش می‌شود)
            $payload = json_decode($job['payload_json'] ?? '', true);
            $type = $job['type'];
            $success = true;
            // rollup نمونه
            if ($type === 'timeseries_rollup') {
                $deleted = 0;
                try { $deleted = self::rollup_timeseries(); } catch (\Throwable $e) { $success = false; }
                if ($success) $wpdb->update($table, ['status'=>'done','completed_at_gmt'=>current_time('mysql', true)], ['id'=>$job['id']]);
                else $wpdb->update($table, ['status'=>'retry','next_retry_gmt'=>gmdate('Y-m-d H:i:s', time() + pow(2, (int)$job['attempts']+1)*60)], ['id'=>$job['id']]);
            } else {
                // سایر jobها فعلا done (برای alpha)
                $wpdb->update($table, ['status'=>'done','completed_at_gmt'=>current_time('mysql', true)], ['id'=>$job['id']]);
            }
            Database::release_lock($lockKey);
        }
    }

    public static function rollup_timeseries(): int {
        global $wpdb;
        $table = Database::table_timeseries();
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) return 0;
        // 5m -> 1h پس از 30 روز: حذف 5m قدیمی‌تر از 30d (نگهداری 1h باقی می‌ماند — در alpha فعلا فقط حذف)
        $cutoff30d = gmdate('Y-m-d H:i:s', time() - 30*DAY_IN_SECONDS);
        $deleted = $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE granularity='5m' AND sampled_at_gmt < %s LIMIT 1000", $cutoff30d));
        return (int) $deleted;
    }

    /**
     * اجرای یک برنامه
     */
    public static function execute_schedule(array $schedule): void {
        global $wpdb;
        $table = Database::table_schedules();
        $id = (int) $schedule['id'];
        $post_id = (int) $schedule['post_id'];
        $field = $schedule['field_type'];

        // قفل همزمانی per-post+field
        $lock_key = "apex_sched_{$post_id}_{$field}_{$id}";
        if (!Database::acquire_lock($lock_key, 120)) {
            // اجرای همزمان دیگری در حال اجراست
            return;
        }

        $execution_id = apex_omega_generate_uuid();
        $now = current_time('mysql');
        $now_gmt = current_time('mysql', true);

        try {
            // بازخوانی آخرین مقدار واقعی (نه کش)
            $current_raw = self::get_current_value($post_id, $field);
            $target_raw = $schedule['target_value'];

            // اگر پست حذف شده
            if (!get_post($post_id)) {
                self::mark_schedule($id, 'error', 'نوشته یافت نشد.', $now, $now_gmt);
                Database::release_lock($lock_key);
                return;
            }

            // اگر مقدار فعلی خارج از min/max مجاز
            if ($schedule['min_allowed'] !== null && $schedule['min_allowed'] !== '' && is_numeric($current_raw) && is_numeric($schedule['min_allowed'])) {
                if ((float)$current_raw < (float)$schedule['min_allowed'] || (float)$current_raw > (float)$schedule['max_allowed']) {
                    // اگر خارج از انتظار است -> نیاز به بررسی
                    self::mark_schedule($id, 'needs_review', sprintf('مقدار فعلی (%s) خارج از محدوده مجاز [%s - %s] است.', $current_raw, $schedule['min_allowed'], $schedule['max_allowed']), $now, $now_gmt);
                    Database::release_lock($lock_key);
                    return;
                }
            }

            // تشخیص تغییر خارجی ناگهانی (مثلاً افزونه یا مدیر دیگر)
            // مقایسه last_value با current: اگر اختلاف زیاد و ناگهانی بدون اجرای ما باشد
            $last_value = $schedule['last_value'];
            if ($last_value !== null && $last_value !== '' && $last_value != $current_raw) {
                // اگر تغییر بزرگ خارجی
                $delta_ext = (float)$current_raw - (float)$last_value;
                $percent_ext = (float)$last_value != 0 ? abs($delta_ext) / abs((float)$last_value) * 100 : 100;
                if (abs($delta_ext) > 1000 && $percent_ext > 10) {
                    // متوقف کن برای بررسی
                    self::mark_schedule($id, 'needs_review', sprintf('تغییر خارجی ناگهانی شناسایی شد: از %s به %s (%.1f%%). برنامه متوقف شد.', $last_value, $current_raw, $percent_ext), $now, $now_gmt);
                    // لاگ هشدار
                    History::log($post_id, $field, $last_value, $current_raw, 'external_change_detected', null, $execution_id, $id, ['percent'=>$percent_ext,'delta'=>$delta_ext]);
                    Database::release_lock($lock_key);
                    return;
                }
            }

            // محاسبه گام بعدی
            $step_result = self::calculate_next_step($schedule, $current_raw, $target_raw);
            $next_value = $step_result['next_value'];
            $reached = $step_result['reached'];

            // اعتبارسنجی نهایی قبل از نوشتن
            // بررسی سقف هر اجرا
            if ($schedule['max_step'] !== null && $schedule['max_step'] !== '' && is_numeric($schedule['max_step']) && (int)$schedule['max_step'] > 0) {
                $max_step = (int) $schedule['max_step'];
                $delta = abs((float)$next_value - (float)$current_raw);
                if ($delta > $max_step) {
                    // محدود کن به سقف
                    $direction = (float)$next_value > (float)$current_raw ? 1 : -1;
                    $next_value = (float)$current_raw + ($direction * $max_step);
                }
            }
            if ($schedule['max_percent_step'] !== null && $schedule['max_percent_step'] !== '' && is_numeric($schedule['max_percent_step']) && (float)$schedule['max_percent_step'] > 0) {
                $max_percent = (float) $schedule['max_percent_step'];
                $percent = (float)$current_raw != 0 ? abs(((float)$next_value - (float)$current_raw) / (float)$current_raw * 100) : 100;
                if ($percent > $max_percent) {
                    $direction = (float)$next_value > (float)$current_raw ? 1 : -1;
                    $allowed_delta = abs((float)$current_raw) * $max_percent / 100;
                    $next_value = (float)$current_raw + ($direction * $allowed_delta);
                    $next_value = $field === 'views' || $field === 'kksr_casts' ? (int) round($next_value) : round($next_value, 1);
                }
            }

            // بررسی نهایی حدود کلی
            if ($schedule['min_allowed'] !== null && $schedule['min_allowed'] !== '' && is_numeric($next_value) && (float)$next_value < (float)$schedule['min_allowed']) {
                $next_value = $schedule['min_allowed'];
            }
            if ($schedule['max_allowed'] !== null && $schedule['max_allowed'] !== '' && is_numeric($next_value) && (float)$next_value > (float)$schedule['max_allowed']) {
                $next_value = $schedule['max_allowed'];
            }

            // جلوگیری از عبور از هدف
            if ($field === 'views' || $field === 'kksr_casts' || $field === 'kksr_ratings') {
                $cur_f = (float)$current_raw; $tgt_f = (float)$target_raw; $nxt_f = (float)$next_value;
                if ($tgt_f > $cur_f && $nxt_f > $tgt_f) $next_value = $tgt_f;
                if ($tgt_f < $cur_f && $nxt_f < $tgt_f) $next_value = $tgt_f;
            } else if ($field === 'kksr_avg') {
                $cur_f = (float)$current_raw; $tgt_f = (float)$target_raw; $nxt_f = (float)$next_value;
                if ($tgt_f > $cur_f && $nxt_f > $tgt_f) $next_value = $tgt_f;
                if ($tgt_f < $cur_f && $nxt_f < $tgt_f) $next_value = $tgt_f;
            }

            // اجرای idempotent: اگر مقدار بعدی برابر فعلی است، فقط زمان‌بندی را جلو ببر
            if ((string)$next_value === (string)$current_raw) {
                // اگر به هدف رسیده‌ایم
                if ((string)$current_raw === (string)$target_raw || $reached) {
                    self::mark_completed($id, $now, $now_gmt, $current_raw);
                } else {
                    // فقط next_run را جلو ببر
                    self::advance_schedule($id, $next_value, $now, $now_gmt, $schedule);
                }
                Database::release_lock($lock_key);
                return;
            }

            // نوشتن واقعی
            $write_success = false;
            $old_for_history = $current_raw;
            if ($field === 'views') {
                $write_success = Adapter_Views::set_views($post_id, (int) $next_value, 'scheduled', $execution_id);
            } elseif ($field === 'kksr_casts') {
                // برای casts، ratings را ثابت نگه دار و avg را بازمحاسبه کن
                $stats = Adapter_KKSR::get_stats($post_id);
                $write_success = Adapter_KKSR::set_stats($post_id, (int)$next_value, $stats['ratings'], null, 'scheduled', $execution_id);
            } elseif ($field === 'kksr_ratings') {
                $stats = Adapter_KKSR::get_stats($post_id);
                $write_success = Adapter_KKSR::set_stats($post_id, $stats['casts'], (float)$next_value, null, 'scheduled', $execution_id);
            } elseif ($field === 'kksr_avg') {
                // avg به تنهایی: باید ratings را بر اساس casts فعلی محاسبه کنیم
                $stats = Adapter_KKSR::get_stats($post_id);
                $casts = $stats['casts'] ?: 1;
                $best = $stats['best'];
                // فرمول معکوس: ratings = avg * casts / best *5
                $new_ratings = (float)$next_value * $casts / $best * 5;
                $write_success = Adapter_KKSR::set_stats($post_id, $casts, $new_ratings, (float)$next_value, 'scheduled', $execution_id);
            } elseif ($field === 'kksr_combined') {
                $config = json_decode($schedule['config_json'], true);
                // combined شامل avg و casts هدف است
                $target_casts = $config['kksr_casts_target'] ?? null;
                $target_avg = $config['kksr_avg_target'] ?? null;
                // ساده: اگر casts هدف داریم، به آن نزدیک شو
                $stats = Adapter_KKSR::get_stats($post_id);
                $next_casts = $stats['casts'];
                $next_avg = $stats['avg'];
                if ($target_casts !== null) {
                    // گام برای casts
                    $delta_c = (int)$target_casts - $stats['casts'];
                    $step_c = self::calc_step($stats['casts'], (int)$target_casts, $schedule);
                    $next_casts = $stats['casts'] + $step_c;
                    if (($delta_c > 0 && $next_casts > (int)$target_casts) || ($delta_c < 0 && $next_casts < (int)$target_casts)) $next_casts = (int)$target_casts;
                }
                if ($target_avg !== null) {
                    $delta_a = (float)$target_avg - $stats['avg'];
                    $next_avg = $stats['avg'] + self::calc_step_float($stats['avg'], (float)$target_avg, $schedule, 0.1);
                    if (($delta_a > 0 && $next_avg > (float)$target_avg) || ($delta_a < 0 && $next_avg < (float)$target_avg)) $next_avg = (float)$target_avg;
                }
                $best = $stats['best'];
                $new_ratings = $next_avg * $next_casts / $best * 5;
                $write_success = Adapter_KKSR::set_stats($post_id, (int)$next_casts, (float)$new_ratings, (float)$next_avg, 'scheduled', $execution_id);
                $next_value = wp_json_encode(['casts'=>$next_casts,'ratings'=>$new_ratings,'avg'=>$next_avg], JSON_UNESCAPED_UNICODE);
            }

            if (!$write_success) {
                $fail_count = (int) $schedule['fail_count'] + 1;
                $wpdb->update($table, [
                    'fail_count' => $fail_count,
                    'last_error' => 'خطا در نوشتن متادیتا',
                    'updated_at' => $now,
                    'updated_at_gmt' => $now_gmt,
                ], ['id' => $id]);
                if ($fail_count >= 3) {
                    self::mark_schedule($id, 'error', 'سه تلاش ناموفق پیاپی.', $now, $now_gmt);
                }
                Database::release_lock($lock_key);
                return;
            }

            // ثبت تاریخچه
            History::log($post_id, $field, $old_for_history, $next_value, 'scheduled', null, $execution_id, $id, ['schedule_id'=>$id]);

            // بررسی رسیدن به هدف
            $new_current = self::get_current_value($post_id, $field);
            $is_completed = (string)$new_current === (string)$target_raw;
            // برای combined مقایسه casts/avg
            if ($field === 'kksr_combined') {
                $cfg = json_decode($schedule['config_json'], true);
                $st = Adapter_KKSR::get_stats($post_id);
                $is_completed = true;
                if (isset($cfg['kksr_casts_target']) && (int)$st['casts'] !== (int)$cfg['kksr_casts_target']) $is_completed = false;
                if (isset($cfg['kksr_avg_target']) && abs((float)$st['avg'] - (float)$cfg['kksr_avg_target']) > 0.05) $is_completed = false;
            }

            if ($is_completed || $reached) {
                self::mark_completed($id, $now, $now_gmt, $next_value);
            } else {
                self::advance_schedule($id, $next_value, $now, $now_gmt, $schedule);
            }

        } catch (\Throwable $e) {
            error_log('[APEX Ω] Scheduler error: ' . $e->getMessage());
            self::mark_schedule($id, 'error', $e->getMessage(), $now, $now_gmt);
        }

        Database::release_lock($lock_key);
    }

    private static function calculate_next_step(array $schedule, $current, $target): array {
        $cur = (float) $current;
        $tgt = (float) $target;
        if ($cur == $tgt) return ['next_value' => $current, 'reached' => true];

        $interval = (int) $schedule['interval_seconds'];
        $start_gmt = $schedule['start_at_gmt'];
        $end_gmt = $schedule['end_at_gmt'];
        if (!$start_gmt || !$end_gmt) {
            // بدون بازه؟ گام ساده 10٪ مانده
            $delta = $tgt - $cur;
            $step = $delta * 0.1;
            // برای views/casts گرد کن
            if (in_array($schedule['field_type'], ['views','kksr_casts'], true)) {
                $step = (int) round($step);
                if ($step == 0) $step = $delta > 0 ? 1 : -1;
            } else {
                $step = round($step, 1);
                if ($step == 0) $step = $delta > 0 ? 0.1 : -0.1;
            }
            $next = $cur + $step;
            if (in_array($schedule['field_type'], ['views','kksr_casts','kksr_ratings'], true)) $next = (int) round($next);
            return ['next_value' => $next, 'reached' => false];
        }

        $total_seconds = strtotime($end_gmt) - strtotime($start_gmt);
        $remaining_seconds = strtotime($end_gmt) - time();
        if ($total_seconds <= 0) $total_seconds = 2592000;
        if ($remaining_seconds <= 0) return ['next_value' => $target, 'reached' => true];

        $total_steps = max(1, ceil($total_seconds / $interval));
        $remaining_steps = max(1, ceil($remaining_seconds / $interval));

        $delta = $tgt - $cur;
        // 2.1.3: تقسیم خطی + پراکندگی ±12% تا همه پست‌های یک خواننده همزمان به یک عدد نرسند (940k تنگ حل شد)
        $step = $delta / $remaining_steps;
        // جیتر per-post (بر اساس post_id hash اگر موجود باشد) + تصادفی
        $jitter_factor = mt_rand(-120, 120) / 1000; // -12%..+12%
        if (isset($schedule['post_id'])) {
            $pid_hash = hexdec(substr(md5((string)$schedule['post_id'] . $schedule['field_type']),0,4)) % 200 - 100; // -100..99
            $jitter_factor += $pid_hash / 1200; // ±8% اضافی deterministic
        }
        $step = $step * (1 + $jitter_factor);

        // حداقل گام برای جلوگیری از سکون
        if (in_array($schedule['field_type'], ['views','kksr_casts'], true)) {
            $step = (int) round($step);
            if ($step == 0) $step = $delta > 0 ? 1 : -1;
            $next = $cur + $step;
            $next = (int) round($next);
        } elseif ($schedule['field_type'] === 'kksr_ratings') {
            $step = round($step);
            if ($step == 0) $step = $delta > 0 ? 1 : -1;
            $next = $cur + $step;
        } else { // avg
            $step = round($step, 1);
            if (abs($step) < 0.05) $step = $delta > 0 ? 0.1 : -0.1;
            $next = round($cur + $step, 1);
        }

        // اگر گام بعدی از هدف عبور می‌کند، مستقیم به هدف
        if (($delta > 0 && $next > $tgt) || ($delta < 0 && $next < $tgt)) {
            $next = $tgt;
        }

        $reached = (string)$next === (string)$tgt;
        return ['next_value' => $next, 'reached' => $reached];
    }

    private static function calc_step(int $cur, int $tgt, array $schedule): int {
        $r = self::calculate_next_step($schedule, $cur, $tgt);
        return (int) ($r['next_value'] - $cur);
    }
    private static function calc_step_float(float $cur, float $tgt, array $schedule, float $min_step): float {
        $r = self::calculate_next_step($schedule, $cur, $tgt);
        $diff = (float)$r['next_value'] - $cur;
        if (abs($diff) < $min_step && abs($tgt - $cur) >= $min_step) {
            $diff = $tgt > $cur ? $min_step : -$min_step;
        }
        return $diff;
    }

    private static function advance_schedule(int $id, $new_value, string $now, string $now_gmt, array $schedule): void {
        global $wpdb;
        $table = Database::table_schedules();
        $interval = (int) $schedule['interval_seconds'];
        $next_gmt = gmdate('Y-m-d H:i:s', time() + $interval);
        $next = get_date_from_gmt($next_gmt, 'Y-m-d H:i:s');
        $wpdb->update($table, [
            'last_run_at' => $now,
            'last_run_at_gmt' => $now_gmt,
            'next_run_at' => $next,
            'next_run_at_gmt' => $next_gmt,
            'last_value' => is_scalar($new_value) ? (string)$new_value : wp_json_encode($new_value, JSON_UNESCAPED_UNICODE),
            'current_value' => is_scalar($new_value) ? (string)$new_value : wp_json_encode($new_value, JSON_UNESCAPED_UNICODE),
            'fail_count' => 0,
            'last_error' => null,
            'updated_at' => $now,
            'updated_at_gmt' => $now_gmt,
        ], ['id' => $id]);
    }

    private static function mark_completed(int $id, string $now, string $now_gmt, $final_value): void {
        global $wpdb;
        $table = Database::table_schedules();
        $wpdb->update($table, [
            'status' => 'completed',
            'last_run_at' => $now,
            'last_run_at_gmt' => $now_gmt,
            'next_run_at' => null,
            'next_run_at_gmt' => null,
            'last_value' => is_scalar($final_value) ? (string)$final_value : wp_json_encode($final_value, JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
            'updated_at_gmt' => $now_gmt,
        ], ['id' => $id]);

        // حالت پست را به completed تغییر نده، فقط وضعیت برنامه
        // برای اطلاع مدیر، یک هشدار ثبت کن
    }

    private static function mark_schedule(int $id, string $status, string $error, string $now, string $now_gmt): void {
        global $wpdb;
        $table = Database::table_schedules();
        $wpdb->update($table, [
            'status' => $status,
            'last_error' => $error,
            'updated_at' => $now,
            'updated_at_gmt' => $now_gmt,
        ], ['id' => $id]);
    }

    public static function get_schedules_for_post(int $post_id, ?string $status = null): array {
        global $wpdb;
        $table = Database::table_schedules();
        $where = $wpdb->prepare("post_id = %d", $post_id);
        if ($status) $where .= $wpdb->prepare(" AND status = %s", $status);
        return $wpdb->get_results("SELECT * FROM $table WHERE $where ORDER BY created_at_gmt DESC", ARRAY_A) ?: [];
    }

    public static function get_all_active(int $limit = 50): array {
        global $wpdb;
        $table = Database::table_schedules();
        return $wpdb->get_results($wpdb->prepare("SELECT s.*, p.post_title FROM $table s LEFT JOIN {$wpdb->posts} p ON p.ID = s.post_id WHERE s.status = %s ORDER BY s.next_run_at_gmt ASC LIMIT %d", 'active', $limit), ARRAY_A) ?: [];
    }

    public static function pause(int $id): bool {
        global $wpdb;
        $table = Database::table_schedules();
        $now = current_time('mysql'); $now_gmt = current_time('mysql', true);
        $r = $wpdb->update($table, ['status'=>'paused','updated_at'=>$now,'updated_at_gmt'=>$now_gmt], ['id'=>$id, 'status'=>'active']);
        return $r !== false;
    }
    public static function resume(int $id): bool {
        global $wpdb;
        $table = Database::table_schedules();
        $now = current_time('mysql'); $now_gmt = current_time('mysql', true);
        $next_gmt = gmdate('Y-m-d H:i:s', time() + 300);
        $next = get_date_from_gmt($next_gmt, 'Y-m-d H:i:s');
        $r = $wpdb->update($table, ['status'=>'active','next_run_at'=>$next,'next_run_at_gmt'=>$next_gmt,'updated_at'=>$now,'updated_at_gmt'=>$now_gmt], ['id'=>$id, 'status'=>'paused']);
        return $r !== false;
    }
    public static function cancel(int $id): bool {
        global $wpdb;
        $table = Database::table_schedules();
        $now = current_time('mysql'); $now_gmt = current_time('mysql', true);
        $r = $wpdb->update($table, ['status'=>'cancelled','next_run_at'=>null,'next_run_at_gmt'=>null,'updated_at'=>$now,'updated_at_gmt'=>$now_gmt], ['id'=>$id]);
        if ($r) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT post_id FROM $table WHERE id=%d", $id), ARRAY_A);
            if ($row) update_post_meta((int)$row['post_id'], '_apex_mode', 'manual');
        }
        return $r !== false;
    }

    /**
     * پیش‌نمایش گام‌های آینده
     */
    public static function preview(int $post_id, string $field_type, $target_value, string $start_gmt, string $end_gmt, int $interval): array {
        $current = self::get_current_value($post_id, $field_type);
        $steps = [];
        $cur = (float)$current;
        $tgt = (float)$target_value;
        $total_seconds = strtotime($end_gmt) - strtotime($start_gmt);
        $remaining_steps = max(1, ceil($total_seconds / $interval));
        // شبیه‌سازی
        for ($i=0; $i < min($remaining_steps, 20); $i++) {
            $delta = $tgt - $cur;
            if (abs($delta) < 0.001) break;
            $step = $delta / ($remaining_steps - $i);
            if (in_array($field_type, ['views','kksr_casts','kksr_ratings'], true)) {
                $step = (int) round($step);
                if ($step == 0) $step = $delta > 0 ? 1 : -1;
                $cur = $cur + $step;
                $cur = (int) round($cur);
                if (($delta > 0 && $cur > $tgt) || ($delta < 0 && $cur < $tgt)) $cur = $tgt;
            } else {
                $step = round($step, 1);
                if (abs($step) < 0.05) $step = $delta > 0 ? 0.1 : -0.1;
                $cur = round($cur + $step, 1);
                if (($delta > 0 && $cur > $tgt) || ($delta < 0 && $cur < $tgt)) $cur = $tgt;
            }
            $at = gmdate('Y-m-d H:i:s', strtotime($start_gmt) + ($i+1)*$interval);
            $steps[] = ['step'=> $i+1, 'value'=> $cur, 'at_gmt'=> $at, 'at'=> get_date_from_gmt($at, 'Y-m-d H:i:s')];
            if ((string)$cur === (string)$tgt) break;
        }
        return ['current'=>$current,'target'=>$target_value,'steps'=>$steps,'total_steps'=>$remaining_steps];
    }
}
