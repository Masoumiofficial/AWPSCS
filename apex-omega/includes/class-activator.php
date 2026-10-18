<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

class Activator {
    public static function activate(): void {
        if (version_compare(PHP_VERSION, APEX_OMEGA_MIN_PHP, '<')) {
            wp_die(esc_html__('APEX Ω نیاز به PHP 7.4 یا بالاتر دارد.', 'apex-omega'));
        }

        Database::create_tables();

        // نقش و قابلیت
        $role = get_role('administrator');
        if ($role && !$role->has_cap('apex_manage_statistics')) {
            $role->add_cap('apex_manage_statistics');
        }
        // نقش ویرایشگر هم اجازه داشته باشد (قابل تنظیم)
        $editor = get_role('editor');
        if ($editor && !$editor->has_cap('apex_manage_statistics')) {
            // به‌صورت پیش‌فرض فقط ادمین؛ ویرایشگر را اضافه نمی‌کنیم مگر تنظیم شود
        }

        // تنظیمات پیش‌فرض
        $defaults = [
            'large_drop_threshold_percent' => 20,
            'max_default_step_views' => 500,
            'max_default_percent' => 15,
            'lock_ttl' => 120,
            'enable_real_cron_hint' => true,
            'db_version' => Database::VERSION,
        ];
        if (!get_option('apex_omega_settings')) {
            add_option('apex_omega_settings', $defaults);
        }

        Scheduler::ensure_cron();

        // فلش پیام نصب
        set_transient('apex_omega_activated', 1, 30);

        // لاگ نصب
        error_log('[APEX Ω] Activated version ' . APEX_OMEGA_VERSION);
    }

    public static function uninstall(): void {
        // فقط اگر کاربر صراحتاً گزینه حذف داده‌ها را فعال کرده باشد
        $settings = get_option('apex_omega_settings', []);
        $remove_data = $settings['remove_data_on_uninstall'] ?? false;

        // سیاست: به‌صورت پیش‌فرض آمار اصلی حذف نمی‌شود؛ فقط جداول APEX پاک می‌شود اگر تأیید شده باشد
        if ($remove_data) {
            Database::drop_tables(true);
            delete_option('apex_omega_settings');
            delete_option('apex_omega_ai_settings');
            delete_option('apex_omega_tiers');
            delete_option('apex_omega_last_distribution');
            delete_option('apex_omega_auto_tier');
            delete_option('apex_omega_rhythm');
            delete_option('apex_omega_evergreen');
            delete_option('apex_omega_autowatch');
            delete_option('apex_omega_autowatch_last_run');
            delete_option('apex_omega_commentor');
            delete_option('apex_omega_commentor_last_run');
            delete_option('apex_omega_artist_weights');
            delete_option('apex_omega_artist_taxonomy');
            delete_option('apex_omega_artist_external_cache');
            delete_option('apex_omega_provider_failures');
            delete_option('apex_omega_ui_mode');
            // متای پست‌های مرتبط
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_apex_%'");
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_apex_%' OR option_name LIKE '_transient_timeout_apex_%'");
        } else {
            // حتی اگر حذف داده فعال نباشد، کرون را پاک کن اما جداول بماند برای بازگشت
            Scheduler::clear_cron();
        }

        // حذف قابلیت
        $role = get_role('administrator');
        if ($role && $role->has_cap('apex_manage_statistics')) {
            $role->remove_cap('apex_manage_statistics');
        }
    }
}
