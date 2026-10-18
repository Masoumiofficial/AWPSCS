<?php
namespace ApexOmega\Admin;

if (!defined('ABSPATH')) exit;

use ApexOmega\Core\Adapter_KKSR;
use ApexOmega\Core\Adapter_Views;
use ApexOmega\Core\History;
use ApexOmega\Core\Scheduler;

class Metabox {
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_metabox']);
        add_action('save_post', [$this, 'save_post'], 10, 2);
    }

    public function add_metabox(): void {
        $post_types = get_post_types(['public'=>true]);
        // حذف انواع سیستمی ناخواسته
        unset($post_types['attachment']);
        foreach ($post_types as $pt) {
            add_meta_box(
                'apex-omega-metabox',
                'APEX Ω — مدیریت آمار',
                [$this, 'render'],
                $pt,
                'normal',
                'high'
            );
        }
    }

    public function render(\WP_Post $post): void {
        if (!apex_omega_user_can_manage()) {
            echo '<p>شما دسترسی مدیریت آمار را ندارید.</p>';
            return;
        }
        wp_nonce_field('apex_omega_metabox', '_apex_nonce');

        $post_id = $post->ID;
        $views = Adapter_Views::get_views($post_id);
        $kksr = Adapter_KKSR::get_stats($post_id);
        $mode = get_post_meta($post_id, '_apex_mode', true) ?: 'manual';
        $last_history = History::get_for_post($post_id, 5);
        $schedules = Scheduler::get_schedules_for_post($post_id);
        $detect = Adapter_KKSR::detect();

        include APEX_OMEGA_DIR . 'admin/views/metabox.php';
    }

    public function save_post(int $post_id, \WP_Post $post): void {
        // فقط در ویرایش دستی؛ زمان‌بندی از Ajax جدا است
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
        if (!isset($_POST['_apex_nonce']) || !wp_verify_nonce($_POST['_apex_nonce'], 'apex_omega_metabox')) return;
        if (!apex_omega_user_can_manage($post_id)) return;

        // حالت مدیریت را ذخیره کن (بدون دخالت در آمار اصلی مگر از Ajax)
        if (isset($_POST['_apex_mode'])) {
            $mode = sanitize_text_field($_POST['_apex_mode']);
            if (in_array($mode, ['manual','scheduled','disabled'], true)) {
                update_post_meta($post_id, '_apex_mode', $mode);
            }
        }
    }
}

new Metabox();
