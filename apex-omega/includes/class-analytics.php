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
        // 1.9.3 PERF-02: یک کوئری به جای 8 — PHP bucketing
        $vals = $wpdb->get_col("SELECT CAST(meta_value AS UNSIGNED) FROM {$wpdb->postmeta} WHERE meta_key='views'");
        $out = array_fill_keys(array_keys($buckets), 0);
        foreach ($vals as $v) {
            $v=(int)$v;
            foreach ($buckets as $label=>$range) { if($v >= $range[0] && $v <= $range[1]){ $out[$label]++; break; } }
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
        // 1.9.3 PERF-03: batch meta để جلوگیری از N+1
        $ids = array_column($rows, 'ID');
        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $meta_rows = $wpdb->get_results($wpdb->prepare("SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ($placeholders) AND meta_key IN ('_apex_tier','_kksr_casts','_kksr_avg','_kksr_ratings','kksr_casts','kksr_avg')", ...$ids), ARRAY_A);
        $meta_map=[]; foreach($meta_rows as $mr){ $mid=(int)$mr['post_id']; $meta_map[$mid][$mr['meta_key']]=$mr['meta_value']; }
        foreach ($rows as &$r) {
            $mid=(int)$r['ID'];
            $r['tier'] = $meta_map[$mid][TierManager::META_TIER] ?? '';
            // fallback to get_stats logic but via cached map
            $r['casts'] = (int)($meta_map[$mid]['_kksr_casts'] ?? $meta_map[$mid]['kksr_casts'] ?? 0);
            $r['avg'] = (float)($meta_map[$mid]['_kksr_avg'] ?? $meta_map[$mid]['kksr_avg'] ?? 0);
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
