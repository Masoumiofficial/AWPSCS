<?php
/**
 * Plugin Name:       APEX Ω — Advanced WordPress Statistics Control System
 * Plugin URI:        https://etehadwp.com/plugins/apex-omega
 * Description:       مدیریت دقیق، امن و زمان‌بندی‌شده آمار بازدید، امتیاز ستاره‌ای و آراء آهنگ‌ها با سازگاری کامل با kk Star Ratings. کنترل نوسان، تاریخچه کامل و بازگردانی هوشمند.
 * Version:           2.2.1 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            اتحاد وردپرس | EtehadWP
 * Author URI:        https://etehadwp.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       apex-omega
 * Domain Path:       /languages
 * Tags:              statistics, views, kksr, scheduling, music
 */

if (!defined('ABSPATH')) {
    http_response_code(404);
    exit;
}

define('APEX_OMEGA_VERSION', '2.2.1');define('APEX_OMEGA_FILE', __FILE__);
define('APEX_OMEGA_DIR', plugin_dir_path(__FILE__));
define('APEX_OMEGA_URL', plugin_dir_url(__FILE__));
define('APEX_OMEGA_BASENAME', plugin_basename(__FILE__));
define('APEX_OMEGA_MIN_PHP', '7.4');
define('APEX_OMEGA_MIN_WP', '5.8');

// PHP version check
if (version_compare(PHP_VERSION, APEX_OMEGA_MIN_PHP, '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>' . esc_html__('APEX Ω نیاز به PHP 7.4 یا بالاتر دارد.', 'apex-omega') . '</p></div>';
    });
    return;
}

require_once APEX_OMEGA_DIR . 'includes/helpers.php';
require_once APEX_OMEGA_DIR . 'includes/class-database.php';
require_once APEX_OMEGA_DIR . 'includes/class-validator.php';
require_once APEX_OMEGA_DIR . 'includes/class-adapter-kksr.php';
require_once APEX_OMEGA_DIR . 'includes/class-adapter-views.php';
require_once APEX_OMEGA_DIR . 'includes/class-history.php';
require_once APEX_OMEGA_DIR . 'includes/class-scheduler.php';
require_once APEX_OMEGA_DIR . 'includes/class-tier.php';
require_once APEX_OMEGA_DIR . 'includes/class-analytics.php';
require_once APEX_OMEGA_DIR . 'includes/class-export.php';
require_once APEX_OMEGA_DIR . 'includes/class-ai.php';
require_once APEX_OMEGA_DIR . 'includes/class-artist.php';
require_once APEX_OMEGA_DIR . 'includes/class-provider.php';
require_once APEX_OMEGA_DIR . 'includes/class-evergreen.php';
require_once APEX_OMEGA_DIR . 'includes/class-autowatch.php';
require_once APEX_OMEGA_DIR . 'includes/class-comment.php';
require_once APEX_OMEGA_DIR . 'includes/class-activator.php';
require_once APEX_OMEGA_DIR . 'includes/class-deactivator.php';
require_once APEX_OMEGA_DIR . 'includes/class-rest.php';
require_once APEX_OMEGA_DIR . 'includes/class-rest-v2.php';
require_once APEX_OMEGA_DIR . 'includes/class-simulator.php';
require_once APEX_OMEGA_DIR . 'includes/class-queue-worker.php';
require_once APEX_OMEGA_DIR . 'includes/class-cli.php';
if (defined('WP_CLI') && WP_CLI) {
    add_action('init', function(){ if (class_exists('\\ApexOmega\\Core\\CLI')) \ApexOmega\Core\CLI::register(); }, 20);
}
require_once APEX_OMEGA_DIR . 'includes/class-ajax.php';
require_once APEX_OMEGA_DIR . 'includes/class-apex-omega.php';

if (is_admin()) {
    require_once APEX_OMEGA_DIR . 'admin/class-admin.php';
    require_once APEX_OMEGA_DIR . 'admin/class-metabox.php';
    require_once APEX_OMEGA_DIR . 'admin/class-bulk.php';
}

require_once APEX_OMEGA_DIR . 'public/class-public.php';

function apex_omega(): \ApexOmega\Core\Plugin {
    static $instance = null;
    if (null === $instance) {
        $instance = new \ApexOmega\Core\Plugin();
    }
    return $instance;
}

register_activation_hook(__FILE__, ['\ApexOmega\Core\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['\ApexOmega\Core\Deactivator', 'deactivate']);
register_uninstall_hook(__FILE__, ['\ApexOmega\Core\Activator', 'uninstall']);

add_action('plugins_loaded', function () {
    load_plugin_textdomain('apex-omega', false, dirname(APEX_OMEGA_BASENAME) . '/languages');
    apex_omega()->init();
    // 1.9.4: مهاجرت DB خودکار بدون نیاز به reactivate (jobs + is_complete)
    add_action('init', function() {
        $dbv = get_option('apex_omega_db_version', '0');
        if (version_compare($dbv, \ApexOmega\Core\Database::VERSION, '<')) {
            \ApexOmega\Core\Database::create_tables();
        }
    }, 15);
    // مهاجرت 1.9.3 → 2.1.7: سقف 1.5M → 10M + پاکسازی حالت ساده/حرفه‌ای منسوخ + فعال‌سازی نگهبان خودکار
    add_action('init', function() {
        $stored = get_option(\ApexOmega\Core\TierManager::OPTION_TIERS, null);
        if (is_array($stored) && !empty($stored)) {
            $max = 0; foreach ($stored as $t) $max = max($max, (int)($t['views_max'] ?? 0));
            if ($max > 10000000) {
                update_option(\ApexOmega\Core\TierManager::OPTION_TIERS, \ApexOmega\Core\TierManager::default_tiers(), false);
            }
        }
        if (get_option('apex_omega_ui_mode', null) !== null) delete_option('apex_omega_ui_mode');
    }, 20);
});
