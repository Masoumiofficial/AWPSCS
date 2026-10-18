<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;
require_once __DIR__ . '/includes/class-database.php';
$settings = get_option('apex_omega_settings', []);
$remove = $settings['remove_data_on_uninstall'] ?? false;
if ($remove) {
    \ApexOmega\Core\Database::drop_tables(true);
    delete_option('apex_omega_settings');
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_apex_%'");
}
// همیشه کرون را پاک کن
wp_clear_scheduled_hook('apex_omega_tick');
wp_clear_scheduled_hook('apex_omega_cleanup');
wp_clear_scheduled_hook('apex_omega_weekly_report');
wp_clear_scheduled_hook('apex_omega_auto_tier_tick');
