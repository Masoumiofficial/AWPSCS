<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

class Exporter {
    // صادر کردن وزن هنرمندان به CSV
    public static function export_artist_csv(): string {
        $tax = ArtistManager::detect_artist_taxonomy();
        $artists = $tax ? ArtistManager::get_all_artists($tax, 1000) : [];
        $out = "term_id,name,slug,count,weight,tier_hint\n";
        foreach ($artists as $a) {
            $out .= sprintf("%d,\"%s\",%s,%d,%d,%s\n",
                $a['term_id'],
                str_replace('"','""',$a['name']),
                $a['slug'],
                $a['count'],
                $a['weight'],
                $a['tier_hint']
            );
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
            $out .= sprintf("%s,%d,\"%s\",%s,\"%s\",\"%s\",%s,%s,%s,%s,%s\n",
                $r['id'], $r['post_id'], str_replace('"','""',$r['post_title']??''),
                $r['field_type'],
                str_replace('"','""', substr($r['old_value']??'',0,80)),
                str_replace('"','""', substr($r['new_value']??'',0,80)),
                $r['delta']??'',
                $r['source'],
                $r['user_id']??'',
                substr($r['execution_id']??'',0,8),
                $r['created_at_gmt']
            );
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
        // اعتبارسنجی ساده: هر آیتم باید id و views_min/max داشته باشد
        foreach ($data as $t) {
            if (empty($t['id']) || !isset($t['views_min'])) return ['success'=>false,'message'=>'ساختار tier نامعتبر: '.wp_json_encode($t,JSON_UNESCAPED_UNICODE)];
        }
        TierManager::save_tiers($data);
        return ['success'=>true,'count'=>count($data)];
    }
}
