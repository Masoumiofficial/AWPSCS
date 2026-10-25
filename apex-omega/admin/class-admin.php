<?php
namespace ApexOmega\Admin;

if (!defined('ABSPATH')) exit;

use ApexOmega\Core\Database;
use ApexOmega\Core\Adapter_KKSR;
use ApexOmega\Core\Adapter_Views;
use ApexOmega\Core\History;
use ApexOmega\Core\Scheduler;
use ApexOmega\Core\Validator;

class Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'menus']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function menus(): void {
        add_menu_page(
            'APEX Ω — آمار پیشرفته',
            'آمار پیشرفته',
            'apex_manage_statistics',
            'apex-omega',
            [$this, 'render_dashboard'],
            'dashicons-chart-bar',
            58
        );
        add_submenu_page(
            'apex-omega',
            'داشبورد — APEX Ω',
            'داشبورد',
            'apex_manage_statistics',
            'apex-omega',
            [$this, 'render_dashboard']
        );
        add_submenu_page(
            'apex-omega',
            'توزیع هوشمند — APEX Ω',
            'توزیع هوشمند',
            'apex_manage_statistics',
            'apex-omega-tier',
            [$this, 'render_tier']
        );
        add_submenu_page(
            'apex-omega',
            'هنرمندان — APEX Ω',
            'هنرمندان',
            'apex_manage_statistics',
            'apex-omega-artists',
            [$this, 'render_artists']
        );
        add_submenu_page(
            'apex-omega',
            'اتوماسیون — APEX Ω',
            'اتوماسیون',
            'apex_manage_statistics',
            'apex-omega-automation',
            [$this, 'render_automation']
        );
        add_submenu_page(
            'apex-omega',
            'همگام‌سازی وزنی — APEX Ω',
            'همگام‌سازی',
            'apex_manage_statistics',
            'apex-omega-weight-sync',
            [$this, 'render_weight_sync']
        );
        add_submenu_page(
            'apex-omega',
            'آثار منتخب — APEX Ω',
            'آثار منتخب',
            'apex_manage_statistics',
            'apex-omega-hits',
            [$this, 'render_hits']
        );
        add_submenu_page(
            'apex-omega',
            'آنالیتیکس — APEX Ω',
            'آنالیتیکس',
            'apex_manage_statistics',
            'apex-omega-analytics',
            [$this, 'render_analytics']
        );
        add_submenu_page(
            'apex-omega',
            'ویرایش گروهی — APEX Ω',
            'ویرایش گروهی',
            'apex_manage_statistics',
            'apex-omega-bulk',
            [$this, 'render_bulk']
        );
        add_submenu_page(
            'apex-omega',
            'تاریخچه — APEX Ω',
            'تاریخچه',
            'apex_manage_statistics',
            'apex-omega-history',
            [$this, 'render_history']
        );
        add_submenu_page(
            'apex-omega',
            'تنظیمات — APEX Ω',
            'تنظیمات',
            'manage_options',
            'apex-omega-settings',
            [$this, 'render_settings']
        );
        add_submenu_page(
            'apex-omega',
            'راهنما — APEX Ω',
            'راهنما',
            'apex_manage_statistics',
            'apex-omega-help',
            [$this, 'render_help']
        );
    }

    public function assets(string $hook): void {
        if (strpos($hook, 'apex-omega') === false && $hook !== 'post.php' && $hook !== 'post-new.php') return;

        wp_enqueue_style('apex-omega-admin', APEX_OMEGA_URL . 'admin/css/admin-rtl.css', [], APEX_OMEGA_VERSION);
        wp_enqueue_style('apex-omega-glass', APEX_OMEGA_URL . 'admin/css/apex-glass.css', ['apex-omega-admin'], APEX_OMEGA_VERSION);
        wp_enqueue_script('apex-omega-admin', APEX_OMEGA_URL . 'admin/js/admin.js', ['jquery'], APEX_OMEGA_VERSION, true);
        global $wpdb;
        $total_posts = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'");
        wp_localize_script('apex-omega-admin', 'ApexOmega', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('apex-omega/v1/'),
            'nonce' => wp_create_nonce('apex_omega_nonce'),
            'rest_nonce' => wp_create_nonce('wp_rest'),
            'total_posts' => $total_posts,
            'i18n' => [
                'confirm_large_drop' => 'کاهش بزرگ شناسایی شد. آیا مطمئن هستید؟',
                'confirm_bulk' => 'آیا از اعمال گروهی مطمئن هستید؟',
                'preview_loading' => 'در حال محاسبه پیش‌نمایش…',
                'save_success' => 'ذخیره شد.',
                'error' => 'خطا رخ داد.',
            ],
        ]);
    }

    public function register_settings(): void {
        register_setting('apex_omega_settings', 'apex_omega_settings', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'default' => [],
        ]);
    }

    public function sanitize_settings($input) {
        $out = get_option('apex_omega_settings', []);
        if (isset($input['large_drop_threshold_percent'])) $out['large_drop_threshold_percent'] = max(1, min(100, (float)$input['large_drop_threshold_percent']));
        if (isset($input['max_default_step_views'])) $out['max_default_step_views'] = max(1, (int)$input['max_default_step_views']);
        if (isset($input['max_default_percent'])) $out['max_default_percent'] = max(1, min(100, (float)$input['max_default_percent']));
        if (isset($input['enable_real_cron_hint'])) $out['enable_real_cron_hint'] = !empty($input['enable_real_cron_hint']);
        if (isset($input['remove_data_on_uninstall'])) $out['remove_data_on_uninstall'] = !empty($input['remove_data_on_uninstall']);
        if (isset($input['never_decrease_views'])) $out['never_decrease_views'] = !empty($input['never_decrease_views']);
        // تیره‌ها
        if (isset($input['ai_api_key'])) $out['ai_api_key'] = sanitize_text_field($input['ai_api_key']);
        return $out;
    }

    public function render_dashboard(): void {
        global $wpdb;
        $counts = $this->get_dashboard_counts();
        $compat_kksr = Adapter_KKSR::get_compatibility_status();
        $compat_views = Adapter_Views::detect();
        $recent = History::get_all_recent(15);
        $active_schedules = Scheduler::get_all_active(10);

        // هشدار داده‌های ناسازگار
        $warnings = $this->collect_warnings($compat_kksr, $compat_views);

        include APEX_OMEGA_DIR . 'admin/views/dashboard.php';
    }

    private function get_dashboard_counts(): array {
        global $wpdb;
        $total_posts = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'");
        $managed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_apex_mode'");
        $s_table = Database::table_schedules();
        $active = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $s_table WHERE status=%s", 'active'));
        $paused = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $s_table WHERE status=%s", 'paused'));
        $needs = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $s_table WHERE status=%s", 'needs_review'));
        $completed = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $s_table WHERE status=%s", 'completed'));
        $h_table = Database::table_history();
        $total_changes = (int) $wpdb->get_var("SELECT COUNT(*) FROM $h_table");
        $today = gmdate('Y-m-d');
        $today_changes = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $h_table WHERE DATE(created_at_gmt)=%s", $today));
        $failed = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $s_table WHERE status=%s", 'error'));

        return compact('total_posts','managed','active','paused','needs','completed','total_changes','today_changes','failed');
    }

    private function collect_warnings(array $kksr, array $views): array {
        $w = [];
        if ($kksr['status'] === 'error') $w[] = ['type'=>'error','msg'=>'سازگاری kk Star Ratings: خطا - ' . implode('، ', $kksr['messages'])];
        elseif ($kksr['status'] === 'warning') $w[] = ['type'=>'warning','msg'=>'سازگاری kk Star Ratings: ' . implode('، ', $kksr['messages'])];

        if (!$views['exists']) $w[] = ['type'=>'warning','msg'=>'هیچ نوشته‌ای دارای فیلد views نیست. ممکن است قالب از روش دیگری برای بازدید استفاده کند.'];
        if ($views['has_non_numeric']) $w[] = ['type'=>'warning','msg'=>'برخی مقادیر views غیرعددی هستند و نیاز به پاکسازی دارند.'];

        // بررسی WP-Cron
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            $w[] = ['type'=>'warning','msg'=>'WP-Cron غیرفعال است. زمان‌بندی دقیق نخواهد بود؛ Cron واقعی سرور را تنظیم کنید.'];
        }
        $next = wp_next_scheduled(Scheduler::CRON_HOOK);
        if (!$next) {
            // خودترمیم فوری — اگر با رونویسی فایل‌ها فعال شده و کرون جا مانده باشد
            Scheduler::ensure_cron();
            $next = wp_next_scheduled(Scheduler::CRON_HOOK);
        }
        if (!$next) $w[] = ['type'=>'warning','msg'=>'رویداد زمان‌بندی APEX فعال نیست. افزونه را غیرفعال/فعال کنید یا دکمه «ترمیم کرون» را در داشبورد بزنید.'];

        // مقادیر خارج از محدوده نمونه‌برداری
        global $wpdb;
        $bad_views = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='views' AND CAST(meta_value AS SIGNED) < 0");
        if ($bad_views) $w[] = ['type'=>'warning','msg'=> sprintf('%s نوشته دارای بازدید منفی است.', $bad_views)];

        return $w;
    }

    public function render_bulk(): void {
        $post_types = get_post_types(['public'=>true], 'objects');
        include APEX_OMEGA_DIR . 'admin/views/bulk.php';
    }

    public function render_history(): void {
        global $wpdb;
        $h = Database::table_history();
        $where = '1=1';
        if (!empty($_GET['filter_source'])) {
            $src = sanitize_text_field($_GET['filter_source']);
            $where .= $wpdb->prepare(' AND source=%s', $src);
        }
        if (!empty($_GET['filter_post'])) {
            $pid = (int)$_GET['filter_post'];
            $where .= $wpdb->prepare(' AND post_id=%d', $pid);
        }
        $recent = $wpdb->get_results("SELECT h.*, p.post_title FROM $h h LEFT JOIN {$wpdb->posts} p ON p.ID=h.post_id WHERE $where ORDER BY h.created_at_gmt DESC LIMIT 200", ARRAY_A) ?: [];
        include APEX_OMEGA_DIR . 'admin/views/history-table.php';
    }

    public function render_settings(): void {
        if (isset($_POST['apex_save_settings']) && check_admin_referer('apex_save_settings', '_wpnonce_settings')) {
            if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز');
            // 1) عمومی
            $input = [
                'large_drop_threshold_percent' => $_POST['large_drop_threshold_percent'] ?? 20,
                'max_default_step_views'       => $_POST['max_default_step_views'] ?? 500,
                'max_default_percent'          => $_POST['max_default_percent'] ?? 15,
                'enable_real_cron_hint'        => isset($_POST['enable_real_cron_hint']),
                'remove_data_on_uninstall'     => isset($_POST['remove_data_on_uninstall']),
                'never_decrease_views'         => isset($_POST['never_decrease_views']),
            ];
            update_option('apex_omega_settings', $this->sanitize_settings($input));

            // 2) هوش مصنوعی
            if (isset($_POST['apex_ai_api_key']) || isset($_POST['apex_ai_model'])) {
                \ApexOmega\Core\AI_Helper::save_settings([
                    'api_key'  => $_POST['apex_ai_api_key'] ?? '',
                    'model'    => $_POST['apex_ai_model'] ?? 'gpt-4o-mini',
                    'endpoint' => $_POST['apex_ai_endpoint'] ?? 'https://api.gapgpt.app/v1/chat/completions',
                ]);
            }

            // 3) هنرمندان و الگوی ریتم — بیس بدون تغییر
            if (isset($_POST['rhythm_artist_ratio']) || isset($_POST['rhythm_unknown_threshold'])) {
                \ApexOmega\Core\ArtistManager::save_rhythm_settings([
                    'artist_ratio'      => isset($_POST['rhythm_artist_ratio']) ? (float)$_POST['rhythm_artist_ratio']/100 : null,
                    'unknown_threshold' => $_POST['rhythm_unknown_threshold'] ?? null,
                    'unknown_cap'       => $_POST['rhythm_unknown_cap'] ?? null,
                    'require_pair'      => isset($_POST['rhythm_require_pair']),
                ]);
            }

            // 4) اتوماسیون رشد — متمرکز، بدون تکرار با هاب‌ها
            if (isset($_POST['evergreen_enabled']) || isset($_POST['evergreen_rate_percent'])) {
                \ApexOmega\Core\Evergreen::save_settings([
                    'enabled'      => isset($_POST['evergreen_enabled']),
                    'rate_percent' => $_POST['evergreen_rate_percent'] ?? null,
                    'max_views'    => $_POST['evergreen_max_views'] ?? null,
                ]);
            }
            if (isset($_POST['autowatch_enabled']) || isset($_POST['autowatch_threshold'])) {
                \ApexOmega\Core\AutoWatch::save_settings([
                    'enabled'          => isset($_POST['autowatch_enabled']),
                    'threshold'          => $_POST['autowatch_threshold'] ?? null,
                    'batch'              => $_POST['autowatch_batch'] ?? null,
                    'min_views'          => $_POST['autowatch_min_views'] ?? null,
                    'max_views'          => $_POST['autowatch_max_views'] ?? null,
                    'only_recent_days'   => $_POST['autowatch_only_recent_days'] ?? null,
                ]);
            }
            if (isset($_POST['commentor_enabled']) || isset($_POST['commentor_threshold'])) {
                \ApexOmega\Core\CommentGuardian::save_settings([
                    'enabled'          => isset($_POST['commentor_enabled']),
                    'threshold'        => $_POST['commentor_threshold'] ?? null,
                    'max_per_post'     => $_POST['commentor_max_per_post'] ?? null,
                    'min_comments'     => $_POST['commentor_min_comments'] ?? null,
                    'max_comments'     => $_POST['commentor_max_comments'] ?? null,
                    'daily_growth'     => $_POST['commentor_daily_growth'] ?? null,
                    'batch'            => $_POST['commentor_batch'] ?? null,
                    'only_recent_days' => $_POST['commentor_only_recent_days'] ?? null,
                ]);
            }

            // 5) همگام‌سازی وزن‌محور — کنترل کامل (جدید 2.2.6)
            if (isset($_POST['weight_sync_mode']) || isset($_POST['weight_sync_cooldown_hours'])) {
                \ApexOmega\Core\ArtistManager::save_weight_sync_settings([
                    'mode'               => $_POST['weight_sync_mode'] ?? null,
                    'cooldown_hours'     => $_POST['weight_sync_cooldown_hours'] ?? null,
                    'batch'              => $_POST['weight_sync_batch'] ?? null,
                    'exclude_recent_days'=> $_POST['weight_sync_exclude_recent_days'] ?? null,
                    'protect_selected'   => isset($_POST['weight_sync_protect_selected']),
                    'protect_manual'     => isset($_POST['weight_sync_protect_manual']),
                ]);
            }

            echo '<div class="notice notice-success"><p>تنظیمات ذخیره شد — همه بخش‌ها یک‌جا به‌روزرسانی شد.</p></div>';
        }
        $settings = wp_parse_args(get_option('apex_omega_settings', []), [
            'large_drop_threshold_percent' => 20,
            'max_default_step_views' => 500,
            'max_default_percent' => 15,
            'enable_real_cron_hint' => true,
            'remove_data_on_uninstall' => false,
            'never_decrease_views' => true,
        ]);
        $ai_set = \ApexOmega\Core\AI_Helper::get_settings();
        include APEX_OMEGA_DIR . 'admin/views/settings.php';
    }

    public function render_tier(): void {
        global $wpdb;
        include APEX_OMEGA_DIR . 'admin/views/tier-distribution.php';
    }

    public function render_artists(): void {
        global $wpdb;
        include APEX_OMEGA_DIR . 'admin/views/artists-hub.php';
    }
    public function render_automation(): void {
        global $wpdb;
        include APEX_OMEGA_DIR . 'admin/views/automation-hub.php';
    }
    public function render_weight_sync(): void {
        global $wpdb;
        include APEX_OMEGA_DIR . 'admin/views/weight-sync.php';
    }
    public function render_hits(): void {
        global $wpdb;
        include APEX_OMEGA_DIR . 'admin/views/hits-hub.php';
    }
    public function render_analytics(): void {
        global $wpdb;
        include APEX_OMEGA_DIR . 'admin/views/analytics.php';
    }

    public function render_help(): void {
        include APEX_OMEGA_DIR . 'admin/views/help.php';
    }
}

new Admin();
