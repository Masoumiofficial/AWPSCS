<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

/**
 * REST v2 — 2.0-alpha
 * /wp-json/apex/v2/...
 */
class RestV2 {
    const NS = 'apex/v2';

    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'register']);
    }

    public static function register(): void {
        register_rest_route(self::NS, '/stats', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'stats'],
            'permission_callback' => [__CLASS__, 'can_manage'],
        ]);

        register_rest_route(self::NS, '/simulate/preview', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'simulate_preview'],
            'permission_callback' => [__CLASS__, 'can_manage'],
            'args' => [
                'counts' => ['required' => true, 'type' => 'object'],
                'filters' => ['required' => false, 'type' => 'object'],
                'horizon_days' => ['required' => false, 'type' => 'integer'],
            ]
        ]);

        register_rest_route(self::NS, '/timeseries', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'timeseries'],
            'permission_callback' => [__CLASS__, 'can_manage'],
            'args' => [
                'post_id' => ['required' => true, 'type' => 'integer'],
                'metric' => ['required' => false, 'type' => 'string'],
                'granularity' => ['required' => false, 'type' => 'string'],
                'range' => ['required' => false, 'type' => 'string'],
            ]
        ]);

        register_rest_route(self::NS, '/queue', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'queue'],
            'permission_callback' => [__CLASS__, 'can_manage'],
        ]);

        register_rest_route(self::NS, '/profiles/relearn', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'relearn'],
            'permission_callback' => [__CLASS__, 'can_manage'],
        ]);
    }

    public static function can_manage(): bool {
        return apex_omega_user_can_manage();
    }

    public static function stats(\WP_REST_Request $req) {
        return rest_ensure_response([
            'version' => '2.0.0-alpha',
            'db_version' => Database::VERSION,
            'summary' => Analytics::summary(),
            'tiers' => Analytics::tier_distribution(),
            'histogram' => Analytics::views_histogram(),
            'queue_pending' => self::queue_count('pending'),
            'profiles' => self::profiles_count(),
        ]);
    }

    public static function simulate_preview(\WP_REST_Request $req) {
        $body = $req->get_json_params();
        if (!$body) $body = $req->get_params();
        $counts = $body['counts'] ?? [];
        $filters = $body['filters'] ?? [];
        $horizon = isset($body['horizon_days']) ? max(1, min(90, (int) $body['horizon_days'])) : 30;

        // sanitize counts
        $clean = [];
        foreach ((array) $counts as $k => $v) {
            $k = sanitize_text_field($k);
            $clean[$k] = max(0, (int) $v);
        }
        $filtersClean = [
            'category' => sanitize_text_field($filters['category'] ?? 'all'),
            'post_type' => sanitize_text_field($filters['post_type'] ?? 'any'),
            'artist_aware' => !empty($filters['artist_aware']),
        ];

        if (empty($clean)) return new \WP_Error('bad_request', 'counts خالی است', ['status' => 400]);

        $res = Simulator::preview_30d($clean, $filtersClean, $horizon);
        if (empty($res['success'])) return new \WP_Error('bad_request', $res['message'] ?? 'خطا', ['status' => 400]);
        return rest_ensure_response($res);
    }

    public static function timeseries(\WP_REST_Request $req) {
        $postId = (int) $req->get_param('post_id');
        $metric = sanitize_text_field($req->get_param('metric') ?: 'views');
        $gran = sanitize_text_field($req->get_param('granularity') ?: '5m');
        $range = sanitize_text_field($req->get_param('range') ?: '7d');

        if (!get_post($postId)) return new \WP_Error('not_found', 'پست یافت نشد', ['status' => 404]);

        global $wpdb;
        $table = Database::table_timeseries();
        // parse range: 7d, 30d, 90d
        $days = 7;
        if (preg_match('/^(\d+)d$/', $range, $m)) $days = max(1, min(365, (int) $m[1]));

        $cutoff = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT value, sampled_at_gmt, granularity, source FROM $table WHERE post_id=%d AND metric=%s AND sampled_at_gmt >= %s AND granularity=%s ORDER BY sampled_at_gmt ASC LIMIT 2000",
            $postId, $metric, $cutoff, $gran
        ), ARRAY_A);

        return rest_ensure_response([
            'post_id' => $postId,
            'metric' => $metric,
            'granularity' => $gran,
            'range' => $range,
            'points' => $rows ?: [],
            'count' => count($rows ?: []),
        ]);
    }

    public static function queue(\WP_REST_Request $req) {
        global $wpdb;
        $table = Database::table_queue();
        $rows = $wpdb->get_results("SELECT job_id, type, status, priority, attempts, max_attempts, next_retry_gmt, created_at_gmt FROM $table ORDER BY priority DESC, created_at_gmt DESC LIMIT 50", ARRAY_A);
        return rest_ensure_response(['queue' => $rows ?: []]);
    }

    public static function relearn(\WP_REST_Request $req) {
        // stub: run profile relearn sync
        $count = 0;
        global $wpdb;
        $pf = Database::table_profiles();
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $pf");
        return rest_ensure_response(['success' => true, 'profiles' => $count, 'message' => 'relearn queued (alpha stub)']);
    }

    private static function queue_count(string $status): int {
        global $wpdb;
        $table = Database::table_queue();
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE status=%s", $status));
    }

    private static function profiles_count(): int {
        global $wpdb;
        $table = Database::table_profiles();
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }
}
