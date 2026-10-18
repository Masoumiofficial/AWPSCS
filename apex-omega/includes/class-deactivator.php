<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

class Deactivator {
    public static function deactivate(): void {
        Scheduler::clear_cron();
        // آمار اصلی را حذف نکن؛ فقط کرون
        // قفل‌ها را پاک کن
        Database::cleanup_expired_locks();

        // فلش
        set_transient('apex_omega_deactivated', 1, 30);
        error_log('[APEX Ω] Deactivated');
    }
}
