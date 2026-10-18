<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

class Analytics {
    // توزیع تیره‌ها براساس _apex_tier meta
    public static function tier_distribution(): array {
        global $wpdb;
        $tiers = TierManager::get_tiers();
        $ids = array_column($tiers, 'id');
        $out = [];
        foreach ($ids as $tid) $out[$tid] = 0;
        $rows = $wpdb->get_results("SELECT meta_value as tier, COUNT(*) as cnt FROM {$wpdb->postmeta} WHERE meta_key='_apex_tier' GROUP BY meta_value", ARRAY_A);
        if ($rows) foreach ($rows as $r) {
            if (isset($out[$r['tier']])) $out[$r['tier']] = (int)$r['cnt'];
            else $out[$r['tier']] = (int)$r['cnt']; // custom tier
        }
        return $out;
    }

    // هیستوگرام بازدید: 8 bucket — سقف جدید 1.5M واقع‌گرایانه
    public static function views_histogram(): array {
        global $wpdb;
        $buckets = [
            '1K-10K'   => [1000,10000],
            '10K-30K'  => [10001,30000],
            '30K-80K' => [30001,80000],
            '80K-200K'=> [80001,200000],
            '200K-500K'=>[200001,500000],
            '500K-900K'=> [500001,900000],
            '900K-1.5M'=> [900001,1500000],
            '1.5M+'  => [1500001, 1000000000],
        ];
        $out = [];
        foreach ($buckets as $label=>$range) {
            $cnt = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='views' AND CAST(meta_value AS UNSIGNED) BETWEEN %d AND %d", $range[0], $range[1]));
            $out[$label] = $cnt;
        }
        return $out;
    }

    // timeline 14 روز اخیر تاریخچه
    public static function history_timeline(int $days=14): array {
        global $wpdb;
        $h = Database::table_history();
        $rows = $wpdb->get_results($wpdb->prepare("SELECT DATE(created_at_gmt) as d, COUNT(*) as c FROM $h WHERE created_at_gmt >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY) GROUP BY d ORDER BY d ASC", $days), ARRAY_A);
        $map = [];
        foreach ($rows as $r) $map[$r['d']] = (int)$r['c'];
        $out = [];
        for ($i=$days-1; $i>=0; $i--) {
            $d = gmdate('Y-m-d', time()-$i*DAY_IN_SECONDS);
            $out[$d] = $map[$d] ?? 0;
        }
        return $out;
    }

    // top posts by views
    public static function top_posts(int $limit=10): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT p.ID, p.post_title, CAST(pm.meta_value AS UNSIGNED) as views FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID AND pm.meta_key='views' WHERE p.post_type='post' AND p.post_status='publish' ORDER BY views DESC LIMIT %d", $limit), ARRAY_A);
        if (!$rows) return [];
        // add tier & kksr
        foreach ($rows as &$r) {
            $r['tier'] = get_post_meta($r['ID'], TierManager::META_TIER, true);
            $st = Adapter_KKSR::get_stats((int)$r['ID']);
            $r['casts'] = $st['casts'];
            $r['avg'] = $st['avg'];
        }
        return $rows;
    }

    public static function summary(): array {
        global $wpdb;
        $total_posts = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'");
        $with_views = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='views'");
        $avg_views = (float)$wpdb->get_var("SELECT AVG(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} WHERE meta_key='views'");
        $max_views = (int)$wpdb->get_var("SELECT MAX(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} WHERE meta_key='views'");
        $h = Database::table_history();
        $today = gmdate('Y-m-d');
        $today_changes = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $h WHERE DATE(created_at_gmt)=%s", $today));
        return [
            'total_posts'=>$total_posts,
            'with_views'=>$with_views,
            'avg_views'=> round($avg_views),
            'max_views'=>$max_views,
            'today_changes'=>$today_changes,
        ];
    }
}
