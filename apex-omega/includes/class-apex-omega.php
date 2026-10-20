<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

class Plugin {
    public function init(): void {
        // مایگریشن بی‌دردسر برای 1.2.2: اگر جدول اسنپ‌شات نیست، بساز (بدون نیاز به غیرفعال/فعال)
        add_action('admin_init', function(){
            global $wpdb;
            $t = Database::table_snapshots();
            if ($wpdb->get_var("SHOW TABLES LIKE '$t'") !== $t) {
                Database::create_tables();
            }
            // مایگریشن کلید قدیمی plain به رمزنگاری‌شده (یک‌بار)
            $ai_opt = get_option(\ApexOmega\Core\AI_Helper::OPTION_KEY, []);
            if (is_array($ai_opt) && !empty($ai_opt['api_key']) && strpos($ai_opt['api_key'],'enc1:')!==0 && strpos($ai_opt['api_key'],'b64:')!==0) {
                $ai_opt['api_key'] = apex_omega_encrypt($ai_opt['api_key']);
                update_option(\ApexOmega\Core\AI_Helper::OPTION_KEY, $ai_opt, false);
            }
        }, 5);
        Scheduler::init();
        Adapter_KKSR::register_display_filters();

        // هوک رأی واقعی جدید -> اگر برنامه فعال است، آن را به حالت نیازمند بررسی ببر
        add_action('kksr_vote', [$this, 'on_kksr_vote'], 10, 2);
        add_action('apex_omega_kksr_updated', [$this, 'on_kksr_manage_update'], 10, 5);
        add_action('apex_omega_views_updated', [$this, 'on_views_manage_update'], 10, 5);

        // Ajax و REST
        if (class_exists('\ApexOmega\Core\Ajax')) {
            Ajax::init();
        }
        if (class_exists('\ApexOmega\Core\Rest')) {
            Rest::init();
        }

        // مدیریت کش: پس از تغییر آمار، کش پست را پاک کن
        add_action('apex_omega_kksr_updated', [$this, 'purge_cache'], 20, 5);
        add_action('apex_omega_views_updated', [$this, 'purge_cache_views'], 20, 5);

        // توزیع خودکار روزانه
        add_action('apex_omega_auto_tier_tick', [$this, 'run_auto_tier']);

        // REST v2 — 2.0-alpha
        if (class_exists('\\ApexOmega\\Core\\RestV2')) {
            RestV2::init();
        }

        // هشدار ادمین
        add_action('wp_ajax_apex_heal_cron', ['\\ApexOmega\\Core\\Scheduler', 'ajax_heal_cron']);
        add_action('admin_notices', [$this, 'admin_notices']);
    }

    public function run_auto_tier(): void {
        $auto = get_option('apex_omega_auto_tier', []);
        if (empty($auto['enabled']) || empty($auto['counts'])) return;
        $filters = $auto['filters'] ?? ['category'=>'all','post_type'=>'any'];
        $filters['exclude_recent_days'] = 7;
        \ApexOmega\Core\TierManager::apply_distribution($auto['counts'], $filters);
        error_log('[APEX Ω] auto tier distribution executed (7-day filter)');
    }

    public function on_kksr_vote($post_id, $rating): void {
        // این هوک توسط kk Star Ratings در نسخه‌های جدید فراخوانی می‌شود
        // بررسی کن آیا برنامه فعالی برای این پست وجود دارد
        $post_id = (int) $post_id;
        $schedules = Scheduler::get_schedules_for_post($post_id, 'active');
        if (empty($schedules)) return;

        foreach ($schedules as $sch) {
            if (in_array($sch['field_type'], ['kksr_casts','kksr_ratings','kksr_avg','kksr_combined'], true)) {
                global $wpdb;
                $table = Database::table_schedules();
                $now = current_time('mysql'); $now_gmt = current_time('mysql', true);
                $wpdb->update($table, [
                    'status' => 'needs_review',
                    'last_error' => sprintf('رأی واقعی جدید ثبت شد (امتیاز %s). برنامه برای جلوگیری از تداخل متوقف شد. لطفاً بررسی و در صورت نیاز ادامه دهید.', $rating),
                    'updated_at' => $now,
                    'updated_at_gmt' => $now_gmt,
                ], ['id' => $sch['id']]);

                // لاگ
                History::log($post_id, $sch['field_type'], $sch['last_value'] ?? '', $sch['target_value'], 'real_vote_interrupt', null, apex_omega_generate_uuid(), (int)$sch['id'], ['rating'=>$rating]);
            }
        }
    }

    public function on_kksr_manage_update(int $post_id, array $old, array $new, string $source, ?string $exec): void {
        if ($source === 'scheduled') return;
        // اگر مدیریت دستی انجام شد و برنامه فعال وجود دارد، آن را به needs_review ببر
        $schedules = Scheduler::get_schedules_for_post($post_id, 'active');
        if (empty($schedules)) return;
        // فقط اگر تغییر دستی بزرگ باشد
        foreach ($schedules as $sch) {
            if (in_array($sch['field_type'], ['kksr_casts','kksr_ratings','kksr_avg','kksr_combined'], true)) {
                global $wpdb;
                $table = Database::table_schedules();
                $now = current_time('mysql'); $now_gmt = current_time('mysql', true);
                $wpdb->update($table, [
                    'status' => 'needs_review',
                    'last_error' => 'تغییر دستی خارج از برنامه شناسایی شد. برای جلوگیری از تداخل، برنامه متوقف شد.',
                    'updated_at' => $now,
                    'updated_at_gmt' => $now_gmt,
                ], ['id' => $sch['id']]);
            }
        }
    }

    public function on_views_manage_update(int $post_id, int $old, int $new, string $source, ?string $exec): void {
        if ($source === 'scheduled') return;
        $schedules = Scheduler::get_schedules_for_post($post_id, 'active');
        if (empty($schedules)) return;
        foreach ($schedules as $sch) {
            if ($sch['field_type'] === 'views') {
                global $wpdb;
                $table = Database::table_schedules();
                $now = current_time('mysql'); $now_gmt = current_time('mysql', true);
                $wpdb->update($table, [
                    'status' => 'needs_review',
                    'last_error' => 'تغییر دستی بازدید خارج از برنامه شناسایی شد.',
                    'updated_at' => $now,
                    'updated_at_gmt' => $now_gmt,
                ], ['id' => $sch['id']]);
            }
        }
    }

    public function purge_cache(...$args): void {
        // سازگاری با افزونه‌های کش رایج
        if (function_exists('rocket_clean_post')) {
            $post_id = $args[0] ?? null;
            if ($post_id) rocket_clean_post($post_id);
        }
        if (function_exists('w3tc_flush_post')) {
            w3tc_flush_post($args[0] ?? 0);
        }
        if (defined('LSCWP_V')) {
            do_action('litespeed_purge_post', $args[0] ?? 0);
        }
        // کش آبجکت
        if (function_exists('wp_cache_flush')) {
            // فقط متای پست را پاک کن، نه کل کش
            // wp_cache_flush سنگین است؛ از آن صرف‌نظر می‌کنیم
        }
    }
    public function purge_cache_views(...$args): void {
        $this->purge_cache(...$args);
    }

    public function admin_notices(): void {
        if (get_transient('apex_omega_activated')) {
            delete_transient('apex_omega_activated');
            echo '<div class="notice notice-success is-dismissible"><p><strong>APEX Ω</strong> ' . esc_html__('با موفقیت فعال شد. به منوی «آمار پیشرفته» بروید.', 'apex-omega') . '</p></div>';
        }
        if (get_transient('apex_omega_deactivated')) {
            delete_transient('apex_omega_deactivated');
        }

        // هشدار WP-Cron
        $settings = get_option('apex_omega_settings', []);
        if (!empty($settings['enable_real_cron_hint']) && defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('هشدار APEX Ω: WP-Cron غیرفعال است (DISABLE_WP_CRON). زمان‌بندی دقیق نیاز به Cron واقعی سرور دارد. به مستندات مراجعه کنید.', 'apex-omega') . '</p></div>';
        }

        // بررسی برنامه‌های نیازمند بررسی
        if (apex_omega_user_can_manage()) {
            global $wpdb;
            $table = Database::table_schedules();
            $needs = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status = %s", 'needs_review'));
            if ($needs > 0) {
                $url = admin_url('admin.php?page=apex-omega');
                echo '<div class="notice notice-warning"><p>' . sprintf(esc_html__('%s برنامه نیازمند بررسی وجود دارد.', 'apex-omega'), $needs) . ' <a href="' . esc_url($url) . '">' . esc_html__('مشاهده', 'apex-omega') . '</a></p></div>';
            }
        }
    }
}
