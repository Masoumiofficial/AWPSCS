<?php if (!defined('ABSPATH')) exit;
$total_posts = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'");
$tiers = \ApexOmega\Core\TierManager::get_tiers();
$last = get_option(\ApexOmega\Core\TierManager::OPTION_LAST_DISTRIBUTION, []);
$auto = get_option('apex_omega_auto_tier', ['enabled'=>false, 'time'=>'03:00']);
$ai_set = \ApexOmega\Core\AI_Helper::get_settings();
$is_ai = \ApexOmega\Core\AI_Helper::is_configured();
$artist_tax = \ApexOmega\Core\ArtistManager::detect_artist_taxonomy();
$artists = $artist_tax ? \ApexOmega\Core\ArtistManager::get_all_artists($artist_tax, 80) : [];
$ui_mode = get_option('apex_omega_ui_mode', 'simple');
$available_taxes = \ApexOmega\Core\ArtistManager::get_available_taxonomies();
$saved_tax = get_option(\ApexOmega\Core\ArtistManager::OPTION_TAXONOMY, '');
$last_snap = \ApexOmega\Core\Database::get_last_snapshot();
?>
<div class="wrap apex-wrap" data-ui-mode="<?php echo esc_attr($ui_mode); ?>">
  <div class="apex-hero">
    <div>
      <h1 style="margin:0;font-size:26px;">🚀 توزیع هوشمند دسته‌ای</h1>
      <p style="margin:6px 0 0;color:#50575e;">بدون انتخاب تکی — فقط بگو هر دسته چند آهنگ داشته باشد، بقیه خودکار و منطقی تنظیم می‌شود. <strong>کاملاً خودکار + آرتیست‌محور — v1.2.2</strong></p>
    </div>
    <div class="apex-hero-stats">
      <div><span class="big"><?php echo esc_html(number_format_i18n($total_posts)); ?></span><small>آهنگ منتشرشده</small></div>
      <div><span class="big" id="apex-total-selected">0</span><small>انتخاب‌شده</small></div>
      <div><span class="big" id="apex-remaining" data-total="<?php echo esc_attr($total_posts); ?>"><?php echo esc_html($total_posts); ?></span><small>باقی‌مانده</small></div>
    </div>
  </div>

  <!-- حالت ساده / حرفه‌ای -->
  <div class="apex-card" style="margin-top:14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; border-right:4px solid #7c3aed;">
    <div>
      <strong>حالت نمایش:</strong>
      <span class="apex-badge <?php echo $ui_mode==='simple'?'active':'paused'; ?>">ساده</span>
      <span class="apex-badge <?php echo $ui_mode==='pro'?'active':'paused'; ?>">حرفه‌ای</span>
      <small style="margin-right:10px;color:#6b7280;">ساده: فقط تعدادها و پیش‌نمایش — حرفه‌ای: فیلتر، آرتیست، اسلایدر دقیق، جزئیات فنی</small>
    </div>
    <label style="display:flex;align-items:center;gap:8px;cursor:pointer; background:#f3f4f6;border:1px solid #e5e7eb;border-radius:999px;padding:6px 14px;">
      <span>ساده</span>
      <input type="checkbox" id="apex-ui-mode-toggle" <?php checked($ui_mode,'pro'); ?>>
      <span>حرفه‌ای</span>
    </label>
  </div>

  <?php if (!$is_ai): ?>
    <div class="apex-alert warning" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
      <span>💡 <strong>GapGPT وصل نیست</strong> — از هرم استاندارد استفاده می‌شود. کلید را وارد کن تا AI توزیع را هوشمند کند.</span>
      <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-settings')); ?>" class="button button-apex secondary">تنظیم AI →</a>
    </div>
  <?php else: ?>
    <div class="apex-alert success" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
      <span>✨ <strong>GapGPT فعال (<?php echo esc_html($ai_set['model']); ?>)</strong></span>
      <button id="apex-ai-suggest" class="button button-apex">✨ پیشنهاد AI برای دسته‌ها</button>
    </div>
  <?php endif; ?>

  <!-- بخش آرتیست -->
  <div class="apex-card apex-pro-only" style="margin-top:14px; border-top:4px solid #f59e0b;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
      <h3 style="margin:0;">🎤 توزیع بر اساس محبوبیت آرتیست</h3>
      <label style="background:#fef3c7;border:1px solid #f59e0b;border-radius:999px;padding:6px 14px;font-weight:800;cursor:pointer;">
        <input type="checkbox" id="apex-artist-aware" checked> فعال: آرتیست‌های پرطرفدار → دسته الماس، ناشناس → تازه
      </label>
    </div>
    <p style="color:#6b7280;margin:8px 0 0;">وقتی فعال باشد، آهنگ‌های پرطرفدار به‌طور خودکار در الماس می‌روند و آهنگ ناشناس هرگز به الماس نمی‌رود. تاکسونومی شناسایی‌شده: <code><?php echo $artist_tax ? esc_html($artist_tax) : 'یافت نشد'; ?></code></p>

    <!-- انتخابگر تاکسونومی -->
    <div style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap; align-items:center; background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:10px;">
      <label style="font-weight:800;">تاکسونومی آرتیست:</label>
      <select id="apex-artist-taxonomy" style="min-width:220px;">
        <option value="__auto__" <?php selected($saved_tax,''); ?>>🔍 خودکار (تشخیص هوشمند)</option>
        <?php foreach ($available_taxes as $tax => $info): ?>
          <option value="<?php echo esc_attr($tax); ?>" <?php selected($saved_tax,$tax); ?>>
            <?php echo esc_html($info['label'] . ' (' . $tax . ') — ' . $info['count'] . ' ترم' . ($info['public']?'':' — غیرعمومی')); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button id="apex-taxonomy-save" class="button button-apex secondary">ذخیره تاکسونومی</button>
      <span id="apex-taxonomy-result" style="color:#065f46;font-weight:700;"></span>
      <small style="width:100%;color:#92400e;">اگر قالب شما از `artist` یا `singer` استفاده نمی‌کند، اینجا دستی انتخاب کن (مثلاً `post_tag` یا `genre`). بعد از تغییر، صفحه را رفرش کن.</small>
    </div>

    <?php if ($artist_tax && !empty($artists)): ?>
      <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
        <button id="apex-artist-ai" class="button button-apex secondary">🤖 امتیازدهی خودکار با AI (GapGPT)</button>
        <button id="apex-artist-ir" class="button button-apex secondary">🌐 امتیاز از سایت ایرانی (رادیوجوان/نکس1موزیک)</button>
        <button id="apex-artist-save" class="button button-apex">💾 ذخیره وزن‌ها</button>
        <span style="margin-right:auto;color:#6b7280;font-size:12px;">وزن 0–100: 90+=الماس، 75+=پرطرفدار، ... 0–15=تازه — با قفل هوشمند (TTL=تعداد×۲ثانیه)</span>
      </div>
      <div style="max-height:320px;overflow:auto;margin-top:10px;border:1px solid #e5e7eb;border-radius:10px;">
        <table class="apex-table" style="margin:0;">
          <thead><tr><th>آرتیست</th><th>تعداد آهنگ</th><th>وزن (0–100)</th><th>دسته پیشنهادی</th><th>اسلایدر</th></tr></thead>
          <tbody>
            <?php foreach ($artists as $a): ?>
              <tr data-tid="<?php echo esc_attr($a['term_id']); ?>">
                <td><strong><?php echo esc_html($a['name']); ?></strong> <small>(<?php echo esc_html($a['slug']); ?>)</small></td>
                <td><?php echo esc_html($a['count']); ?></td>
                <td><input type="number" class="apex-artist-weight" data-tid="<?php echo esc_attr($a['term_id']); ?>" value="<?php echo esc_attr($a['weight']); ?>" min="0" max="100" style="width:80px;text-align:center;font-weight:800;"></td>
                <td><span class="apex-badge" style="background:<?php echo esc_attr($tiers[array_search($a['tier_hint'], array_column($tiers,'id'))]['color'] ?? '#6b7280'); ?>;color:#fff;"><?php echo esc_html($tiers[array_search($a['tier_hint'], array_column($tiers,'id'))]['label'] ?? $a['tier_hint']); ?></span></td>
                <td><input type="range" class="apex-artist-slider" data-tid="<?php echo esc_attr($a['term_id']); ?>" min="0" max="100" value="<?php echo esc_attr($a['weight']); ?>" style="width:140px;accent-color:#f59e0b;"></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div id="apex-artist-result" style="margin-top:8px;"></div>
      <small>💡 دستی وزن بده (مثلاً ابی 95، آرتیست جدید 10) یا AI/سایت ایرانی را بزن — با کش 24ساعته و fallback خودکار اگر سایت دردسترس نبود.</small>
    <?php elseif ($artist_tax): ?>
      <div class="apex-alert warning">تاکسونومی <?php echo esc_html($artist_tax); ?> خالی است.</div>
    <?php else: ?>
      <div class="apex-alert warning">
        تاکسونومی آرتیست یافت نشد. افزونه به‌صورت خودکار `artist / singer / post_tag` را چک کرده ولی چیزی نیافته.<br>
        از انتخابگر بالا تاکسونومی درست را انتخاب کن یا این بخش را نادیده بگیر — توزیع هرمی همچنان کار می‌کند.
      </div>
    <?php endif; ?>
  </div>

  <!-- نوار ابزار سریع -->
  <div class="apex-card" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:16px 0;">
    <strong>ابزار سریع:</strong>
    <button id="apex-preset-pyramid" class="button">🔺 هرم استاندارد</button>
    <button id="apex-preset-equal" class="button">⚖️ مساوی</button>
    <button id="apex-preset-clear" class="button">🧹 صفر</button>
    <span style="margin-right:auto;"></span>
    <label class="apex-pro-only">فیلتر دسته:
      <select id="apex-filter-cat" style="min-width:160px;">
        <option value="all">همه دسته‌ها</option>
        <?php $cats=get_categories(['hide_empty'=>false]); foreach($cats as $c): ?>
          <option value="<?php echo esc_attr($c->slug); ?>"><?php echo esc_html($c->name); ?> (<?php echo $c->count; ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>نوع:
      <select id="apex-filter-pt">
        <?php $pts=get_post_types(['public'=>true],'objects'); foreach($pts as $pt): if($pt->name==='attachment') continue; ?>
          <option value="<?php echo esc_attr($pt->name); ?>" <?php selected($pt->name,'post'); ?>><?php echo esc_html($pt->label); ?></option>
        <?php endforeach; ?>
        <option value="any">همه</option>
      </select>
    </label>
  </div>

  <!-- گرید دسته‌ها -->
  <div class="apex-tier-grid">
    <?php foreach ($tiers as $t): ?>
      <div class="apex-tier-card" data-tier="<?php echo esc_attr($t['id']); ?>" style="--tier-color: <?php echo esc_attr($t['color']); ?>">
        <div class="apex-tier-head">
          <span class="apex-tier-icon"><?php echo esc_html($t['icon']); ?></span>
          <div>
            <h3><?php echo esc_html($t['label']); ?></h3>
            <small><?php echo esc_html($t['desc']); ?></small>
          </div>
          <span class="apex-tier-badge"><?php echo esc_html(number_format_i18n($t['views_min'])); ?>–<?php echo esc_html(number_format_i18n($t['views_max'])); ?></span>
        </div>
        <div class="apex-tier-ranges">
          <div>👁️ <strong><?php echo number_format_i18n($t['views_min']); ?></strong> تا <strong><?php echo number_format_i18n($t['views_max']); ?></strong></div>
          <div>🗳️ <strong><?php echo number_format_i18n($t['casts_min']); ?></strong>–<strong><?php echo number_format_i18n($t['casts_max']); ?></strong> &nbsp;|&nbsp; ⭐ <strong><?php echo $t['avg_min']; ?></strong>–<strong><?php echo $t['avg_max']; ?></strong></div>
        </div>
        <div class="apex-tier-control">
          <label>تعداد در این دسته</label>
          <div class="apex-stepper">
            <button class="apex-minus" data-tier="<?php echo esc_attr($t['id']); ?>">−</button>
            <input type="number" class="apex-tier-count" data-tier="<?php echo esc_attr($t['id']); ?>" value="0" min="0" max="<?php echo esc_attr($total_posts); ?>" step="1" placeholder="0">
            <button class="apex-plus" data-tier="<?php echo esc_attr($t['id']); ?>">+</button>
          </div>
          <div class="apex-tier-slider-wrap">
            <input type="range" class="apex-tier-slider" data-tier="<?php echo esc_attr($t['id']); ?>" min="0" max="<?php echo esc_attr($total_posts); ?>" value="0">
          </div>
          <small class="apex-slider-hint" data-tier="<?php echo esc_attr($t['id']); ?>" style="color:#6b7280;">حداکثر: <?php echo esc_html(number_format_i18n($total_posts)); ?> — با تنظیم سایر دسته‌ها به‌صورت پویا کم/زیاد می‌شود</small>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- پیش‌نمایش و اجرا -->
  <div class="apex-card" style="margin-top:18px;">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
      <h3 style="margin:0;">👁️ پیش‌نمایش</h3>
      <span style="margin-right:auto;color:#646970;" id="apex-preview-hint">تعدادها را پر کن → پیش‌نمایش → اعمال (با اسنپ‌شات و قابلیت بازگشت)</span>
    </div>
    <div class="apex-actions" style="margin:12px 0;">
      <button id="apex-preview-tier" class="button-apex secondary">👁️ پیش‌نمایش</button>
      <button id="apex-apply-tier" class="button-apex" style="background:#00a32a;">✅ اعمال خودکار</button>
      <label style="margin-right:12px;"><input type="checkbox" id="apex-auto-enable" <?php checked(!empty($auto['enabled'])); ?>> خودکار روزانه 03:00 (فقط آهنگ‌های 7+ روز قدیمی)</label>
    </div>
    <div id="apex-tier-preview-box" class="apex-preview-box" style="min-height:90px;">هنوز پیش‌نمایشی نیست.</div>
    <div id="apex-tier-result"></div>
    <!-- جدول نتایج دقیق پس از اعمال -->
    <div id="apex-tier-details-box" style="margin-top:12px; display:none;">
      <h4>📋 جزئیات تغییرات (قابل بازگشت با اسنپ‌شات)</h4>
      <div style="max-height:400px; overflow:auto; border:1px solid #e5e7eb; border-radius:10px;">
        <table class="apex-table" id="apex-result-table"><thead><tr><th>آهنگ</th><th>دسته</th><th>بازدید قبلی → جدید</th><th>رأی قبلی → جدید</th><th>میانگین</th></tr></thead><tbody></tbody></table>
      </div>
    </div>
  </div>

  <!-- AI -->
  <div class="apex-card" style="margin-top:18px;">
    <h3>🤖 GapGPT — پیشنهاد کاملاً خودکار (اختیاری)</h3>
    <p>کلید از <a href="https://gapgpt.app/platform-v2" target="_blank">gapgpt.app</a> — <a href="https://gapgpt.app/platform-v2/docs/quickstart" target="_blank">quickstart</a> — کلید با AUTH_KEY رمزنگاری می‌شود</p>
    <form id="apex-ai-form" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="apex-field"><label>Endpoint</label><input type="url" id="apex-ai-endpoint" value="<?php echo esc_attr($ai_set['endpoint']); ?>"></div>
      <div class="apex-field"><label>مدل</label><input type="text" id="apex-ai-model" value="<?php echo esc_attr($ai_set['model']); ?>"></div>
      <div class="apex-field"><label>API Key</label><input type="password" id="apex-ai-key" value="<?php echo $is_ai ? '••••••••' : ''; ?>" placeholder="sk-..."></div>
      <div class="apex-field" style="display:flex;align-items:end;gap:8px;flex-wrap:wrap;">
        <button id="apex-ai-save" class="button button-apex">💾 ذخیره</button>
        <button id="apex-ai-test" class="button button-apex secondary" type="button">🔌 تست اتصال</button>
        <span id="apex-ai-test-result" style="font-weight:700;"></span>
      </div>
    </form>
  </div>

  <?php if (!empty($last)): ?>
    <div class="apex-card" style="margin-top:16px;border-right:4px solid #2271b1;">
      <h4>آخرین توزیع: <?php echo esc_html($last['at'] ?? '—'); ?> — <?php echo esc_html($last['applied'] ?? 0); ?> آهنگ — <code><?php echo esc_html(substr($last['exec'] ?? '',0,8)); ?></code> — قفل TTL: <?php echo isset($last['counts']) ? esc_html(apex_omega_lock_ttl(array_sum((array)$last['counts']))) . 'ثانیه' : '—'; ?></h4>
      <small><?php echo esc_html(wp_json_encode($last['counts'] ?? [], JSON_UNESCAPED_UNICODE)); ?></small>
      <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
        <button id="apex-undo-tier" class="button button-apex secondary" data-exec="<?php echo esc_attr($last['exec'] ?? ''); ?>" style="background:#fef2f2; border-color:#fca5a5; color:#991b1b;">↩️ بازگردانی آخرین توزیع (Undo)</button>
        <span id="apex-undo-result" style="font-weight:700;"></span>
      </div>
      <?php if (!empty($last_snap) && $last_snap['restored'] == 0): ?>
        <small style="color:#065f46;">✅ اسنپ‌شات موجود است — تا هر زمان قابل بازگشت است (ID: <?php echo esc_html($last_snap['execution_id']); ?>)</small>
      <?php elseif (!empty($last_snap)): ?>
        <small style="color:#92400e;">↩️ این اسنپ‌شات قبلاً بازگردانی شده است</small>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="apex-alert success">
    <strong>قابلیت‌های 1.2.2:</strong> اسنپ‌شات کامل قبل از هر توزیع (قابل Undo) — تراکنش یکپارچه هر پست (views+kksr همزمان) — قفل هوشمند <code>TTL = تعداد × ۲ ثانیه</code> — paging برای >۵۰۰۰ آهنگ — رمزنگاری کلید API با <code>AUTH_KEY</code> — تست اتصال GapGPT — انتخاب دستی تاکسونومی — حالت ساده/حرفه‌ای — جدول نتایج دقیق پس از اعمال — فیلتر ۷ روزه برای توزیع خودکار (هر آهنگ حداکثر هفته‌ای یک‌بار عوض می‌شود) — گزارش هفتگی ایمیلی و چک سلامت سایت.
  </div>
</div>
