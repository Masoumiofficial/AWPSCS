<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

class Rest {
    const NS = 'apex-omega/v1';

    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'register']);
    }

    public static function register(): void {
        register_rest_route(self::NS, '/preview', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'preview'],
            'permission_callback' => [__CLASS__, 'can_manage'],
            'args' => [
                'post_id' => ['required'=>true, 'type'=>'integer'],
                'field_type' => ['required'=>true, 'type'=>'string'],
                'target_value' => ['required'=>true],
                'start_at_gmt' => ['required'=>true, 'type'=>'string'],
                'end_at_gmt' => ['required'=>true, 'type'=>'string'],
                'interval_seconds' => ['required'=>true, 'type'=>'integer'],
            ]
        ]);

        register_rest_route(self::NS, '/schedule', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'create_schedule'],
            'permission_callback' => [__CLASS__, 'can_manage'],
        ]);

        register_rest_route(self::NS, '/stats/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_stats'],
            'permission_callback' => [__CLASS__, 'can_manage'],
        ]);
    }

    public static function can_manage(): bool {
        return apex_omega_user_can_manage() && current_user_can('manage_options');
    }

    public static function preview(\WP_REST_Request $req) {
        $post_id = (int) $req->get_param('post_id');
        $field = sanitize_text_field($req->get_param('field_type'));
        $target = $req->get_param('target_value');
        $start = sanitize_text_field($req->get_param('start_at_gmt'));
        $end = sanitize_text_field($req->get_param('end_at_gmt'));
        $interval = (int) $req->get_param('interval_seconds');

        if (!get_post($post_id)) return new \WP_Error('not_found', 'نوشته یافت نشد.', ['status'=>404]);

        $preview = Scheduler::preview($post_id, $field, $target, $start, $end, $interval);
        return rest_ensure_response($preview);
    }

    public static function create_schedule(\WP_REST_Request $req) {
        $data = $req->get_json_params();
        if (!$data) $data = $req->get_params();

        // پاکسازی
        $clean = [
            'post_id' => isset($data['post_id']) ? (int)$data['post_id'] : 0,
            'field_type' => isset($data['field_type']) ? sanitize_text_field($data['field_type']) : '',
            'target_value' => $data['target_value'] ?? null,
            'interval_seconds' => isset($data['interval_seconds']) ? (int)$data['interval_seconds'] : 3600,
            'start_at_gmt' => isset($data['start_at_gmt']) ? sanitize_text_field($data['start_at_gmt']) : null,
            'end_at_gmt' => isset($data['end_at_gmt']) ? sanitize_text_field($data['end_at_gmt']) : null,
            'start_at' => isset($data['start_at_gmt']) ? get_date_from_gmt(sanitize_text_field($data['start_at_gmt']), 'Y-m-d H:i:s') : null,
            'end_at' => isset($data['end_at_gmt']) ? get_date_from_gmt(sanitize_text_field($data['end_at_gmt']), 'Y-m-d H:i:s') : null,
            'min_allowed' => $data['min_allowed'] ?? null,
            'max_allowed' => $data['max_allowed'] ?? null,
            'max_step' => $data['max_step'] ?? null,
            'max_percent_step' => $data['max_percent_step'] ?? null,
            'mode' => $data['mode'] ?? 'scheduled',
            'confirm_large_drop' => !empty($data['confirm_large_drop']),
            'confirm_volatility' => !empty($data['confirm_volatility']),
        ];
        $res = Scheduler::create_schedule($clean);
        if (empty($res['success'])) {
            return new \WP_Error('validation', $res['message'] ?? implode(' | ', $res['errors'] ?? $res['warnings'] ?? ['خطا']), ['status'=>400, 'data'=>$res]);
        }
        return rest_ensure_response($res);
    }

    public static function get_stats(\WP_REST_Request $req) {
        $id = (int) $req->get_param('id');
        $post = get_post($id);
        if (!$post) return new \WP_Error('not_found', 'نوشته یافت نشد.', ['status'=>404]);
        $kksr = Adapter_KKSR::get_stats($id);
        $views = Adapter_Views::get_views($id);
        $history = History::get_for_post($id, 10);
        $schedules = Scheduler::get_schedules_for_post($id);
        return rest_ensure_response([
            'post_id' => $id,
            'title' => $post->post_title,
            'views' => $views,
            'kksr' => $kksr,
            'history' => $history,
            'schedules' => $schedules,
        ]);
    }
}
