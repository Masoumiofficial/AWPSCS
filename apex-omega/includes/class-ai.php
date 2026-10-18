<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

class AI_Helper {
    const OPTION_KEY = 'apex_omega_ai_settings';

    public static function get_settings(): array {
        $defaults = [
            'provider' => 'gapgpt', // gapgpt | openai | custom
            'api_key' => '',
            'model' => 'gpt-4o-mini',
            'endpoint' => 'https://api.gapgpt.app/v1/chat/completions',
        ];
        $saved = get_option(self::OPTION_KEY, []);
        if (!is_array($saved)) $saved = [];
        $merged = wp_parse_args($saved, $defaults);
        // decrypt api_key if encrypted
        if (!empty($merged['api_key'])) {
            $dec = apex_omega_decrypt($merged['api_key']);
            // if decrypt returns same string but it was plain legacy, keep it
            // detect new format: enc1: or b64:
            if (strpos($merged['api_key'], 'enc1:') === 0 || strpos($merged['api_key'], 'b64:') === 0) {
                $merged['api_key_raw'] = $merged['api_key'];
                $merged['api_key'] = $dec;
            } else {
                // legacy plain: keep as is, but also provide raw for display
                $merged['api_key_raw'] = $merged['api_key'];
            }
            // masked for UI
            $merged['api_key_masked'] = $merged['api_key'] ? '••••••••' : '';
        } else {
            $merged['api_key_masked'] = '';
            $merged['api_key_raw'] = '';
        }
        return $merged;
    }

    public static function save_settings(array $data): void {
        $cur = self::get_settings();
        $raw_key = $data['api_key'] ?? '';
        // handle masked UI value
        if ($raw_key === '••••••••' || $raw_key === '') {
            if (empty($data['api_key']) || $data['api_key'] === '••••••••') {
                $raw_key = $cur['api_key'];
            }
        }
        // encrypt before save
        $to_store = $raw_key !== '' ? apex_omega_encrypt(sanitize_text_field($raw_key)) : '';
        // if key is already encrypted format coming from external, don't double encrypt
        if ($raw_key !== '' && (strpos($raw_key, 'enc1:')===0 || strpos($raw_key, 'b64:')===0)) {
            $to_store = $raw_key;
        }
        $new = [
            'provider' => sanitize_text_field($data['provider'] ?? $cur['provider']),
            'api_key' => $to_store,
            'model' => sanitize_text_field($data['model'] ?? $cur['model']),
            'endpoint' => esc_url_raw($data['endpoint'] ?? $cur['endpoint']),
        ];
        // اگر کلید خالی آمد، قبلی را نگه دار (برای امنیت فرم)
        if ($to_store === '' && !empty($cur['api_key_raw'])) {
            $new['api_key'] = $cur['api_key_raw'];
        }
        update_option(self::OPTION_KEY, $new, false);
    }

    public static function is_configured(): bool {
        $s = self::get_settings();
        return !empty($s['api_key']) && !empty($s['endpoint']);
    }

    public static function test_connection(): array {
        $s = self::get_settings();
        if (empty($s['api_key'])) return ['success'=>false,'message'=>'کلید GapGPT تنظیم نشده'];
        $payload = [
            'model' => $s['model'] ?: 'gpt-4o-mini',
            'messages' => [
                ['role'=>'system','content'=>'You are a test. Reply with single word: ok'],
                ['role'=>'user','content'=>'ping'],
            ],
            'max_tokens'=>5,
            'temperature'=>0,
        ];
        $resp = wp_remote_post($s['endpoint'], [
            'headers'=>['Authorization'=>'Bearer '.$s['api_key'],'Content-Type'=>'application/json'],
            'body'=>wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout'=>12,
        ]);
        if (is_wp_error($resp)) return ['success'=>false,'message'=>'خطا: '.$resp->get_error_message()];
        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        if ($code === 200) return ['success'=>true,'message'=>'اتصال موفق — GapGPT پاسخ داد','code'=>$code];
        $data = json_decode($body,true);
        $msg = $data['error']['message'] ?? $data['message'] ?? substr($body,0,300);
        return ['success'=>false,'message'=>'کد '.$code.': '.$msg,'code'=>$code,'raw'=>substr($body,0,800)];
    }

    /**
     * پیشنهاد توزیع هوشمند با AI
     * ورودی: تعداد کل آثار، اطلاعات دسته‌ها
     * خروجی: counts per tier + توضیح
     */
    public static function suggest_distribution(int $total_posts, array $tiers): array {
        $settings = self::get_settings();
        if (empty($settings['api_key'])) {
            // fallback به هرم پیش‌فرض
            return [
                'success' => true,
                'source' => 'fallback_pyramid',
                'suggestion' => TierManager::suggested_pyramid($total_posts),
                'explanation' => 'کلید AI تنظیم نشده — از الگوی هرم استاندارد (2% الماس، 25% تازه) استفاده شد.',
            ];
        }

        // ساخت پرامپت فارسی حرفه‌ای
        $tiers_desc = "";
        foreach ($tiers as $t) {
            $tiers_desc .= sprintf("- %s (%s): %s تا %s بازدید، میانگین %s تا %s، %s تا %s رأی\n",
                $t['label'], $t['id'], number_format_i18n($t['views_min']), number_format_i18n($t['views_max']),
                $t['avg_min'], $t['avg_max'], number_format_i18n($t['casts_min']), number_format_i18n($t['casts_max'])
            );
        }

        $prompt = "تو یک متخصص سئو و تحلیل داده برای سایت موسیقی ایرانی هستی.\n";
        $prompt .= "سایت $total_posts آهنگ دارد.\n";
        $prompt .= "۷ دسته داریم:\n$tiers_desc\n";
        $prompt .= "یک توزیع منطقی پیشنهاد بده که هرم طبیعی داشته باشد (تعداد کم در پرطرفدار، زیاد در تازه). ";
        $prompt .= "خروجی فقط JSON باشد با کلید tier_1 تا tier_7 و مجموع دقیقاً $total_posts. ";
        $prompt .= "مثال: {\"tier_1\": 5, \"tier_2\": 12, ...} و یک جمله توضیح کوتاه به فارسی.";

        $payload = [
            'model' => $settings['model'] ?: 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'تو یک تحلیلگر داده حرفه‌ای هستی. فقط JSON معتبر برگردان.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.7,
            'max_tokens' => 800,
        ];

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $settings['api_key'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout' => 20,
            'data_format' => 'body',
        ];

        $resp = wp_remote_post($settings['endpoint'], $args);

        if (is_wp_error($resp)) {
            return [
                'success' => false,
                'source' => 'api_error',
                'message' => 'خطا در تماس با AI: ' . $resp->get_error_message(),
                'fallback' => TierManager::suggested_pyramid($total_posts),
            ];
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);

        if ($code !== 200 || empty($data['choices'][0]['message']['content'])) {
            return [
                'success' => false,
                'source' => 'api_invalid',
                'message' => 'پاسخ AI نامعتبر بود (کد '.$code.')',
                'raw' => substr($body, 0, 500),
                'fallback' => TierManager::suggested_pyramid($total_posts),
            ];
        }

        $content = $data['choices'][0]['message']['content'];
        // استخراج JSON از متن
        $json = null;
        if (preg_match('/\{.*\}/s', $content, $m)) {
            $json = json_decode($m[0], true);
        } else {
            $json = json_decode(trim($content), true);
        }

        if (!is_array($json)) {
            return [
                'success' => false,
                'source' => 'parse_error',
                'message' => 'JSON پیشنهادی قابل خواندن نبود.',
                'raw_content' => $content,
                'fallback' => TierManager::suggested_pyramid($total_posts),
            ];
        }

        // اعتبارسنجی
        $clean = [];
        $sum = 0;
        foreach (['tier_1','tier_2','tier_3','tier_4','tier_5','tier_6','tier_7'] as $k) {
            $v = isset($json[$k]) ? (int)$json[$k] : 0;
            if ($v < 0) $v = 0;
            $clean[$k] = $v;
            $sum += $v;
        }
        // اگر جمع نشد، نرمالایز
        if ($sum !== $total_posts && $sum > 0) {
            $factor = $total_posts / $sum;
            foreach ($clean as $k=>$v) $clean[$k] = (int) round($v * $factor);
            // اصلاح باقی‌مانده
            $new_sum = array_sum($clean);
            $diff = $total_posts - $new_sum;
            if ($diff !== 0) $clean['tier_7'] += $diff;
        }

        return [
            'success' => true,
            'source' => 'gapgpt',
            'suggestion' => $clean,
            'explanation' => $content,
            'raw' => $data,
        ];
    }

    /**
     * پیشنهاد مقدار برای یک آهنگ خاص (عنوان + دسته)
     */
    public static function suggest_single(string $title, array $tier): array {
        $settings = self::get_settings();
        if (empty($settings['api_key'])) return ['success'=>false,'message'=>'کلید AI نیست'];

        $prompt = "برای آهنگ با عنوان \"$title\" که در دسته \"{$tier['label']}\" (بازدید {$tier['views_min']} تا {$tier['views_max']}) قرار دارد، یک مقدار بازدید واقع‌گرایانه، تعداد رأی و میانگین پیشنهاد بده. خروجی JSON: {\"views\": 12345, \"casts\": 123, \"avg\": 4.5}";

        $payload = [
            'model' => $settings['model'],
            'messages' => [
                ['role'=>'system','content'=>'فقط JSON برگردان'],
                ['role'=>'user','content'=>$prompt],
            ],
            'temperature'=>0.8,
        ];
        $resp = wp_remote_post($settings['endpoint'], [
            'headers'=>['Authorization'=>'Bearer '.$settings['api_key'],'Content-Type'=>'application/json'],
            'body'=>wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout'=>15,
        ]);
        if (is_wp_error($resp)) return ['success'=>false,'message'=>$resp->get_error_message()];
        $body = wp_remote_retrieve_body($resp);
        $data = json_decode($body,true);
        $content = $data['choices'][0]['message']['content'] ?? '';
        if (preg_match('/\{.*\}/s',$content,$m)) {
            $j=json_decode($m[0],true);
            if ($j) return ['success'=>true,'data'=>$j,'raw'=>$content];
        }
        return ['success'=>false,'message'=>'پاسخ نامعتبر','raw'=>$content];
    }
}
