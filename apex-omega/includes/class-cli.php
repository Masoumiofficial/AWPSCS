<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

/**
 * WP-CLI 2.0-alpha — backfill + relearn + queue
 * Usage: wp apex backfill --days=90 --dry-run
 *        wp apex relearn --limit=200
 *        wp apex queue --run=5
 *        wp apex stats
 */
class CLI {
    public static function register(): void {
        if (!defined('WP_CLI') || !WP_CLI) return;

        \WP_CLI::add_command('apex backfill', [__CLASS__, 'backfill']);
        \WP_CLI::add_command('apex relearn', [__CLASS__, 'relearn']);
        \WP_CLI::add_command('apex queue', [__CLASS__, 'queue']);
        \WP_CLI::add_command('apex stats', [__CLASS__, 'stats']);
        \WP_CLI::add_command('apex simulate', [__CLASS__, 'simulate']);
    }

    /**
     * Backfill timeseries from history (90d)
     */
    public static function backfill(array $args, array $assoc): void {
        $days = isset($assoc['days']) ? max(1, min(365, (int)$assoc['days'])) : 90;
        $dry = !empty($assoc['dry-run']);
        $batch = isset($assoc['batch']) ? max(100, min(5000, (int)$assoc['batch'])) : 1000;

        global $wpdb;
        $h = Database::table_history();
        $ts = Database::table_timeseries();

        if ($wpdb->get_var("SHOW TABLES LIKE '$ts'") !== $ts) {
            \WP_CLI::error("جدول timeseries وجود ندارد — اول افزونه را آپدیت کن (DB 2.0.0-alpha)");
        }

        $cutoff = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $h WHERE created_at_gmt >= %s AND field_type IN ('views','kksr_casts','kksr_avg')", $cutoff));
        \WP_CLI::log("Backfill: {$total} history rows from last {$days}d, batch {$batch} " . ($dry ? '[dry-run]' : ''));

        if ($dry) return;

        $offset = 0;
        $inserted = 0;
        while (true) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT post_id, field_type, new_value, created_at, created_at_gmt FROM $h WHERE created_at_gmt >= %s ORDER BY id ASC LIMIT %d OFFSET %d",
                $cutoff, $batch, $offset
            ), ARRAY_A);
            if (empty($rows)) break;

            foreach ($rows as $r) {
                $metric = $r['field_type'] === 'views' ? 'views' : ($r['field_type'] === 'kksr_casts' ? 'casts' : 'avg');
                // تاریخچه old/new به‌صورت رشته — برای timeseries مقدار new
                $val = is_numeric($r['new_value']) ? (float)$r['new_value'] : 0;
                // اگر JSON combined بود، skip (برای alpha)
                if (strpos($r['new_value'], '{') === 0) continue;
                $wpdb->insert($ts, [
                    'post_id' => (int)$r['post_id'],
                    'metric' => $metric,
                    'value' => $val,
                    'sampled_at' => $r['created_at'],
                    'sampled_at_gmt' => $r['created_at_gmt'],
                    'source' => 'backfill',
                    'granularity' => '1h',
                ]);
                $inserted++;
            }
            $offset += $batch;
            \WP_CLI::log("... {$inserted}/{$total}");
            if (count($rows) < $batch) break;
        }
        \WP_CLI::success("Backfill done: {$inserted} points inserted");
    }

    public static function relearn(array $args, array $assoc): void {
        $limit = isset($assoc['limit']) ? max(10, min(1000, (int)$assoc['limit'])) : 200;
        global $wpdb;
        $pf = Database::table_profiles();
        if ($wpdb->get_var("SHOW TABLES LIKE '$pf'") !== $pf) {
            \WP_CLI::error("جدول profiles وجود ندارد");
        }
        $tax = ArtistManager::detect_artist_taxonomy();
        if (!$tax) \WP_CLI::error("تاکسونومی آرتیست یافت نشد");
        $artists = ArtistManager::get_all_artists($tax, $limit);
        $n = 0;
        foreach ($artists as $a) {
            $termId = (int)$a['term_id'];
            $weight = (float)$a['weight'];
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $pf WHERE term_id=%d", $termId));
            $data = [
                'term_id' => $termId,
                'artist_name' => $a['name'],
                'weight' => $weight,
                'confidence' => min(0.95, $a['count']/20),
                'samples' => (int)$a['count'],
                'curve_json' => wp_json_encode(['growth'=>0.02+($weight/100)*0.03,'decay'=>0.002], JSON_UNESCAPED_UNICODE),
                'updated_at_gmt' => current_time('mysql', true),
            ];
            if ($existing) $wpdb->update($pf, $data, ['term_id'=>$termId]);
            else $wpdb->insert($pf, $data);
            $n++;
        }
        \WP_CLI::success("Relearn: {$n} profiles upserted from {$tax}");
    }

    public static function queue(array $args, array $assoc): void {
        $run = isset($assoc['run']) ? max(1, min(20, (int)$assoc['run'])) : 5;
        QueueWorker::tick($run);
        \WP_CLI::success("Queue tick: ran up to {$run}");
    }

    public static function stats(array $args, array $assoc): void {
        global $wpdb;
        $stats = Analytics::summary();
        \WP_CLI::log("Posts: {$stats['total_posts']} | with_views: {$stats['with_views']} | avg: {$stats['avg_views']}");
        foreach ([Database::table_timeseries(), Database::table_profiles(), Database::table_queue()] as $t) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$t'") === $t ? 'OK' : 'MISSING';
            $cnt = $exists==='OK' ? (int)$wpdb->get_var("SELECT COUNT(*) FROM $t") : 0;
            \WP_CLI::log("{$t}: {$exists} ({$cnt} rows)");
        }
    }

    public static function simulate(array $args, array $assoc): void {
        // wp apex simulate --tier1=10 --tier7=100 --days=14
        $counts = [];
        foreach (['tier_1','tier_2','tier_3','tier_4','tier_5','tier_6','tier_7'] as $k) {
            if (isset($assoc[$k])) $counts[$k] = (int)$assoc[$k];
            if (isset($assoc[str_replace('_','-',$k)])) $counts[$k] = (int)$assoc[str_replace('_','-',$k)];
        }
        if (empty($counts)) $counts = ['tier_1'=>5,'tier_2'=>10,'tier_7'=>50];
        $days = isset($assoc['days']) ? (int)$assoc['days'] : 14;
        $res = Simulator::preview_30d($counts, ['artist_aware'=>true], $days);
        if (empty($res['success'])) \WP_CLI::error($res['message'] ?? 'preview failed');
        \WP_CLI::log("Preview: exec {$res['execution_id']} confidence {$res['confidence']}");
        foreach ($res['series'] as $row) {
            $d = $row['day'];
            $vals = [];
            foreach ($counts as $k=>$v) $vals[] = "$k={$row[$k.'_avg']}";
            \WP_CLI::log("Day {$d}: " . implode(' ', $vals) . " capped={$row['capped']}");
        }
        \WP_CLI::success("Done");
    }
}
