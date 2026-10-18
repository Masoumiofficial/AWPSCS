<?php
namespace ApexOmega\Admin;

if (!defined('ABSPATH')) exit;

class Bulk {
    public function __construct() {
        add_filter('bulk_actions-edit-post', [$this, 'bulk_actions']);
        add_filter('handle_bulk_actions-edit-post', [$this, 'handle_bulk'], 10, 3);
        // ستون سفارشی
        add_filter('manage_posts_columns', [$this, 'columns']);
        add_action('manage_posts_custom_column', [$this, 'column_content'], 10, 2);
        add_filter('manage_pages_columns', [$this, 'columns']);
        add_action('manage_pages_custom_column', [$this, 'column_content'], 10, 2);
    }

    public function bulk_actions(array $actions): array {
        $actions['apex_bulk'] = 'APEX Ω — ویرایش گروهی آمار';
        return $actions;
    }

    public function handle_bulk(string $redirect, string $action, array $post_ids) {
        if ($action !== 'apex_bulk') return $redirect;
        $redirect = add_query_arg(['apex_bulk_ids' => implode(',', $post_ids)], admin_url('admin.php?page=apex-omega-bulk'));
        return $redirect;
    }

    public function columns(array $cols): array {
        $cols['apex_views'] = 'بازدید APEX';
        $cols['apex_rating'] = 'امتیاز APEX';
        return $cols;
    }

    public function column_content(string $col, int $post_id): void {
        if ($col === 'apex_views') {
            $v = get_post_meta($post_id, 'views', true);
            echo esc_html($v !== '' ? number_format_i18n((int)$v) : '—');
        }
        if ($col === 'apex_rating') {
            $casts = get_post_meta($post_id, '_kksr_casts', true);
            $ratings = get_post_meta($post_id, '_kksr_ratings', true);
            $best = (int) get_option('kksr_stars', 5);
            $avg = $casts && $ratings ? round(((float)$ratings/(int)$casts)/5*$best,1) : 0;
            echo esc_html($avg ? $avg . ' (' . $casts . ' رأی)' : '—');
        }
    }
}

new Bulk();
