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
            'apex_preview_tier',
            'apex_apply_tier',
            'apex_undo_tier',
            'apex_ai_suggest',
            'apex_ai_save',
            'apex_test_ai',
            'apex_save_artist_weights',
            'apex_save_artist_taxonomy',
            'apex_auto_artist_ai',
            'apex_auto_artist_ir',
            'apex_export_artist_csv',
            'apex_export_history_csv',
            'apex_export_tiers_json',
            'apex_import_artist_csv',
            'apex_import_tiers_json',
            'apex_save_evergreen',
            'apex_save_rhythm',
            'apex_save_autowatch',
            'apex_save_commentor',
            'apex_run_commentor_now',
            'apex_send_post_comments',
            'apex_commentor_diag',
            'apex_dismiss_notice',
            'apex_start_tier_job',
            'apex_process_tier_chunk',
            'apex_tier_job_status',
            'apex_preview_weight_sync',
            'apex_apply_weight_sync',
            'apex_start_weight_sync_job',
            'apex_process_weight_chunk',
            'apex_toggle_hit',
            'apex_save_hits',
            'apex_suggest_hits',
            'apex_clear_hits',
            'apex_apply_premium_selected',
            'apex_reseed_artist',
            'apex_apply_comments_selected',
            'apex_comment_stats_selected',
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
        $nonce = sanitize_text_field($_REQUEST['_ajax_nonce'] ?? $_REQUEST['nonce'] ?? ''); // 2.0-beta SEC-02 sanitize
        // پاکسازی کامل بافر قبل از JSON — جلوگیری از Warning HTML که باعث SyntaxError < می‌شود (فیکس 1.9.1)
        while (ob_get_level() > 0) { @ob_end_clean(); }
        @ob_start();
        $valid = wp_verify_nonce($nonce, 'apex_omega_nonce') || wp_verify_nonce($nonce, 'apex-omega-nonce');
        if (!$valid) {
            // nonce را با هر دو فرمت قبول کن — داشبورد dash می‌فرستد، admin.js underscore
            wp_send_json_error(['message' => 'نشانه امنیتی نامعتبر (nonce) — صفحه را رفرش کنید.'], 403);
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
            case 'apex_preview_tier':
                self::preview_tier();
                break;
            case 'apex_apply_tier':
                self::apply_tier();
                break;
            case 'apex_ai_suggest':
                self::ai_suggest();
                break;
            case 'apex_ai_save':
                self::ai_save();
                break;
            case 'apex_save_artist_weights':
                self::save_artist_weights();
                break;
            case 'apex_auto_artist_ai':
                self::auto_artist_ai();
                break;
            case 'apex_auto_artist_ir':
                self::auto_artist_ir();
                break;
            case 'apex_test_ai':
                self::test_ai();
                break;
            case 'apex_save_artist_taxonomy':
                self::save_artist_taxonomy();
                break;
            case 'apex_undo_tier':
                self::undo_tier();
                break;
            case 'apex_export_artist_csv':
                self::export_artist_csv();
                break;
            case 'apex_export_history_csv':
                self::export_history_csv();
                break;
            case 'apex_export_tiers_json':
                self::export_tiers_json();
                break;
            case 'apex_import_artist_csv':
                self::import_artist_csv();
                break;
            case 'apex_import_tiers_json':
                self::import_tiers_json();
                break;
            case 'apex_save_evergreen':
                self::save_evergreen();
                break;
            case 'apex_save_rhythm':
                self::save_rhythm();
                break;
            case 'apex_save_autowatch':
                self::save_autowatch();
                break;
            case 'apex_save_commentor':
                self::save_commentor();
                break;
            case 'apex_run_commentor_now':
                self::run_commentor_now();
                break;
            case 'apex_send_post_comments':
                self::send_post_comments();
                break;
            case 'apex_commentor_diag':
                self::commentor_diag();
                break;
            case 'apex_start_tier_job':
                self::start_tier_job();
                break;
            case 'apex_process_tier_chunk':
                self::process_tier_chunk();
                break;
            case 'apex_tier_job_status':
                self::tier_job_status();
                break;
            case 'apex_preview_weight_sync':
                self::preview_weight_sync();
                break;
            case 'apex_apply_weight_sync':
                self::apply_weight_sync();
                break;
            case 'apex_start_weight_sync_job':
                self::start_weight_sync_job();
                break;
            case 'apex_process_weight_chunk':
                self::process_weight_chunk();
                break;
            case 'apex_toggle_hit':
                self::toggle_hit();
                break;
            case 'apex_save_hits':
                self::save_hits();
                break;
            case 'apex_suggest_hits':
                self::suggest_hits();
                break;
            case 'apex_clear_hits':
                self::clear_hits();
                break;
            case 'apex_apply_premium_selected':
                self::apply_premium_selected();
                break;
            case 'apex_reseed_artist':
                self::reseed_artist();
                break;
            case 'apex_apply_comments_selected':
                self::apply_comments_selected();
                break;
            case 'apex_comment_stats_selected':
                self::comment_stats_selected();
                break;
            default:
                wp_send_json_error(['message' => 'اکشن نامعتبر'], 400);
        }
    }

    private static function save_manual(): void {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        if (!$post_id || !get_post($post_id)) wp_send_json_error(['message'=>'نوشته نامعتبر'], 400);
        if (!current_user_can('edit_post', $post_id) && !apex_omega_user_can_manage()) wp_send_json_error(['message'=>'دسترسی ویرایش نوشته ندارید'], 403);

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
            'min_allowed' => (isset($_POST['min_allowed']) && $_POST['min_allowed'] !== '' && (int)$_POST['min_allowed'] > 0) ? $_POST['min_allowed'] : null,
            'max_allowed' => (isset($_POST['max_allowed']) && $_POST['max_allowed'] !== '' && (int)$_POST['max_allowed'] > 0) ? $_POST['max_allowed'] : null,
            'max_step' => (isset($_POST['max_step']) && $_POST['max_step'] !== '' && (int)$_POST['max_step'] > 0) ? $_POST['max_step'] : null,
            'max_percent_step' => (isset($_POST['max_percent_step']) && $_POST['max_percent_step'] !== '' && (float)$_POST['max_percent_step'] > 0) ? $_POST['max_percent_step'] : null,
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
        $range_min = $_POST['range_min'] ?? null;
        $range_max = $_POST['range_max'] ?? null;
        $mode = sanitize_text_field($_POST['mode'] ?? 'manual');

        $is_range = ($range_min !== null && $range_min !== '' && $range_max !== null && $range_max !== '');
        $exec = apex_omega_generate_uuid();
        $success_count = 0;
        $errors = [];

        if ($is_range) {
            // اعتبارسنجی بازه یک‌بار قبل از حلقه
            if ($field === 'views') {
                [$ok1, $errs1, $vmin] = Validator::validate_views($range_min);
                [$ok2, $errs2, $vmax] = Validator::validate_views($range_max);
                if (!$ok1) wp_send_json_error(['message'=>'حداقل بازدید نامعتبر: '.implode(' ', $errs1)],400);
                if (!$ok2) wp_send_json_error(['message'=>'حداکثر بازدید نامعتبر: '.implode(' ', $errs2)],400);
                if ($vmin > $vmax) wp_send_json_error(['message'=>'حداقل نمی‌تواند بیشتر از حداکثر باشد ('.number_format_i18n($vmin).' > '.number_format_i18n($vmax).')'],400);
            } elseif ($field === 'kksr_casts') {
                [$ok1, $errs1, $vmin] = Validator::validate_casts($range_min);
                [$ok2, $errs2, $vmax] = Validator::validate_casts($range_max);
                if (!$ok1) wp_send_json_error(['message'=>'حداقل رأی نامعتبر: '.implode(' ', $errs1)],400);
                if (!$ok2) wp_send_json_error(['message'=>'حداکثر رأی نامعتبر: '.implode(' ', $errs2)],400);
                if ($vmin > $vmax) wp_send_json_error(['message'=>'حداقل رأی بیشتر از حداکثر است'],400);
            } elseif ($field === 'kksr_avg') {
                $detect = Adapter_KKSR::detect(); $best = $detect['best'];
                [$ok1, $errs1, $vmin] = Validator::validate_avg($range_min, $best);
                [$ok2, $errs2, $vmax] = Validator::validate_avg($range_max, $best);
                if (!$ok1) wp_send_json_error(['message'=>'حداقل میانگین نامعتبر: '.implode(' ', $errs1)],400);
                if (!$ok2) wp_send_json_error(['message'=>'حداکثر میانگین نامعتبر: '.implode(' ', $errs2)],400);
                if ($vmin > $vmax) wp_send_json_error(['message'=>'حداقل میانگین بیشتر از حداکثر است'],400);
            } else {
                wp_send_json_error(['message'=>"فیلد $field پشتیبانی نمی‌شود در حالت بازه"],400);
            }
        }

        foreach ($post_ids as $pid) {
            if (!get_post($pid)) { $errors[] = "نوشته $pid یافت نشد."; continue; }
            if (!current_user_can('edit_post', $pid) && !apex_omega_user_can_manage()) { $errors[] = "دسترسی ویرایش پست $pid ندارید."; continue; }
            if ($field === 'views') {
                if ($is_range) {
                    [$ok1, $errs1, $vmin] = Validator::validate_views($range_min);
                    [$ok2, $errs2, $vmax] = Validator::validate_views($range_max);
                    $v = mt_rand((int)$vmin, (int)$vmax);
                } else {
                    [$ok, $errs, $v] = Validator::validate_views($value);
                    if (!$ok) { $errors[] = "پست $pid: " . implode(' ', $errs); continue; }
                }
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
                    if ($is_range) {
                        [$ok1, $errs1, $vmin] = Validator::validate_casts($range_min);
                        [$ok2, $errs2, $vmax] = Validator::validate_casts($range_max);
                        $vc = mt_rand((int)$vmin, (int)$vmax);
                    } else {
                        [$ok, $errs, $vc] = Validator::validate_casts($value);
                        if (!$ok) { $errors[] = "پست $pid: " . implode(' ', $errs); continue; }
                    }
                    $old = $stats;
                    $new_ratings = $stats['avg'] * $vc / $best *5;
                    if (Adapter_KKSR::set_stats($pid, $vc, $new_ratings, $stats['avg'], 'bulk_manual', $exec)) {
                        History::log($pid, 'kksr_casts', $stats['casts'], $vc, 'bulk_manual', get_current_user_id(), $exec);
                        $success_count++;
                    }
                } else {
                    if ($is_range) {
                        [$ok1, $errs1, $vmin] = Validator::validate_avg($range_min, $best);
                        [$ok2, $errs2, $vmax] = Validator::validate_avg($range_max, $best);
                        // بازه تصادفی با دقت 0.1
                        $vmin10 = (int) round($vmin*10); $vmax10 = (int) round($vmax*10);
                        $va = mt_rand($vmin10, $vmax10)/10;
                    } else {
                        [$ok, $errs, $va] = Validator::validate_avg($value, $best);
                        if (!$ok) { $errors[] = "پست $pid: " . implode(' ', $errs); continue; }
                    }
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

        $res = ['success_count'=>$success_count, 'errors'=>$errors, 'execution_id'=>$exec];
        if ($is_range) { $res['range_mode']=true; $res['range_min']=$range_min; $res['range_max']=$range_max; }
        wp_send_json_success($res);
    }

    private static function preview_tier(): void {
        // 1.9.4 SEC-03: هشدار per-post — اگر strict mode روشن باشد، لاگ کن (default: فقط هشدار)
        if (get_option('apex_omega_strict_per_post', false)) {
            // strict: require edit_post برای هر پست هدف — ولی preview هنوز پست خاصی ندارد، پس فقط لاگ
            error_log('[APEX Ω] preview_tier by user '.get_current_user_id().' strict mode check');
        }
        $counts = $_POST['counts'] ?? [];
        if (is_string($counts)) $counts = json_decode(stripslashes($counts), true) ?: [];
        // ممکن است به صورت counts[tier_1]=5 بیاید
        if (empty($counts) && isset($_POST['counts'])) {
            // حالت fallback: $_POST['counts'] آرایه PHP است
            $counts = (array) $_POST['counts'];
        }
        // sanitize
        $clean = [];
        foreach ((array)$counts as $k=>$v) {
            $k = sanitize_text_field($k);
            $clean[$k] = max(0, (int)$v);
        }
        $filters = [
            'category' => sanitize_text_field($_POST['category'] ?? 'all'),
            'post_type' => sanitize_text_field($_POST['post_type'] ?? 'any'),
            'artist_aware' => !empty($_POST['artist_aware']),
        ];
        $res = \ApexOmega\Core\TierManager::preview_distribution($clean, $filters);
        if (empty($res['success'])) wp_send_json_error($res, 400);
        wp_send_json_success($res);
    }

    private static function apply_tier(): void {
        $counts = $_POST['counts'] ?? [];
        if (is_string($counts)) $counts = json_decode(stripslashes($counts), true) ?: [];
        $clean = [];
        foreach ((array)$counts as $k=>$v) {
            $k = sanitize_text_field($k);
            $clean[$k] = max(0, (int)$v);
        }
        $filters = [
            'category' => sanitize_text_field($_POST['category'] ?? 'all'),
            'post_type' => sanitize_text_field($_POST['post_type'] ?? 'any'),
            'artist_aware' => !empty($_POST['artist_aware']),
        ];
        // ذخیره حالت خودکار
        $auto_enable = !empty($_POST['auto_enable']);
        if ($auto_enable) {
            update_option('apex_omega_auto_tier', ['enabled'=>true, 'counts'=>$clean, 'filters'=>$filters, 'updated_at'=>current_time('mysql', true)], false);
            \ApexOmega\Core\TierManager::schedule_auto();
        } else {
            // اگر تیک برداشته شد
            $auto = get_option('apex_omega_auto_tier', []);
            if (!empty($auto['enabled'])) {
                update_option('apex_omega_auto_tier', ['enabled'=>false], false);
                \ApexOmega\Core\TierManager::unschedule_auto();
            }
        }
        $res = \ApexOmega\Core\TierManager::apply_distribution($clean, $filters);
        if (empty($res['success'])) wp_send_json_error($res, 400);
        wp_send_json_success($res);
    }

    private static function ai_suggest(): void {
        global $wpdb;
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'");
        $tiers = \ApexOmega\Core\TierManager::get_tiers();
        $res = \ApexOmega\Core\AI_Helper::suggest_distribution($total, $tiers);
        if (!empty($res['success'])) wp_send_json_success($res);
        wp_send_json_error($res, 400);
    }

    private static function ai_save(): void {
        $data = [
            'api_key' => $_POST['api_key'] ?? '',
            'model' => $_POST['model'] ?? 'gpt-4o-mini',
            'endpoint' => $_POST['endpoint'] ?? 'https://api.gapgpt.app/v1/chat/completions',
            'provider' => 'gapgpt',
        ];
        // اگر کاربر •••• فرستاد، نگه دار
        if ($data['api_key'] === '••••••••' || $data['api_key'] === '') {
            $cur = \ApexOmega\Core\AI_Helper::get_settings();
            if (empty($_POST['api_key']) || $_POST['api_key'] === '••••••••') {
                $data['api_key'] = $cur['api_key'];
            }
        }
        \ApexOmega\Core\AI_Helper::save_settings($data);
        wp_send_json_success(['message'=>'ذخیره شد']);
    }

    private static function save_artist_weights(): void {
        $weights = $_POST['weights'] ?? [];
        if (is_string($weights)) $weights = json_decode(stripslashes($weights), true) ?: [];
        $clean = [];
        foreach ((array)$weights as $tid=>$w) {
            $clean[(int)$tid] = max(0,min(100,(int)$w));
        }
        \ApexOmega\Core\ArtistManager::save_weights($clean);
        wp_send_json_success(['message'=>'وزن‌ها ذخیره شد','count'=>count($clean)]);
    }

    private static function auto_artist_ai(): void {
        $res = \ApexOmega\Core\ArtistManager::auto_weight_via_ai();
        if (!empty($res['success'])) wp_send_json_success($res);
        wp_send_json_error($res, 400);
    }

    private static function auto_artist_ir(): void {
        $res = \ApexOmega\Core\ArtistManager::auto_weight_via_external();
        if (!empty($res['success'])) wp_send_json_success($res);
        wp_send_json_error($res, 400);
    }
    private static function test_ai(): void {
        $res = \ApexOmega\Core\AI_Helper::test_connection();
        if (!empty($res['success'])) wp_send_json_success($res); else wp_send_json_error($res, 400);
    }
    private static function save_artist_taxonomy(): void {
        $tax = sanitize_text_field($_POST['taxonomy'] ?? '');
        if ($tax === '__auto__') $tax = '';
        update_option(\ApexOmega\Core\ArtistManager::OPTION_TAXONOMY, $tax, false);
        // bust detect cache: just report
        wp_send_json_success(['message'=>'تاکسونومی ذخیره شد','taxonomy'=>$tax ?: 'خودکار']);
    }
    private static function undo_tier(): void {
        $exec = sanitize_text_field($_POST['execution_id'] ?? '');
        $res = \ApexOmega\Core\TierManager::undo_last_distribution($exec ?: null);
        if (!empty($res['success'])) wp_send_json_success($res); else wp_send_json_error($res, 400);
    }
    private static function export_artist_csv(): void {
        $csv = \ApexOmega\Core\Exporter::export_artist_csv();
        wp_send_json_success(['csv'=>$csv,'filename'=>'apex-artist-weights-'.gmdate('Y-m-d').'.csv']);
    }
    private static function export_history_csv(): void {
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 1000;
        $csv = \ApexOmega\Core\Exporter::export_history_csv(min(10000,max(10,$limit))); // 1.9.3 سقف 10K
        wp_send_json_success(['csv'=>$csv,'filename'=>'apex-history-'.gmdate('Y-m-d').'.csv']);
    }
    private static function export_tiers_json(): void {
        $json = \ApexOmega\Core\Exporter::export_tiers_json();
        wp_send_json_success(['json'=>$json,'filename'=>'apex-tiers-'.gmdate('Y-m-d').'.json']);
    }
    private static function import_artist_csv(): void {
        $csv = $_POST['csv'] ?? '';
        if (!$csv) wp_send_json_error(['message'=>'CSV خالی است'],400);
        $res = \ApexOmega\Core\Exporter::import_artist_csv(stripslashes($csv));
        if (!empty($res['success'])) wp_send_json_success($res); else wp_send_json_error($res,400);
    }
    private static function import_tiers_json(): void {
        $json = $_POST['json'] ?? '';
        if (!$json) wp_send_json_error(['message'=>'JSON خالی است'],400);
        $res = \ApexOmega\Core\Exporter::import_tiers_json(stripslashes($json));
        if (!empty($res['success'])) wp_send_json_success($res); else wp_send_json_error($res,400);
    }
    private static function save_evergreen(): void {
        $data = [
            'enabled' => !empty($_POST['enabled']),
            'rate_percent' => isset($_POST['rate_percent']) ? (float)$_POST['rate_percent'] : null,
            'max_views' => isset($_POST['max_views']) ? (int)$_POST['max_views'] : null,
        ];
        \ApexOmega\Core\Evergreen::save_settings($data);
        wp_send_json_success(['message'=>'تنظیمات Evergreen ذخیره شد','settings'=>\ApexOmega\Core\Evergreen::get_settings()]);
    }
    private static function save_rhythm(): void {
        $data = [
            'artist_ratio' => isset($_POST['artist_ratio']) ? (float)$_POST['artist_ratio'] : null,
            'title_ratio' => isset($_POST['title_ratio']) ? (float)$_POST['title_ratio'] : null,
            'unknown_threshold' => isset($_POST['unknown_threshold']) ? (int)$_POST['unknown_threshold'] : null,
            'unknown_cap' => isset($_POST['unknown_cap']) ? (int)$_POST['unknown_cap'] : null,
            'require_pair' => isset($_POST['require_pair']) ? !empty($_POST['require_pair']) : null,
        ];
        // filter nulls
        $data = array_filter($data, function($v){ return $v !== null; });
        \ApexOmega\Core\ArtistManager::save_rhythm_settings($data);
        wp_send_json_success(['message'=>'الگوی ریتم ذخیره شد','settings'=>\ApexOmega\Core\ArtistManager::get_rhythm_settings()]);
    }
    private static function save_autowatch(): void {
        $data = [
            'enabled' => isset($_POST['enabled']) ? !empty($_POST['enabled']) : null,
            'threshold' => isset($_POST['threshold']) ? (int)$_POST['threshold'] : null,
            'min_views' => isset($_POST['min_views']) ? (int)$_POST['min_views'] : null,
            'max_views' => isset($_POST['max_views']) ? (int)$_POST['max_views'] : null,
            'batch' => isset($_POST['batch']) ? (int)$_POST['batch'] : null,
            'only_recent_days' => isset($_POST['only_recent_days']) ? (int)$_POST['only_recent_days'] : null,
        ];
        $data = array_filter($data, function($v){ return $v !== null; });
        \ApexOmega\Core\AutoWatch::save_settings($data);
        wp_send_json_success(['message'=>'نگهبان ذخیره شد','settings'=>\ApexOmega\Core\AutoWatch::get_settings()]);
    }
    private static function save_commentor(): void {
        $data = [
            'enabled' => isset($_POST['enabled']) ? !empty($_POST['enabled']) : null,
            'threshold' => isset($_POST['threshold']) ? (int)$_POST['threshold'] : null,
            'min_comments' => isset($_POST['min_comments']) ? (int)$_POST['min_comments'] : null,
            'max_comments' => isset($_POST['max_comments']) ? (int)$_POST['max_comments'] : null,
            'daily_growth' => isset($_POST['daily_growth']) ? (int)$_POST['daily_growth'] : null,
            'max_per_post' => isset($_POST['max_per_post']) ? (int)$_POST['max_per_post'] : null,
            'batch' => isset($_POST['batch']) ? (int)$_POST['batch'] : null,
            'only_recent_days' => isset($_POST['only_recent_days']) ? (int)$_POST['only_recent_days'] : null,
        ];
        $data = array_filter($data, function($v){ return $v !== null; });
        \ApexOmega\Core\CommentGuardian::save_settings($data);
        wp_send_json_success(['message'=>'نگهبان کامنت ذخیره شد','settings'=>\ApexOmega\Core\CommentGuardian::get_settings()]);
    }
    private static function run_commentor_now(): void {
        $res = \ApexOmega\Core\CommentGuardian::run_manual();
        $last = get_option('apex_omega_commentor_last_run', []);
        wp_send_json_success(['message'=>'اجرای نگهبان کامنت انجام شد','last'=>$last,'debug'=>$res]);
    }
    private static function commentor_diag(): void {
        $diag = \ApexOmega\Core\CommentGuardian::diag();
        wp_send_json_success($diag);
    }
    private static function start_tier_job(): void {
        $counts = $_POST['counts'] ?? [];
        if (is_string($counts)) $counts = json_decode(stripslashes($counts), true) ?: [];
        $clean=[]; foreach ((array)$counts as $k=>$v){ $k=sanitize_text_field($k); $clean[$k]=max(0,(int)$v); }
        $filters = ['category'=>sanitize_text_field($_POST['category']??'all'),'post_type'=>sanitize_text_field($_POST['post_type']??'any'),'artist_aware'=>!empty($_POST['artist_aware'])];
        $chunk_size = isset($_POST['chunk_size']) ? max(10,min(200,(int)$_POST['chunk_size'])) : 100;
        // ذخیره auto_tier مثل apply_tier
        $auto_enable=!empty($_POST['auto_enable']);
        if($auto_enable){ update_option('apex_omega_auto_tier',['enabled'=>true,'counts'=>$clean,'filters'=>$filters,'updated_at'=>current_time('mysql',true)],false); \ApexOmega\Core\TierManager::schedule_auto(); }
        else { $auto=get_option('apex_omega_auto_tier',[]); if(!empty($auto['enabled'])){ update_option('apex_omega_auto_tier',['enabled'=>false],false); \ApexOmega\Core\TierManager::unschedule_auto(); } }
        $res=\ApexOmega\Core\TierManager::start_distribution_job($clean,$filters,$chunk_size);
        if(empty($res['success'])) wp_send_json_error($res,400);
        wp_send_json_success($res);
    }
    private static function process_tier_chunk(): void {
        $exec=sanitize_text_field($_POST['execution_id']??'');
        if(!$exec) wp_send_json_error(['message'=>'execution_id الزامی است'],400);
        $chunk_size=isset($_POST['chunk_size'])? (int)$_POST['chunk_size']:0;
        $res=\ApexOmega\Core\TierManager::process_job_chunk($exec,$chunk_size);
        if(empty($res['success'])) wp_send_json_error($res,400);
        wp_send_json_success($res);
    }
    private static function tier_job_status(): void {
        $exec=sanitize_text_field($_POST['execution_id']??'');
        if(!$exec) wp_send_json_error(['message'=>'execution_id الزامی است'],400);
        $res=\ApexOmega\Core\TierManager::get_job_status($exec);
        if(empty($res['success'])) wp_send_json_error($res,400);
        wp_send_json_success($res);
    }
    private static function send_post_comments(): void {
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $count = isset($_POST['count']) ? (int) $_POST['count'] : 3;
        if (!$post_id || !get_post($post_id)) wp_send_json_error(['message'=>'نوشته نامعتبر'], 400);
        if (!current_user_can('edit_post', $post_id) && !apex_omega_user_can_manage()) wp_send_json_error(['message'=>'دسترسی ندارید'], 403);
        $res = \ApexOmega\Core\CommentGuardian::send_for_post($post_id, $count);
        if (!empty($res['success'])) wp_send_json_success($res);
        wp_send_json_error($res, 400);
    }

    private static function preview_weight_sync(): void {
        $filters = [
            'category' => sanitize_text_field($_POST['category'] ?? 'all'),
            'post_type' => sanitize_text_field($_POST['post_type'] ?? 'any'),
            'limit' => isset($_POST['limit']) ? (int)$_POST['limit'] : 10000,
        ];
        $res = \ApexOmega\Core\ArtistManager::preview_weight_sync($filters);
        if (empty($res['success'])) wp_send_json_error($res, 400);
        wp_send_json_success($res);
    }
    private static function apply_weight_sync(): void {
        $filters = [
            'category' => sanitize_text_field($_POST['category'] ?? 'all'),
            'post_type' => sanitize_text_field($_POST['post_type'] ?? 'any'),
            'limit' => isset($_POST['limit']) ? (int)$_POST['limit'] : 10000,
        ];
        $res = \ApexOmega\Core\ArtistManager::apply_weight_sync($filters);
        if (empty($res['success'])) wp_send_json_error($res, 400);
        wp_send_json_success($res);
    }
    private static function start_weight_sync_job(): void {
        $filters = ['category'=>sanitize_text_field($_POST['category']??'all'),'post_type'=>sanitize_text_field($_POST['post_type']??'any'),'limit'=>isset($_POST['limit'])?(int)$_POST['limit']:5000];
        $chunk_size = isset($_POST['chunk_size']) ? max(10,min(200,(int)$_POST['chunk_size'])) : 100;
        $res = \ApexOmega\Core\ArtistManager::start_weight_sync_job($filters, $chunk_size);
        if (empty($res['success'])) wp_send_json_error($res, 400);
        wp_send_json_success($res);
    }
    private static function process_weight_chunk(): void {
        $exec = sanitize_text_field($_POST['execution_id'] ?? '');
        if (!$exec) wp_send_json_error(['message'=>'execution_id الزامی است'],400);
        $chunk_size = isset($_POST['chunk_size']) ? (int)$_POST['chunk_size'] : 0;
        $res = \ApexOmega\Core\ArtistManager::process_weight_chunk($exec, $chunk_size);
        if (empty($res['success'])) wp_send_json_error($res, 400);
        wp_send_json_success($res);
    }
    private static function toggle_hit(): void {
        $pid = (int)($_POST['post_id'] ?? 0);
        if (!$pid) wp_send_json_error(['message'=>'post_id لازم است'],400);
        $res = \ApexOmega\Core\ArtistManager::toggle_hit($pid);
        wp_send_json_success($res);
    }
    private static function save_hits(): void {
        $ids = $_POST['post_ids'] ?? [];
        if (is_string($ids)) $ids = json_decode(stripslashes($ids), true) ?: explode(',', $ids);
        $ids = array_map('intval', (array)$ids);
        $clear = !empty($_POST['clear_first']);
        $res = \ApexOmega\Core\ArtistManager::save_hits($ids, $clear);
        wp_send_json_success($res);
    }
    private static function suggest_hits(): void {
        $term_id = isset($_POST['term_id']) ? (int)$_POST['term_id'] : null;
        $limit = isset($_POST['limit']) ? max(5,min(50,(int)$_POST['limit'])) : 20;
        $res = \ApexOmega\Core\ArtistManager::suggest_hits_via_ai($term_id, $limit);
        if (empty($res['success'])) wp_send_json_error($res,400);
        wp_send_json_success($res);
    }
    private static function clear_hits(): void {
        \ApexOmega\Core\ArtistManager::clear_hits();
        wp_send_json_success(['message'=>'تمام هیت‌ها پاک شد']);
    }
    private static function reseed_artist(): void {
        $term_id = (int)($_POST['term_id'] ?? 0);
        $tax = sanitize_text_field($_POST['taxonomy'] ?? '');
        if ($tax === '__auto__') $tax = '';
        $res = \ApexOmega\Core\ArtistManager::reseed_artist_variance($term_id, $tax);
        if (empty($res['success'])) wp_send_json_error($res, 400);
        wp_send_json_success($res);
    }
    private static function apply_premium_selected(): void {
        $with_comments = !empty($_POST['with_comments']);
        $res = \ApexOmega\Core\ArtistManager::apply_premium_to_selected_works(0, $with_comments);
        if (empty($res['success'])) wp_send_json_error($res, 400);
        wp_send_json_success($res);
    }

    private static function apply_comments_selected(): void {
        // پارامترها: count_min, count_max (بازه مثل بازدید), mode=add|set, only_zero, is_premium
        $min = isset($_POST['count_min']) ? max(1, (int)$_POST['count_min']) : 5;
        $max = isset($_POST['count_max']) && $_POST['count_max'] !== '' ? max($min, (int)$_POST['count_max']) : $min;
        // اگر کاربر یک عدد واحد فرستاده (count) — fallback
        if (isset($_POST['count']) && $_POST['count'] !== '' && empty($_POST['count_min'])) {
            $min = max(1, (int)$_POST['count']);
            $max = $min;
        }
        $mode = isset($_POST['mode']) ? sanitize_text_field($_POST['mode']) : 'add'; // add = افزودن N جدید، set = رساندن به N
        if (!in_array($mode, ['add','set'], true)) $mode = 'add';
        $only_zero = !empty($_POST['only_zero']);
        $is_premium = isset($_POST['is_premium']) ? (!empty($_POST['is_premium']) ? true : false) : null;
        // فیلتر منبع: selected-works (پیش‌فرض) یا همه فیلتر فعلی؟
        $source = isset($_POST['source_ids']) ? sanitize_text_field($_POST['source_ids']) : 'selected';
        $ids = [];
        if ($source === 'filtered' && !empty($_POST['filtered_ids'])) {
            $raw = $_POST['filtered_ids'];
            if (is_string($raw)) $raw = json_decode(stripslashes($raw), true) ?: explode(',', $raw);
            $ids = array_map('intval', (array)$raw);
            $ids = array_filter($ids);
        } else {
            $ids = \ApexOmega\Core\ArtistManager::get_selected_works_ids(5000);
        }
        if (empty($ids)) wp_send_json_error(['message'=>'هیچ پستی در دسته selected-works یافت نشد.'], 400);
        $res = \ApexOmega\Core\CommentGuardian::bulk_apply_to_ids($ids, $min, $max, [
            'mode' => $mode,
            'only_zero' => $only_zero,
            'is_premium' => $is_premium,
        ]);
        if (empty($res['success'])) wp_send_json_error($res, 400);
        wp_send_json_success($res);
    }

    private static function comment_stats_selected(): void {
        global $wpdb;
        $ids = \ApexOmega\Core\ArtistManager::get_selected_works_ids(5000);
        $total_site = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'");
        $auto_count = count($ids);
        $total_comments_site = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved='1'");
        $comments_selected = 0;
        $zero = 0;
        $buckets = ['0'=>0,'1-5'=>0,'6-15'=>0,'16-30'=>0,'30+'=>0];
        $with_comments = 0;
        if (!empty($ids)) {
            $in = implode(',', array_map('intval', $ids));
            $rows = $wpdb->get_results("SELECT comment_post_ID, COUNT(*) as c FROM {$wpdb->comments} WHERE comment_approved='1' AND comment_post_ID IN ($in) GROUP BY comment_post_ID", ARRAY_A);
            $map = [];
            foreach ($rows as $r) $map[(int)$r['comment_post_ID']] = (int)$r['c'];
            foreach ($ids as $pid) {
                $c = $map[$pid] ?? 0;
                $comments_selected += $c;
                if ($c === 0) { $buckets['0']++; $zero++; }
                elseif ($c <= 5) $buckets['1-5']++;
                elseif ($c <= 15) $buckets['6-15']++;
                elseif ($c <= 30) $buckets['16-30']++;
                else $buckets['30+']++;
                if ($c > 0) $with_comments++;
            }
        }
        $avg = $auto_count ? round($comments_selected / $auto_count, 1) : 0;
        $pct_with = $auto_count ? round($with_comments / $auto_count * 100, 1) : 0;
        $pct_selected_of_site = $total_site ? round($auto_count / $total_site * 100, 1) : 0;
        wp_send_json_success([
            'total_site'=>$total_site,
            'auto_count'=>$auto_count,
            'pct_selected'=>$pct_selected_of_site,
            'total_comments_site'=>$total_comments_site,
            'comments_selected'=>$comments_selected,
            'avg'=>$avg,
            'with_comments'=>$with_comments,
            'zero'=>$zero,
            'pct_with'=>$pct_with,
            'pct_zero'=> $auto_count ? round($zero/$auto_count*100,1) : 0,
            'buckets'=>$buckets,
        ]);
    }

}
