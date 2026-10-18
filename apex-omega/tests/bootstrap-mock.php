<?php
// Mock WordPress functions for standalone test run
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/../');

global $mock_postmeta, $mock_options, $mock_posts, $mock_tables;
$mock_postmeta = [];
$mock_options = ['kksr_stars'=>5, 'active_plugins'=>['kk-star-ratings/index.php']];
$mock_posts = [1=>['ID'=>1,'post_title'=>'آهنگ تست 1','post_type'=>'post','post_status'=>'publish']];
$mock_tables = ['history'=>[], 'schedules'=>[], 'locks'=>[]];

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key, $single=true){
        global $mock_postmeta;
        $v = $mock_postmeta[$post_id][$key] ?? null;
        if ($single) return $v ?? '';
        return $v !== null ? [$v] : [];
    }
}
if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id,$key,$value){ global $mock_postmeta; $mock_postmeta[$post_id][$key]=$value; return true; }
}
if (!function_exists('delete_post_meta')) {
    function delete_post_meta($post_id,$key){ global $mock_postmeta; unset($mock_postmeta[$post_id][$key]); return true; }
}
if (!function_exists('get_option')) {
    function get_option($k,$def=false){ global $mock_options; return $mock_options[$k] ?? $def; }
}
if (!function_exists('update_option')) {
    function update_option($k,$v){ global $mock_options; $mock_options[$k]=$v; return true; }
}
if (!function_exists('get_post')) {
    function get_post($id){ global $mock_posts; return isset($mock_posts[$id]) ? (object)$mock_posts[$id] : null; }
}
if (!function_exists('current_time')) {
    function current_time($type,$gmt=false){
        if($type==='mysql') return $gmt? gmdate('Y-m-d H:i:s') : date('Y-m-d H:i:s');
        return $gmt? gmdate('U'): time();
    }
}
if (!function_exists('get_date_from_gmt')) {
    function get_date_from_gmt($gmt,$format='Y-m-d H:i:s'){ $t=strtotime($gmt); return date($format, $t + 12600); } // +3:30 mock
}
if (!function_exists('wp_generate_password')) {
    function wp_generate_password($l=12,$s=false){ return bin2hex(random_bytes($l/2)); }
}
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($s){ return trim(strip_tags($s)); } }
if (!function_exists('number_format_i18n')) { function number_format_i18n($n){ return number_format($n); } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($d,$o=0){ return json_encode($d, $o); } }
if (!function_exists('esc_html')) { function esc_html($s){ return htmlspecialchars($s, ENT_QUOTES); } }
if (!function_exists('is_wp_error')) { function is_wp_error($x){ return $x instanceof WP_Error; } }
if (!class_exists('WP_Error')) { class WP_Error { public $code,$msg; function __construct($c,$m){$this->code=$c;$this->msg=$m;} } }
if (!function_exists('apex_omega_generate_uuid')) {
    function apex_omega_generate_uuid(): string {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
if (!function_exists('apex_omega_user_can_manage')) { function apex_omega_user_can_manage(){ return true; } }
if (!function_exists('get_current_user_id')) { function get_current_user_id(){ return 1; } }
