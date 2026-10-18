<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

class Exporter {
    private static function escape_csv($v): string {
        $v = (string)$v;
        if (preg_match('/^[\=\+\-\@\|\%]/', $v)) $v = "'" . $v; // CSV injection guard — OWASP
        // double quote escaping
        if (strpos($v, '"') !== false || strpos($v, ',') !== false || strpos($v, "\n") !== false) $v = '"' . str_replace('"', '""', $v) . '"';
        return $v;
    }
    // صادر کردن وزن هنرمندان به CSV
    public static function export_artist_csv(): string {
        $tax = ArtistManager::detect_artist_taxonomy();
        $artists = $tax ? ArtistManager::get_all_artists($tax, 1000) : [];
        $out = "term_id,name,slug,count,weight,tier_hint\n";
        foreach ($artists as $a) {
            $out .= implode(",", [
                self::escape_csv($a['term_id']),
                self::escape_csv($a['name']),
                self::escape_csv($a['slug']),
                self::escape_csv($a['count']),
                self::escape_csv($a['weight']),
                self::escape_csv($a['tier_hint']),
            ]) . "\n";
        }
        return $out;
    }

    public static function import_artist_csv(string $csv): array {
        $lines = explode("\n", trim($csv));
        if (count($lines) <2) return ['success'=>false,'message'=>'فایل خالی است'];
        $header = str_getcsv(array_shift($lines));
        $weights = [];
        $imported=0;
        foreach ($lines as $line) {
            if (trim($line)==='') continue;
            $row = str_getcsv($line);
            if (count($row) <5) continue;
            $assoc = array_combine($header, $row) ?: [];
            $tid = isset($assoc['term_id']) ? (int)$assoc['term_id'] : (isset($row[0]) ? (int)$row[0] : 0);
            $weight = isset($assoc['weight']) ? (int)$assoc['weight'] : (isset($row[4]) ? (int)$row[4] : 50);
            if ($tid) { $weights[$tid]= max(0,min(100,$weight)); $imported++; }
        }
        if ($weights) {
            ArtistManager::save_weights(array_merge(get_option(ArtistManager::OPTION_WEIGHTS, []), $weights));
        }
        return ['success'=>true,'imported'=>$imported,'weights'=>$weights];
    }

    // صادر کردن تاریخچه به CSV (1000 ردیف آخر)
    public static function export_history_csv(int $limit=1000): string {
        $rows = History::get_all_recent($limit);
        $out = "id,post_id,title,field_type,old_value,new_value,delta,source,user_id,execution_id,created_at_gmt\n";
        foreach ($rows as $r) {
            $out .= implode(",", [
                self::escape_csv($r['id']),
                self::escape_csv($r['post_id']),
                self::escape_csv($r['post_title']??''),
                self::escape_csv($r['field_type']),
                self::escape_csv(mb_substr($r['old_value']??'',0,80)),
                self::escape_csv(mb_substr($r['new_value']??'',0,80)),
                self::escape_csv($r['delta']??''),
                self::escape_csv($r['source']),
                self::escape_csv($r['user_id']??''),
                self::escape_csv(substr($r['execution_id']??'',0,8)),
                self::escape_csv($r['created_at_gmt']),
            ]) . "\n";
        }
        return $out;
    }

    // صادر کردن پیکربندی تیره‌ها
    public static function export_tiers_json(): string {
        $tiers = TierManager::get_tiers();
        return wp_json_encode($tiers, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }

    public static function import_tiers_json(string $json): array {
        $data = json_decode($json, true);
        if (!is_array($data) || empty($data)) return ['success'=>false,'message'=>'JSON نامعتبر'];
        // اعتبارسنجی سخت‌گیرانه — مو لای درز نرود
        if (count($data) < 3 || count($data) > 10) return ['success'=>false,'message'=>'تعداد دسته باید 3-10 باشد'];
        foreach ($data as $t) {
            if (empty($t['id']) || !isset($t['views_min']) || !isset($t['views_max']) || !isset($t['casts_min']) || !isset($t['avg_min'])) return ['success'=>false,'message'=>'ساختار tier نامعتبر: '.wp_json_encode($t,JSON_UNESCAPED_UNICODE)];
            $vm = (int)$t['views_max'];
            if ($vm > 2000000) return ['success'=>false,'message'=>'سقف هر دسته حداکثر 2M است (واقع‌گرایانه) — '.$t['id'].'='.$vm];
            if ((float)$t['avg_max'] > 5.0 || (float)$t['avg_min'] < 0) return ['success'=>false,'message'=>'میانگین نامعتبر: '.$t['id']];
        }
        TierManager::save_tiers($data);
        return ['success'=>true,'count'=>count($data)];
    }
}
