<?php
namespace ApexOmega\Core;

if (!defined('ABSPATH')) exit;

class Ajax {
    public static function init(): void {
        $actions = [
            'apex_save_manual',
            'apex_preview_schedule',
            'apex_create_schedule',
            'apex_pause_schedule',
            'apex_resume_schedule',
            'apex_cancel_schedule',
            'apex_rollback',
            'apex_bulk_apply',
            'apex_dismiss_notice',
        ];
        foreach ($actions as $a) {
            add_action('wp_ajax_' . $a, [__CLASS__, 'router']);
        }
    }

    public static function router(): void {
        $action = str_replace('wp_ajax_', '', current_action());
        // بررسی دسترسی
        if (!apex_omega_user_can_manage()) {
            wp_send_json_error(['message' => 'دسترسی غیرمجاز.'], 403);
        }
        // نانس
        $nonce = $_REQUEST['_ajax_nonce'] ?? $_REQUEST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'apex_omega_nonce')) {
            wp_send_json_error(['message' => 'نشانه امنیتی نامعتبر (nonce).'], 403);
        }

        switch ($action) {
            case 'apex_save_manual':
                self::save_manual();
                break;
            case 'apex_preview_schedule':
                self::preview_schedule();
                break;
            case 'apex_create_schedule':
                self::create_schedule();
                break;
            case 'apex_pause_schedule':
                self::pause_schedule();
                break;
            case 'apex_resume_schedule':
                self::resume_schedule();
                break;
            case 'apex_cancel_schedule':
                self::cancel_schedule();
                break;
            case 'apex_rollback':
                self::rollback();
                break;
            case 'apex_bulk_apply':
                self::bulk_apply();
                break;
            default:
                wp_send_json_error(['message' => 'اکشن نامعتبر'], 400);
        }
    }

    private static function save_manual(): void {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        if (!$post_id || !get_post($post_id)) wp_send_json_error(['message'=>'نوشته نامعتبر'], 400);

        // قفل
        $lock_key = "apex_manual_{$post_id}";
        if (!Database::acquire_lock($lock_key, 30)) {
            wp_send_json_error(['message'=>'عملیات دیگری در حال اجراست.'], 409);
        }

        $mode = isset($_POST['mode']) ? sanitize_text_field($_POST['mode']) : 'manual';
        $views = $_POST['views'] ?? null;
        $casts = $_POST['casts'] ?? null;
        $avg = $_POST['avg'] ?? null;
        $exec = apex_omega_generate_uuid();

        $results = [];
        $has_error = false;

        // بازدید
        if ($views !== null && $views !== '') {
            [$ok, $errs, $v] = Validator::validate_views($views);
            if (!$ok) {
                $results['views'] = ['success'=>false, 'errors'=>$errs];
                $has_error = true;
            } else {
                $old = Adapter_Views::get_views($post_id);
                // بررسی نوسان بدون تأیید؟
                $vol = Validator::check_volatility($old, $v, null, null);
                if ($vol['requires_confirm'] && empty($_POST['confirm_volatility'])) {
                    $results['views'] = ['success'=>false, 'requires_confirm'=>true, 'warnings'=>$vol['warnings'], 'message'=>'تغییر بازدید نیاز به تأیید دارد.'];
                    $has_error = true;
                } else {
                    $success = Adapter_Views::set_views($post_id, $v, 'manual', $exec);
                    if ($success) {
                        History::log($post_id, 'views', $old, $v, 'manual', get_current_user_id(), $exec);
                        $results['views'] = ['success'=>true, 'old'=>$old, 'new'=>$v];
                    } else {
                        $results['views'] = ['success'=>false, 'message'=>'خطا در ذخیره بازدید'];
                        $has_error = true;
                    }
                }
            }
        }

        // kk casts + avg
        if ($casts !== null && $casts !== '' || $avg !== null && $avg !== '') {
            $detect = Adapter_KKSR::detect();
            $best = $detect['best'];
            $stats = Adapter_KKSR::get_stats($post_id);

            $new_casts = $stats['casts'];
            $new_avg = $stats['avg'];

            if ($casts !== null && $casts !== '') {
                [$ok, $errs, $vc] = Validator::validate_casts($casts);
                if (!$ok) {
                    $results['casts'] = ['success'=>false, 'errors'=>$errs];
                    $has_error = true;
                } else {
                    $new_casts = $vc;
                }
            }
            if ($avg !== null && $avg !== '') {
                [$ok, $errs, $va] = Validator::validate_avg($avg, $best);
                if (!$ok) {
                    $results['avg'] = ['success'=>false, 'errors'=>$errs];
                    $has_error = true;
                } else {
                    $new_avg = $va;
                }
            }

            if (!$has_error || (!isset($results['casts']['success']) || $results['casts']['success']!==false) && (!isset($results['avg']['success']) || $results['avg']['success']!==false)) {
                // سازگاری
                $new_ratings = $new_avg * $new_casts / $best * 5;
                $cons_errors = Adapter_KKSR::validate_consistency($new_casts, $new_ratings, $new_avg, $best);
                if (!empty($cons_errors)) {
                    // هشدار اما اجازه ذخیره با تأیید
                    if (empty($_POST['confirm_inconsistency'])) {
                        $results['kksr'] = ['success'=>false, 'requires_confirm'=>true, 'warnings'=>$cons_errors, 'message'=>'ناسازگاری در داده‌های امتیاز؛ تأیید کنید.'];
                        $has_error = true;
                    }
                }
                if (!$has_error || !empty($_POST['confirm_inconsistency'])) {
                    $old_combined = ['casts'=>$stats['casts'],'ratings'=>$stats['ratings'],'avg'=>$stats['avg']];
                    $success = Adapter_KKSR::set_stats($post_id, $new_casts, $new_ratings, $new_avg, 'manual', $exec);
                    if ($success) {
                        History::log($post_id, 'kksr_combined', wp_json_encode($old_combined, JSON_UNESCAPED_UNICODE), wp_json_encode(['casts'=>$new_casts,'ratings'=>$new_ratings,'avg'=>$new_avg], JSON_UNESCAPED_UNICODE), 'manual', get_current_user_id(), $exec, null, ['best'=>$best]);
                        $results['kksr'] = ['success'=>true, 'old'=>$old_combined, 'new'=>['casts'=>$new_casts,'avg'=>$new_avg]];
                    } else {
                        $results['kksr'] = ['success'=>false, 'message'=>'خطا در ذخیره آمار امتیاز'];
                        $has_error = true;
                    }
                }
            }
        }

        update_post_meta($post_id, '_apex_mode', $mode);
        Database::release_lock($lock_key);

        if ($has_error && !isset($results['views']['success']) && !isset($results['kksr']['success'])) {
            // اگر هیچ موفقیتی نبود
            // اما اگر بخشی موفق بود، همچنان success کلی بفرست با جزئیات
        }

        wp_send_json_success(['results'=>$results, 'execution_id'=>$exec]);
    }

    private static function preview_schedule(): void {
        $post_id = (int) ($_POST['post_id'] ?? 0);
        $field = sanitize_text_field($_POST['field_type'] ?? '');
        $target = $_POST['target_value'] ?? null;
        $start_gmt = sanitize_text_field($_POST['start_at_gmt'] ?? '');
        $end_gmt = sanitize_text_field($_POST['end_at_gmt'] ?? '');
        $interval = (int) ($_POST['interval_seconds'] ?? 3600);

        if (!$post_id || !$field || $target===null) wp_send_json_error(['message'=>'پارامتر ناقص'],400);
        $preview = Scheduler::preview($post_id, $field, $target, $start_gmt, $end_gmt, $interval);
        wp_send_json_success($preview);
    }

    private static function create_schedule(): void {
        $data = [
            'post_id' => (int) ($_POST['post_id'] ?? 0),
            'field_type' => sanitize_text_field($_POST['field_type'] ?? ''),
            'target_value' => $_POST['target_value'] ?? null,
            'interval_seconds' => (int) ($_POST['interval_seconds'] ?? 3600),
            'start_at_gmt' => sanitize_text_field($_POST['start_at_gmt'] ?? ''),
            'end_at_gmt' => sanitize_text_field($_POST['end_at_gmt'] ?? ''),
            'start_at' => isset($_POST['start_at_gmt']) ? get_date_from_gmt(sanitize_text_field($_POST['start_at_gmt']), 'Y-m-d H:i:s') : null,
            'end_at' => isset($_POST['end_at_gmt']) ? get_date_from_gmt(sanitize_text_field($_POST['end_at_gmt']), 'Y-m-d H:i:s') : null,
            'min_allowed' => $_POST['min_allowed'] ?? null,
            'max_allowed' => $_POST['max_allowed'] ?? null,
            'max_step' => $_POST['max_step'] ?? null,
            'max_percent_step' => $_POST['max_percent_step'] ?? null,
            'require_confirm_large_drop' => !empty($_POST['require_confirm_large_drop']),
            'confirm_large_drop' => !empty($_POST['confirm_large_drop']),
            'confirm_volatility' => !empty($_POST['confirm_volatility']),
            'mode' => 'scheduled',
        ];
        if (isset($_POST['kksr_avg_target'])) $data['kksr_avg_target'] = $_POST['kksr_avg_target'];
        if (isset($_POST['kksr_casts_target'])) $data['kksr_casts_target'] = $_POST['kksr_casts_target'];

        $res = Scheduler::create_schedule($data);
        if (empty($res['success'])) {
            wp_send_json_error($res, 400);
        }
        wp_send_json_success($res);
    }

    private static function pause_schedule(): void {
        $id = (int) ($_POST['schedule_id'] ?? 0);
        if (!$id) wp_send_json_error(['message'=>'شناسه نامعتبر'],400);
        $ok = Scheduler::pause($id);
        if ($ok) wp_send_json_success(['message'=>'برنامه متوقف شد.']);
        wp_send_json_error(['message'=>'خطا در توقف'],500);
    }
    private static function resume_schedule(): void {
        $id = (int) ($_POST['schedule_id'] ?? 0);
        if (!$id) wp_send_json_error(['message'=>'شناسه نامعتبر'],400);
        $ok = Scheduler::resume($id);
        if ($ok) wp_send_json_success(['message'=>'برنامه ادامه یافت.']);
        wp_send_json_error(['message'=>'خطا'],500);
    }
    private static function cancel_schedule(): void {
        $id = (int) ($_POST['schedule_id'] ?? 0);
        if (!$id) wp_send_json_error(['message'=>'شناسه نامعتبر'],400);
        $ok = Scheduler::cancel($id);
        if ($ok) wp_send_json_success(['message'=>'برنامه لغو شد.']);
        wp_send_json_error(['message'=>'خطا'],500);
    }

    private static function rollback(): void {
        $history_id = (int) ($_POST['history_id'] ?? 0);
        if (!$history_id) wp_send_json_error(['message'=>'شناسه تاریخچه نامعتبر'],400);
        $res = History::rollback($history_id);
        if ($res['success']) wp_send_json_success($res);
        wp_send_json_error($res, 400);
    }

    private static function bulk_apply(): void {
        $post_ids = $_POST['post_ids'] ?? [];
        if (is_string($post_ids)) $post_ids = explode(',', $post_ids);
        $post_ids = array_map('intval', (array)$post_ids);
        $post_ids = array_filter($post_ids);
        if (empty($post_ids)) wp_send_json_error(['message'=>'هیچ نوشته‌ای انتخاب نشده'],400);

        $field = sanitize_text_field($_POST['field_type'] ?? '');
        $value = $_POST['value'] ?? null;
        $mode = sanitize_text_field($_POST['mode'] ?? 'manual');

        $exec = apex_omega_generate_uuid();
        $success_count = 0;
        $errors = [];

        foreach ($post_ids as $pid) {
            if (!get_post($pid)) { $errors[] = "نوشته $pid یافت نشد."; continue; }
            if ($field === 'views') {
                [$ok, $errs, $v] = Validator::validate_views($value);
                if (!$ok) { $errors[] = "پست $pid: " . implode(' ', $errs); continue; }
                $old = Adapter_Views::get_views($pid);
                if (Adapter_Views::set_views($pid, $v, 'bulk_manual', $exec)) {
                    History::log($pid, 'views', $old, $v, 'bulk_manual', get_current_user_id(), $exec);
                    update_post_meta($pid, '_apex_mode', $mode);
                    $success_count++;
                }
            } elseif (in_array($field, ['kksr_casts','kksr_avg'], true)) {
                $detect = Adapter_KKSR::detect();
                $best = $detect['best'];
                $stats = Adapter_KKSR::get_stats($pid);
                if ($field === 'kksr_casts') {
                    [$ok, $errs, $vc] = Validator::validate_casts($value);
                    if (!$ok) { $errors[] = "پست $pid: " . implode(' ', $errs); continue; }
                    $old = $stats;
                    $new_ratings = $stats['avg'] * $vc / $best *5;
                    if (Adapter_KKSR::set_stats($pid, $vc, $new_ratings, $stats['avg'], 'bulk_manual', $exec)) {
                        History::log($pid, 'kksr_casts', $stats['casts'], $vc, 'bulk_manual', get_current_user_id(), $exec);
                        $success_count++;
                    }
                } else {
                    [$ok, $errs, $va] = Validator::validate_avg($value, $best);
                    if (!$ok) { $errors[] = "پست $pid: " . implode(' ', $errs); continue; }
                    $new_ratings = $va * $stats['casts'] / $best *5;
                    if (Adapter_KKSR::set_stats($pid, $stats['casts'], $new_ratings, $va, 'bulk_manual', $exec)) {
                        History::log($pid, 'kksr_avg', $stats['avg'], $va, 'bulk_manual', get_current_user_id(), $exec);
                        $success_count++;
                    }
                }
                update_post_meta($pid, '_apex_mode', $mode);
            } else {
                $errors[] = "فیلد $field پشتیبانی نمی‌شود در حالت گروهی.";
            }
        }

        wp_send_json_success(['success_count'=>$success_count, 'errors'=>$errors, 'execution_id'=>$exec]);
    }
}
