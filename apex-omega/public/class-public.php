<?php
namespace ApexOmega;

if (!defined('ABSPATH')) exit;

class Public_Plugin {
    public function __construct() {
        // در حال حاضر نیازی به تغییر فرانت‌اند نیست چون مستقیم متا را می‌نویسیم
        // اما برای شفافیت، می‌توانیم فیلتر نمایش بازدید را اضافه کنیم
        add_filter('apex_omega_display_views', [$this, 'filter_views'], 10, 2);
    }

    public function filter_views($views, $post_id) {
        return get_post_meta($post_id, 'views', true);
    }
}

if (!is_admin()) {
    new Public_Plugin();
}
