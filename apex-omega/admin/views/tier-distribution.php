<?php if (!defined('ABSPATH')) exit;
$total_posts = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'");
$tiers = \ApexOmega\Core\TierManager::get_tiers();
$last = get_option(\ApexOmega\Core\TierManager::OPTION_LAST_DISTRIBUTION, []);
$auto = get_option('apex_omega_auto_tier', ['enabled'=>false, 'time'=>'03:00']);
$ai_set = \ApexOmega\Core\AI_Helper::get_settings();
$is_ai = \ApexOmega\Core\AI_Helper::is_configured();
$artist_tax = \ApexOmega\Core\ArtistManager::detect_artist_taxonomy();
$artists = $artist_tax ? \ApexOmega\Core\ArtistManager::get_all_artists($artist_tax, 80) : [];
$available_taxes = \ApexOmega\Core\ArtistManager::get_available_taxonomies();
$saved_tax = get_option(\ApexOmega\Core\ArtistManager::OPTION_TAXONOMY, '');
$last_snap = \ApexOmega\Core\Database::get_last_snapshot();
?>
<div class="wrap apex-glass-wrap">
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0;">
  <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-artists')); ?>" class="button" style="background:#f59e0b;color:#fff;border-color:#f59e0b;">🎤 پنل هنرمندان</a>
  <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-automation')); ?>" class="button" style="background:#0e7490;color:#fff;border-color:#0e7490;">⚙️ اتوماسیون</a>
  <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-weight-sync')); ?>" class="button" style="background:#059669;color:#fff;border-color:#059669;">⚡ همگام‌سازی یک‌کلیک</a>
  <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-analytics')); ?>" class="button button-secondary">📊 آنالیتیکس</a>
  <span style="margin-right:auto;color:#6b7280;font-size:12px;">پنل جدید v2.1.9 — هر بخش تفکیک‌شده + درصد پیشرفت</span>
</div>
<div class="apex-hero">
    <div>
      <h1 style="margin:0;font-size:26px;">🚀 توزیع هوشمند دسته‌ای</h1>
      <p style="margin:6px 0 0;color:#50575e;">بدون انتخاب تکی — فقط بگو هر دسته چند آهنگ داشته باشد، بقیه خودکار و منطقی تنظیم می‌شود. <strong>کاملاً خودکار + آرتیست‌محور + Evergreen — v1.5.0 (بدون حالت ساده/حرفه‌ای — الگوی ریتم + Evergreen تیره‌محور)</strong></p>
    </div>
    <div class="apex-hero-stats">
      <div><span class="big"><?php echo esc_html(number_format_i18n($total_posts)); ?></span><small>آهنگ منتشرشده</small></div>
      <div><span class="big" id="apex-total-selected">0</span><small>انتخاب‌شده</small></div>
      <div><span class="big" id="apex-remaining" data-total="<?php echo esc_attr($total_posts); ?>"><?php echo esc_html($total_posts); ?></span><small>باقی‌مانده</small></div>
    </div>
  </div>

  <?php if (!$is_ai): ?>
    <div class="apex-alert warning" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
      <span>💡 <strong>GapGPT وصل نیست</strong> — از هرم استاندارد استفاده می‌شود. کلید را وارد کن تا AI توزیع را هوشمند کند.</span>
      <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-settings')); ?>" class="button button-apex secondary">تنظیم AI →</a>
    </div>
  <?php else: ?>
    <div class="apex-alert success" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
      <span>✨ <strong>GapGPT فعال (<?php echo esc_html($ai_set['model']); ?>)</strong></span>
      <button type="button" id="apex-ai-suggest" class="button button-apex">✨ پیشنهاد AI برای دسته‌ها</button>
    </div>
  <?php endif; ?>

  <!-- بخش آرتیست -->
  <div class="apex-card" style="margin-top:14px; border-top:4px solid #f59e0b;">
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
      <button type="button" id="apex-taxonomy-save" class="button button-apex secondary">ذخیره تاکسونومی</button>
      <span id="apex-taxonomy-result" style="color:#065f46;font-weight:700;"></span>
      <small style="width:100%;color:#92400e;">اگر قالب شما از `artist` یا `singer` استفاده نمی‌کند، اینجا دستی انتخاب کن (مثلاً `post_tag` یا `genre`). بعد از تغییر، صفحه را رفرش کن.</small>
    </div>

    <?php if ($artist_tax && !empty($artists)): ?>
      <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
        <button type="button" id="apex-artist-ai" class="button button-apex secondary">🤖 امتیازدهی خودکار با AI (GapGPT)</button>
        <button type="button" id="apex-artist-ir" class="button button-apex secondary">🌐 امتیاز از سایت ایرانی (رادیوجوان/نکس1موزیک)</button>
        <button type="button" id="apex-artist-save" class="button button-apex">💾 ذخیره وزن‌ها</button>
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


  <!-- الگوی ریتم حرفه‌ای — اولویت خواننده (درخواست شما) -->
<div class="apex-tier-control">
          <label>تعداد در این دسته</label>
          <div class="apex-stepper">
            <button type="button" class="apex-minus" data-tier="<?php echo esc_attr($t['id']); ?>">−</button>
            <input type="number" class="apex-tier-count" data-tier="<?php echo esc_attr($t['id']); ?>" value="0" min="0" max="<?php echo esc_attr($total_posts); ?>" step="1" placeholder="0">
            <button type="button" class="apex-plus" data-tier="<?php echo esc_attr($t['id']); ?>">+</button>
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
      <button type="button" id="apex-preview-tier" class="button-apex secondary">👁️ پیش‌نمایش</button>
      <button type="button" id="apex-apply-tier" class="button-apex" style="background:#00a32a;">✅ اعمال خودکار</button>
      <label style="margin-right:12px;"><input type="checkbox" id="apex-auto-enable" <?php checked(!empty($auto['enabled'])); ?>> خودکار روزانه 03:00 (فقط آهنگ‌های 7+ روز قدیمی)</label>
    </div>
    <?php $evergreen = \ApexOmega\Core\Evergreen::get_settings(); $ever_enabled = !empty($evergreen['enabled']); ?>
    <div class="apex-card" style="margin:12px 0; background:#f0fdfa; border:1px solid #14b8a6; border-right:4px solid #14b8a6;">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <div>
          <h4 style="margin:0;color:#0f766e;">🌱 رشد مداوم Evergreen — هر ۵ دقیقه افزایش خودکار  ✅ جدید v1.4.0</h4>
          <small style="color:#134e4a;">بعد از توزیع اولیه، بازدید/امتیاز همه آهنگ‌ها به‌صورت کاملاً طبیعی و تدریجی تا سقف 10M رشد می‌کند — بدون نیاز به دخالت دستی، دقیقاً مثل ترافیک واقعی روزمره.</small>
        </div>
        <label style="display:flex;align-items:center;gap:8px;background:#fff;border:1px solid #14b8a6;border-radius:999px;padding:6px 14px;font-weight:800;cursor:pointer;">
          <input type="checkbox" id="apex-evergreen-enabled" <?php checked($ever_enabled,true); ?>> فعال
        </label>
      </div>
      <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin-top:10px;">
        <label>نرخ رشد هر ۵ دقیقه (%):<br><input type="number" id="apex-evergreen-rate" step="0.01" min="0.005" max="0.5" value="<?php echo esc_attr($evergreen['rate_percent']); ?>" style="width:110px;"></label>
        <label>سقف بازدید:<br><input type="number" id="apex-evergreen-max" min="50000" max="10000000" value="<?php echo esc_attr($evergreen['max_views']); ?>" style="width:140px;"></label>
        <button id="apex-evergreen-save" type="button" class="button button-apex" style="background:#0f766e;border-color:#0f766e;">💾 ذخیره Evergreen</button>
        <span id="apex-evergreen-result" style="font-weight:700;"></span>
        <small style="width:100%;color:#6b7280;">پیشنهاد: 0.03–0.05% = ≈ 5–8% رشد روزانه (واقع‌گرایانه). سقف 10M — قبلا 1.5M بود.</small>
      </div>
      <small>⏰ اجرا در <code>apex_omega_tick</code> هر ۵ دقيقه (Real-Cron). نیاز به بازدید سایت ندارد اگر سرور کرون دارد (یا WP-Cron با ترافیک).</small>
    </div>
    <?php $autowatch = \ApexOmega\Core\AutoWatch::get_settings(); $aw_enabled = !empty($autowatch['enabled']); $aw_last = get_option('apex_omega_autowatch_last_run', []); ?>
    <div class="apex-card" style="margin:12px 0; background:#fff7ed; border:1px solid #f97316; border-right:4px solid #f97316;">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <div>
          <h4 style="margin:0;color:#9a3412;">👀 نگهبان خودکار پست‌های تازه — هر چند دقیقه چک هوشمند  ✅ جدید v1.6.0</h4>
          <small style="color:#7c2d12;">پست‌های جدید اسکریپت که زیر 10K بازدید (یا صفر) هستند، خودکار شارژ می‌شوند تا سایت ضایع نباشد — تازه‌کار 500-10K، ویژه‌ها بیشتر + هر تیک رشد اصولی. کاملاً خودکار.</small>
        </div>
        <label style="display:flex;align-items:center;gap:8px;background:#fff;border:1px solid #f97316;border-radius:999px;padding:6px 14px;font-weight:800;cursor:pointer;">
          <input type="checkbox" id="apex-autowatch-enabled" <?php checked($aw_enabled,true); ?>> فعال
        </label>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-top:10px;align-items:end;">
        <label>آستانه تازه (بازدید کمتر از):<br><input type="number" id="apex-autowatch-threshold" min="1000" max="50000" step="1000" value="<?php echo esc_attr($autowatch['threshold']); ?>" style="width:100%;"></label>
        <label>حداقل شارژ:<br><input type="number" id="apex-autowatch-min" min="100" max="5000" step="100" value="<?php echo esc_attr($autowatch['min_views']); ?>" style="width:100%;"></label>
        <label>حداکثر شارژ:<br><input type="number" id="apex-autowatch-max" min="1000" max="50000" step="1000" value="<?php echo esc_attr($autowatch['max_views']); ?>" style="width:100%;"></label>
        <label>هر تیک چند پست:<br><input type="number" id="apex-autowatch-batch" min="5" max="50" step="1" value="<?php echo esc_attr($autowatch['batch']); ?>" style="width:100%;"></label>
        <label>فقط پست‌های چند روز اخیر:<br><input type="number" id="apex-autowatch-recent" min="0" max="365" step="1" value="<?php echo esc_attr($autowatch['only_recent_days']); ?>" style="width:100%;"> <small>0=همه</small></label>
        <button id="apex-autowatch-save" type="button" class="button button-apex" style="background:#ea580c;border-color:#ea580c;">💾 ذخیره نگهبان</button>
      </div>
      <div style="margin-top:8px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
        <small style="color:#6b7280;">هر <code>apex_omega_tick</code> (5 دقیقه) تا <span id="apex-autowatch-batch-hint"><?php echo esc_html($autowatch['batch']); ?></span> پست کم‌بازدید چک می‌شود — با cooldown 24ساعته هر پست دوباره شارژ نمی‌شود.</small>
        <span id="apex-autowatch-result" style="font-weight:700;"></span>
        <?php if (!empty($aw_last['at'])): ?><small style="color:#065f46;">آخرین اجرا: <?php echo esc_html($aw_last['at']); ?> — <?php echo esc_html($aw_last['done']); ?> پست شارژ شد <code><?php echo esc_html(substr($aw_last['exec']??'',0,8)); ?></code></small><?php endif; ?>
      </div>
      <small>💡 خواننده تازه‌کار (وزن <30) → 500-3000، ویژه (≥85) → تا 1.6× بیشتر + Evergreen هر تیک صعودی. امتیاز هم همبسته (10-150 رأی، 3.3-5.0⭐).</small>
    </div>
    <?php $commentor = \ApexOmega\Core\CommentGuardian::get_settings(); $cm_enabled = !empty($commentor['enabled']); $cm_last = get_option('apex_omega_commentor_last_run', []); ?>
    <div class="apex-card" style="margin:12px 0; background:#faf5ff; border:1px solid #9333ea; border-right:4px solid #9333ea;">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <div>
          <h4 style="margin:0;color:#6b21a8;">💬 نگهبان کامنت هوشمند — کامنت‌گذاری خودکار ایرانی  ✅ جدید v1.7.0</h4>
          <small style="color:#581c87;">برای هر آهنگ بر اساس وزن خواننده + بازدید، کامنت فارسی مرتبط (AI) با نام ایرانی انگلیسی (Arad Moradi) می‌گذارد — تازه‌کار 2-3 تا معمولی، پرطرفدار تا 60 تا بازارم با رشد روزانه. کاملاً طبیعی.</small>
        </div>
        <label style="display:flex;align-items:center;gap:8px;background:#fff;border:1px solid #9333ea;border-radius:999px;padding:6px 14px;font-weight:800;cursor:pointer;">
          <input type="checkbox" id="apex-commentor-enabled" <?php checked($cm_enabled,true); ?>> فعال
        </label>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-top:10px;align-items:end;">
        <label>آستانه تازه (بازدید کمتر از):<br><input type="number" id="apex-commentor-threshold" min="1000" max="50000" step="1000" value="<?php echo esc_attr($commentor['threshold']); ?>" style="width:100%;"></label>
        <label>حداقل کامنت تازه:<br><input type="number" id="apex-commentor-min" min="1" max="5" step="1" value="<?php echo esc_attr($commentor['min_comments']); ?>" style="width:100%;"></label>
        <label>حداکثر کامنت تازه:<br><input type="number" id="apex-commentor-max" min="1" max="5" step="1" value="<?php echo esc_attr($commentor['max_comments']); ?>" style="width:100%;"></label>
        <label>رشد روزانه پرطرفدار:<br><input type="number" id="apex-commentor-daily" min="1" max="5" step="1" value="<?php echo esc_attr($commentor['daily_growth']); ?>" style="width:100%;"></label>
        <label>سقف هر پست:<br><input type="number" id="apex-commentor-maxpost" min="10" max="100" step="5" value="<?php echo esc_attr($commentor['max_per_post']); ?>" style="width:100%;"></label>
        <label>پردازش روزانه:<br><input type="number" id="apex-commentor-batch" min="0" max="500" step="10" value="<?php echo esc_attr($commentor['batch']); ?>" style="width:100%;"> <small>0=همهٔ پست‌ها روزانه (توصیه) — بدون سقف 20</small></label>
        <label>فقط پست‌های چند روز اخیر:<br><input type="number" id="apex-commentor-recent" min="0" max="365" step="1" value="<?php echo esc_attr($commentor['only_recent_days']); ?>" style="width:100%;"> <small>0=همه</small></label>
        <button type="button" id="apex-commentor-save" class="button button-apex" style="background:#7c3aed;border-color:#7c3aed;">💬 ذخیره نگهبان کامنت</button>
        <button type="button" id="apex-commentor-run" class="button button-apex secondary" style="background:#fff;color:#7c3aed;border-color:#7c3aed;">▶️ اجرای فوری (تست)</button>
      </div>
      <div style="margin-top:8px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
        <small style="color:#6b7280;">روزانه <strong>هر چی پست داری</strong> بر اساس الگوی ریتم (بازدید+امتیاز+وزن خواننده) چک می‌شود — اول پر‌بازدیدها، بعد تازه‌ها — هر پست cooldown 24ساعته. خوانندهٔ معروف با آهنگ تازه هم متعادل 3-5 کامنت می‌گیرد نه انفجاری.</small>
        <span id="apex-commentor-result" style="font-weight:700;"></span>
        <?php if (!empty($cm_last['at'])): ?><small style="color:#065f46;">آخرین اجرا: <?php echo esc_html($cm_last['at']); ?> — <?php echo esc_html($cm_last['posts']); ?> پست → <?php echo esc_html($cm_last['comments']); ?> کامنت <code><?php echo esc_html(substr($cm_last['exec']??'',0,8)); ?></code></small><?php endif; ?>
      </div>
      <small>💡 نام‌گذاری: 200 نام + 200 فامیل ایرانی (Arad/Moradi) با ترکیب اسم1+فامیل5 و سه فرمت (فقط اسم/فقط فامیل/کامل 20/20/60) — کامنت AI مرتبط به خواننده، fallback فارسی per tier (ساده/تحسین/هیجانی).</small>
    </div>
    <script>
    // 🚀 فیکس جامع 1.6.2 — همه دکمه‌های توزیع هوشمند بدون وابستگی به کش/مینیفایر jQuery
    (function(){
      function getNonce(){ return (window.ApexOmega&&ApexOmega.nonce) || document.querySelector('input[name="_wpnonce"]')?.value || document.querySelector('#_wpnonce')?.value || ''; }
      function getAjax(){ return (window.ApexOmega&&ApexOmega.ajax_url) || '/wp-admin/admin-ajax.php'; }
      function apexPost(action, extra){
        var fd=new FormData(); fd.append('action',action); fd.append('_ajax_nonce',getNonce());
        for(var k in extra){ if(extra.hasOwnProperty(k)) fd.append(k, extra[k]); }
        return fetch(getAjax(),{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){ return r.text().then(function(txt){ try{ return JSON.parse(txt); }catch(e){ var clean=txt.replace(/<[^>]*>/g,' ').trim().slice(0,300); throw new Error('پاسخ غیرمنتظره HTML به جای JSON — نشست منقضی یا خطای PHP: '+clean); } }); });
      }
      function qs(id){ return document.getElementById(id); }
      function collectCounts(){
        var out={}; document.querySelectorAll('.apex-tier-count').forEach(function(el){ var k=el.dataset.tier; var v=parseInt(el.value||0); if(v>0||el.value!=='') out[k]=v; });
        return out;
      }
      function bindOnce(id, handler){
        var el=qs(id); if(!el || el.dataset.vanilla) return; el.dataset.vanilla='1';
        el.addEventListener('click', function(e){ e.preventDefault(); e.stopImmediatePropagation(); handler(el,e); });
      }
      function run(){
        // Evergreen
        bindOnce('apex-evergreen-save', function(btn){
          btn.disabled=true; var o=btn.textContent; btn.textContent='ذخیره…';
          var enabled=qs('apex-evergreen-enabled')?.checked?1:0, rate=parseFloat(qs('apex-evergreen-rate')?.value)||0.04, max=parseInt(qs('apex-evergreen-max')?.value)||10000000;
          var resEl=qs('apex-evergreen-result'); if(resEl) resEl.textContent='در حال ذخیره…';
          apexPost('apex_save_evergreen',{enabled:enabled,rate_percent:rate,max_views:max}).then(function(j){
            if(j.success){ if(resEl){resEl.textContent='✅ ذخیره شد — '+(j.data.settings.enabled?'فعال':'غیرفعال')+' — نرخ '+j.data.settings.rate_percent+'%'; resEl.style.color='#065f46';}}
            else if(resEl){resEl.textContent='❌ '+(j.data.message||'خطا'); resEl.style.color='#991b1b';}
          }).catch(function(err){ if(resEl){resEl.textContent='❌ خطا: '+err; resEl.style.color='#991b1b';}}).finally(function(){ btn.disabled=false; btn.textContent=o; });
        });
        // Autowatch
        bindOnce('apex-autowatch-save', function(btn){
          btn.disabled=true; var o=btn.textContent; btn.textContent='ذخیره…';
          var enabled=qs('apex-autowatch-enabled')?.checked?1:0, threshold=parseInt(qs('apex-autowatch-threshold')?.value)||10000, minv=parseInt(qs('apex-autowatch-min')?.value)||500, maxv=parseInt(qs('apex-autowatch-max')?.value)||10000, batch=parseInt(qs('apex-autowatch-batch')?.value)||20, recent=parseInt(qs('apex-autowatch-recent')?.value)||60;
          var resEl=qs('apex-autowatch-result'); if(resEl) resEl.textContent='در حال ذخیره…';
          apexPost('apex_save_autowatch',{enabled:enabled,threshold:threshold,min_views:minv,max_views:maxv,batch:batch,only_recent_days:recent}).then(function(j){
            if(j.success){ if(resEl){resEl.textContent='✅ '+j.data.message+' — آستانه '+j.data.settings.threshold; resEl.style.color='#065f46';}}
            else if(resEl){resEl.textContent='❌ '+(j.data?.message||'خطا'); resEl.style.color='#991b1b';}
          }).catch(function(err){ if(resEl){resEl.textContent='❌ خطا: '+err; resEl.style.color='#991b1b';}}).finally(function(){ btn.disabled=false; btn.textContent='💾 ذخیره نگهبان'; });
        });
        // Commentor
        bindOnce('apex-commentor-save', function(btn){
          btn.disabled=true; var o=btn.textContent; btn.textContent='ذخیره…';
          var enabled=qs('apex-commentor-enabled')?.checked?1:0, threshold=parseInt(qs('apex-commentor-threshold')?.value)||10000, minc=parseInt(qs('apex-commentor-min')?.value)||2, maxc=parseInt(qs('apex-commentor-max')?.value)||4, daily=parseInt(qs('apex-commentor-daily')?.value)||2, maxpost=parseInt(qs('apex-commentor-maxpost')?.value)||60, batch=parseInt(qs('apex-commentor-batch')?.value), recent=parseInt(qs('apex-commentor-recent')?.value)||0; if(isNaN(batch)) batch=0;
          var resEl=qs('apex-commentor-result'); if(resEl) resEl.textContent='در حال ذخیره…';
          apexPost('apex_save_commentor',{enabled:enabled,threshold:threshold,min_comments:minc,max_comments:maxc,daily_growth:daily,max_per_post:maxpost,batch:batch,only_recent_days:recent}).then(function(j){
            if(j.success){ if(resEl){resEl.textContent='✅ '+j.data.message+' — آستانه '+j.data.settings.threshold; resEl.style.color='#065f46';}}
            else if(resEl){resEl.textContent='❌ '+(j.data?.message||'خطا'); resEl.style.color='#991b1b';}
          }).catch(function(err){ if(resEl){resEl.textContent='❌ خطا: '+err; resEl.style.color='#991b1b';}}).finally(function(){ btn.disabled=false; btn.textContent='💬 ذخیره نگهبان کامنت'; });
        });
        bindOnce('apex-commentor-run', function(btn){
          btn.disabled=true; var o=btn.textContent; btn.textContent='در حال اجرا…';
          var resEl=qs('apex-commentor-result'); if(resEl) resEl.textContent='⏳ در حال اجرای نگهبان…';
          apexPost('apex_run_commentor_now',{}).then(function(j){
            if(j.success){ if(resEl){ var l=j.data.last||{}; resEl.innerHTML='✅ اجرا شد: '+(l.posts||0)+' پست → '+(l.comments||0)+' کامنت'; resEl.style.color='#065f46'; if(l.posts>0) setTimeout(()=>location.reload(),1200);} }
            else if(resEl){ resEl.textContent='❌ '+(j.data?.message||'خطا'); resEl.style.color='#991b1b'; }
          }).catch(function(err){ if(resEl){resEl.textContent='❌ خطا: '+err; resEl.style.color='#991b1b';}}).finally(function(){ btn.disabled=false; btn.textContent='▶️ اجرای فوری (تست)'; });
        });
        // Rhythm
        bindOnce('apex-rhythm-save', function(btn){
          btn.disabled=true; var o=btn.textContent; btn.textContent='ذخیره…';
          var ar=(parseInt(qs('apex-rhythm-artist-ratio')?.value)||85)/100, th=parseInt(qs('apex-rhythm-threshold')?.value)||30, cap=parseInt(qs('apex-rhythm-cap')?.value)||45, pair=qs('apex-rhythm-pair')?.checked?1:0;
          var resEl=qs('apex-rhythm-result'); if(resEl) resEl.textContent='در حال ذخیره…';
          apexPost('apex_save_rhythm',{artist_ratio:ar,unknown_threshold:th,unknown_cap:cap,require_pair:pair}).then(function(j){
            if(j.success){ if(resEl) resEl.textContent='✅ '+j.data.message; resEl.style.color='#065f46';}
            else if(resEl){resEl.textContent='❌ '+(j.data.message||'خطا'); resEl.style.color='#991b1b';}
          }).catch(function(err){ if(resEl) resEl.textContent='❌ '+err; }).finally(function(){ btn.disabled=false; btn.textContent='💾 ذخیره الگوی ریتم';});
        });
        // Taxonomy
        bindOnce('apex-taxonomy-save', function(btn){
          btn.disabled=true; var o=btn.textContent; btn.textContent='ذخیره…';
          var tax=qs('apex-artist-taxonomy')?.value||'';
          var resEl=qs('apex-taxonomy-result');
          apexPost('apex_save_artist_taxonomy',{taxonomy:tax}).then(function(j){
            if(j.success){ if(resEl) resEl.textContent='✅ '+j.data.message+' — '+j.data.taxonomy; resEl.style.color='#065f46'; setTimeout(()=>location.reload(),700); }
            else if(resEl){resEl.textContent='❌ '+j.data.message; resEl.style.color='#991b1b';}
          }).catch(e=>{ if(resEl) resEl.textContent='❌ '+e;}).finally(()=>{ btn.disabled=false; btn.textContent=o;});
        });
        // Artist save / AI / IR
        bindOnce('apex-artist-save', function(btn){
          var weights={}; document.querySelectorAll('.apex-artist-weight').forEach(function(el){ weights[el.dataset.tid]=el.value; });
          btn.textContent='ذخیره…'; var resEl=qs('apex-artist-result');
          // weights as object needs special handling: use FormData with array notation
          var fd=new FormData(); fd.append('action','apex_save_artist_weights'); fd.append('_ajax_nonce',getNonce());
          for(var tid in weights) fd.append('weights['+tid+']', weights[tid]);
          fetch(getAjax(),{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(function(j){
            if(j.success){ if(resEl) resEl.innerHTML='<div class="apex-alert success">ذخیره شد ('+j.data.count+' آرتیست)</div>';}
            else if(resEl) resEl.innerHTML='<div class="apex-alert error">'+(j.data.message||'خطا')+'</div>';
          }).catch(e=>{ if(resEl) resEl.innerHTML='<div class="apex-alert error">❌ '+e+'</div>';}).finally(()=>btn.textContent='💾 ذخیره وزن‌ها');
        });
        bindOnce('apex-artist-ai', function(btn){
          btn.disabled=true; var o=btn.textContent; btn.textContent='AI در حال امتیازدهی…'; var resEl=qs('apex-artist-result');
          apexPost('apex_auto_artist_ai',{}).then(function(j){
            if(j.success){ Object.keys(j.data.weights||{}).forEach(function(tid){ var w=j.data.weights[tid]; var inp=document.querySelector('.apex-artist-weight[data-tid="'+tid+'"]'); if(inp) inp.value=w; var sl=document.querySelector('.apex-artist-slider[data-tid="'+tid+'"]'); if(sl) sl.value=w; }); if(resEl) resEl.innerHTML='<div class="apex-alert success">✅ AI امتیاز داد: '+(Object.keys(j.data.weights||{}).length)+' آرتیست</div>';}
            else if(resEl) resEl.innerHTML='<div class="apex-alert error">'+(j.data.message||'خطا')+'</div>';
          }).catch(e=>{ if(resEl) resEl.innerHTML='<div class="apex-alert error">❌ '+e+'</div>';}).finally(()=>{btn.disabled=false; btn.textContent=o;});
        });
        bindOnce('apex-artist-ir', function(btn){
          btn.disabled=true; var o=btn.textContent; btn.textContent='در حال خواندن از سایت ایرانی…'; var resEl=qs('apex-artist-result');
          apexPost('apex_auto_artist_ir',{}).then(function(j){
            if(j.success){ Object.keys(j.data.weights||{}).forEach(function(tid){ var w=j.data.weights[tid]; var inp=document.querySelector('.apex-artist-weight[data-tid="'+tid+'"]'); if(inp) inp.value=w; var sl=document.querySelector('.apex-artist-slider[data-tid="'+tid+'"]'); if(sl) sl.value=w; }); if(resEl) resEl.innerHTML='<div class="apex-alert success">🌐 از سایت ایرانی امتیاز گرفته شد: '+(Object.keys(j.data.weights||{}).length)+'</div>';}
            else if(resEl) resEl.innerHTML='<div class="apex-alert error">'+(j.data.message||'خطا')+'</div>';
          }).catch(e=>{ if(resEl) resEl.innerHTML='<div class="apex-alert error">❌ '+e+'</div>';}).finally(()=>{btn.disabled=false; btn.textContent=o;});
        });
        // AI save/test/suggest
        bindOnce('apex-ai-save', function(btn){
          var o=btn.textContent; btn.textContent='ذخیره…';
          apexPost('apex_ai_save',{api_key:qs('apex-ai-key')?.value||'',model:qs('apex-ai-model')?.value||'gpt-4o-mini',endpoint:qs('apex-ai-endpoint')?.value||''}).then(function(j){
            if(j.success) alert('ذخیره شد (رمزنگاری با AUTH_KEY)'); else alert(j.data.message||'خطا');
          }).finally(()=>btn.textContent=o);
        });
        bindOnce('apex-ai-test', function(btn){
          btn.disabled=true; var o=btn.textContent; btn.textContent='تست…'; var resEl=qs('apex-ai-test-result'); if(resEl) resEl.textContent='در حال تست…';
          apexPost('apex_test_ai',{}).then(function(j){
            if(j.success){ if(resEl) resEl.textContent='✅ '+j.data.message; if(resEl) resEl.style.color='#065f46';}
            else if(resEl){resEl.textContent='❌ '+(j.data.message||'خطا'); resEl.style.color='#991b1b';}
          }).catch(function(err){ if(resEl){resEl.textContent='❌ '+err; resEl.style.color='#991b1b';}}).finally(()=>{ btn.disabled=false; btn.textContent=o;});
        });
        bindOnce('apex-ai-suggest', function(btn){
          btn.disabled=true; var o=btn.textContent; btn.textContent='در حال پرسش از AI…'; var box=qs('apex-tier-preview-box');
          apexPost('apex_ai_suggest',{}).then(function(res){
            if(res.success && res.data.suggestion){
              Object.keys(res.data.suggestion).forEach(function(k){ var v=res.data.suggestion[k]; var inp=document.querySelector('.apex-tier-count[data-tier="'+k+'"]'); if(inp) inp.value=v; var sl=document.querySelector('.apex-tier-slider[data-tier="'+k+'"]'); if(sl) sl.value=v; });
              // trigger update totals if jQuery present
              if(window.jQuery) jQuery('.apex-tier-count').first().trigger('input');
              if(box) box.innerHTML='<div class="apex-alert success">✨ پیشنهاد AI اعمال شد ('+res.data.source+')</div>';
            } else {
              var msg=res.data?.message||'خطا'; if(box) box.innerHTML='<div class="apex-alert error">'+msg+'</div>';
              if(res.data?.fallback){ Object.keys(res.data.fallback).forEach(function(k){ var v=res.data.fallback[k]; var inp=document.querySelector('.apex-tier-count[data-tier="'+k+'"]'); if(inp) inp.value=v; });}
            }
          }).catch(e=>{ if(box) box.innerHTML='<div class="apex-alert error">❌ '+e+'</div>';}).finally(()=>{ btn.disabled=false; btn.textContent=o;});
        });
        // Presets
        bindOnce('apex-preset-pyramid', function(){
          var total=(window.ApexOmega&&ApexOmega.total_posts)||parseInt(document.querySelector('.apex-hero-stats .big')?.textContent.replace(/,/g,'')||'0');
          if(!total){ alert('تعداد کل آثار 0 است'); return;}
          var perc={tier_1:0.02,tier_2:0.04,tier_3:0.10,tier_4:0.14,tier_5:0.20,tier_6:0.25,tier_7:0.25}; var sum=0, out={};
          Object.keys(perc).forEach(function(k){ var c=Math.round(total*perc[k]); out[k]=c; sum+=c; var inp=document.querySelector('.apex-tier-count[data-tier="'+k+'"]'); if(inp) inp.value=c; var sl=document.querySelector('.apex-tier-slider[data-tier="'+k+'"]'); if(sl) sl.value=c;});
          out['tier_7']+= total - sum; var last=document.querySelector('.apex-tier-count[data-tier="tier_7"]'); if(last) last.value=out['tier_7']; var sl2=document.querySelector('.apex-tier-slider[data-tier="tier_7"]'); if(sl2) sl2.value=out['tier_7'];
          if(window.jQuery) jQuery('.apex-tier-count').first().trigger('input');
        });
        bindOnce('apex-preset-equal', function(){
          var total=(window.ApexOmega&&ApexOmega.total_posts)||0; if(!total) return; var per=Math.floor(total/7); var rem=total - per*7;
          ['tier_1','tier_2','tier_3','tier_4','tier_5','tier_6','tier_7'].forEach(function(k){ var v=per; if(rem>0){v++; rem--;} var inp=document.querySelector('.apex-tier-count[data-tier="'+k+'"]'); if(inp) inp.value=v; var sl=document.querySelector('.apex-tier-slider[data-tier="'+k+'"]'); if(sl) sl.value=v;});
          if(window.jQuery) jQuery('.apex-tier-count').first().trigger('input');
        });
        bindOnce('apex-preset-clear', function(){
          document.querySelectorAll('.apex-tier-count,.apex-tier-slider').forEach(function(el){ el.value=0;}); if(window.jQuery) jQuery('.apex-tier-count').first().trigger('input'); var b=qs('apex-tier-preview-box'); if(b) b.textContent='صفر شد.'; var d=qs('apex-tier-details-box'); if(d) d.style.display='none';
        });
        // v2.1.8: Weight sync — پیش‌نمایش و یک‌کلیک همگام‌سازی
        bindOnce('apex-weight-preview', function(btn){
          btn.disabled=true; var o=btn.textContent; btn.textContent='در حال پیش‌نمایش…';
          var cat=qs('apex-filter-cat')?.value||'all', pt=qs('apex-filter-pt')?.value||'any';
          var box=qs('apex-weight-preview-box'), resBox=qs('apex-weight-result');
          if(box) box.innerHTML='⏳ در حال محاسبه وزن هر پست…';
          apexPost('apex_preview_weight_sync',{category:cat,post_type:pt,limit:10000}).then(function(res){
            if(res.success){
              var html='<div class="apex-alert success">وزن‌محور: '+res.data.total+' آهنگ — هر هنرمند داخل بازه وزنش</div>';
              html+='<table class="apex-table"><tr><th>دسته (بازه وزن)</th><th>تعداد آهنگ</th><th>میانگین بازدید</th><th>میانگین رأی</th><th>بازه</th></tr>';
              for(var kk in res.data.summary){ var s=res.data.summary[kk]; html+='<tr><td><span style="display:inline-block;width:10px;height:10px;background:'+s.color+';border-radius:999px;margin-left:6px;vertical-align:middle;"></span>'+s.icon+' '+s.label+'</td><td><strong>'+s.count+'</strong></td><td>'+s.avg_views.toLocaleString()+ '</td><td>'+s.avg_rating+'</td><td>'+s.views_min.toLocaleString()+'–'+s.views_max.toLocaleString()+'</td></tr>'; }
              html+='</table><small style="color:#6b7280;">تیک بزن «همگام‌سازی همه» تا همه پست‌ها داخل همین بازه‌ها بروند — هیچ ۹۵ای ۱۰k نمی‌ماند.</small>';
              if(box) box.innerHTML=html;
              if(resBox) resBox.innerHTML='';
            } else if(box) box.innerHTML='<div class="apex-alert error">'+(res.data.message||'خطا')+'</div>';
          }).catch(function(err){ if(box) box.innerHTML='<div class="apex-alert error">❌ '+err.message+'</div>';}).finally(function(){ btn.disabled=false; btn.textContent=o;});
        });
        bindOnce('apex-weight-apply', function(btn){
          if(!confirm('همگام‌سازی وزن‌محور: همه آهنگ‌ها بر اساس وزن هنرمند بازنویسی می‌شوند (رندم داخل بازه هر تیره). اسنپ‌شات گرفته می‌شود و قابل Undo است. ادامه؟')) return;
          btn.disabled=true; var o=btn.textContent; btn.textContent='در حال همگام‌سازی…';
          var cat=qs('apex-filter-cat')?.value||'all', pt=qs('apex-filter-pt')?.value||'any';
          var resBox=qs('apex-weight-result'); var box=qs('apex-weight-preview-box');
          // اول preview سریع برای فهم total → بعد job chunked
          apexPost('apex_preview_weight_sync',{category:cat,post_type:pt,limit:10000}).then(function(prev){
            if(!prev.success) throw new Error(prev.data.message||'خطا در پیش‌نمایش');
            var total=prev.data.total||0;
            if(total===0) throw new Error('هیچ پستی یافت نشد');
            var useChunked = total > 80;
            if(!useChunked){
              // single request
              if(resBox) resBox.innerHTML='<div class="apex-alert warning">⏳ '+total+' آهنگ — مستقیم اعمال می‌شود…</div>';
              return apexPost('apex_apply_weight_sync',{category:cat,post_type:pt,limit:10000}).then(function(res){
                if(res.success){
                  if(resBox) resBox.innerHTML='<div class="apex-alert success">✅ '+res.data.applied+' آهنگ همگام شد — هر ۹۵ داخل 900K–10M — شناسه: '+res.data.execution_id+'</div>';
                  setTimeout(()=>location.reload(),2000);
                } else throw new Error(res.data.message||'خطا');
              });
            } else {
              // chunked job
              if(resBox) resBox.innerHTML='<div class="apex-alert success">🧩 کار وزن‌محور آغاز شد — <strong>'+total+'</strong> آهنگ — هر درخواست 100 تا<br><div style="background:#e5e7eb;border-radius:999px;height:16px;margin-top:8px;overflow:hidden;"><div id="apex-weight-bar" style="height:100%;width:0%;background:linear-gradient(90deg,#059669,#06b6d4);transition:width 0.4s;"></div></div><div id="apex-weight-txt" style="margin-top:6px;font-size:13px;">0 / '+total+' (0%) — آماده‌سازی...</div></div>';
              return apexPost('apex_start_weight_sync_job',{category:cat,post_type:pt,chunk_size:100}).then(function(res){
                if(!res.success) throw new Error(res.data.message||'خطا در ایجاد کار');
                var exec=res.data.execution_id;
                function processChunk(){
                  return apexPost('apex_process_weight_chunk',{execution_id:exec,chunk_size:100}).then(function(r2){
                    if(!r2.success){ var isLock=(r2.data&&r2.data.message||'').indexOf('قفل')!==-1; if(isLock){ var txt=qs('apex-weight-txt'); if(txt) txt.textContent='⏳ قفل — 3ثانیه…'; return new Promise(res=>setTimeout(res,3000)).then(processChunk); } throw new Error(r2.data.message||'خطا chunk'); }
                    var pct=r2.data.percent||0, proc=r2.data.processed||0;
                    var bar=qs('apex-weight-bar'), txt=qs('apex-weight-txt'); if(bar) bar.style.width=pct+'%'; if(txt) txt.textContent=proc+' / '+total+' ('+pct+'%) — '+(r2.data.status==='done'?'تمام!':'در حال پردازش...');
                    if(r2.data.status==='done'){ if(resBox) resBox.innerHTML+='<div class="apex-alert success" style="margin-top:8px;">✅ همگام‌سازی کامل — '+proc+' آهنگ — شناسه: '+exec+'</div>'; setTimeout(()=>location.reload(),1800); return; }
                    return new Promise(function(res){ setTimeout(res,700); }).then(processChunk);
                  });
                }
                return processChunk();
              });
            }
          }).catch(function(err){ if(resBox) resBox.innerHTML='<div class="apex-alert error">❌ '+(err.message||err)+'</div>';}).finally(function(){ btn.disabled=false; btn.textContent=o; });
        });

        // Preview / Apply
        bindOnce('apex-preview-tier', function(btn){
          btn.disabled=true; var o=btn.textContent; btn.textContent='در حال پیش‌نمایش…';
          var counts=collectCounts(); if(Object.keys(counts).length===0){ alert('حداقل یک دسته را پر کن'); btn.disabled=false; btn.textContent=o; return; }
          var cat=qs('apex-filter-cat')?.value||'all', pt=qs('apex-filter-pt')?.value||'any', aware=qs('apex-artist-aware')?.checked?1:0;
          var fd=new FormData(); fd.append('action','apex_preview_tier'); fd.append('_ajax_nonce',getNonce());
          for(var k in counts) fd.append('counts['+k+']', counts[k]); fd.append('category',cat); fd.append('post_type',pt); fd.append('artist_aware',aware);
          fetch(getAjax(),{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){ return r.text().then(function(txt){ try{ return JSON.parse(txt); }catch(e){ throw new Error('پاسخ HTML به جای JSON (نشست منقضی/خطای PHP): '+txt.slice(0,200).replace(/<[^>]*>/g,' ').trim()); } }); }).then(function(res){
            var box=qs('apex-tier-preview-box');
            if(res.success){
              var html='<div class="apex-alert success">تعداد درخواستی: '+res.data.total_requested+' | موجود: '+res.data.total_available+'</div>';
              html+='<table class="apex-table"><tr><th>دسته</th><th>تعداد</th><th>میانگین بازدید</th><th>میانگین رأی</th></tr>';
              for(var kk in res.data.summary){ var s=res.data.summary[kk]; html+='<tr><td>'+(s.icon||'')+' '+(s.label||kk)+'</td><td>'+s.count+'</td><td>'+s.avg_views+'</td><td>'+s.avg_rating+'</td></tr>';}
              html+='</table>';
              if(box) box.innerHTML=html;
            } else if(box) box.innerHTML='<div class="apex-alert error">'+(res.data.message||'خطا')+'</div>';
          }).catch(function(err){ var box=qs('apex-tier-preview-box'); if(box) box.innerHTML='<div class="apex-alert error">❌ '+err.message+'</div>';}).finally(function(){ btn.disabled=false; btn.textContent=o;});
        });

        // 1.9.3 chunked: start job → process 100/درخواست با progress bar (COR-07)
        bindOnce('apex-apply-tier', function(btn){
          if(!confirm('این عملیات تمام آثار انتخاب‌شده را بازنویسی می‌کند (با تاریخچه + اسنپ‌شات). ادامه؟')) return;
          btn.disabled=true; var o=btn.textContent; btn.textContent='در حال اعمال…';
          var counts=collectCounts(); var cat=qs('apex-filter-cat')?.value||'all', pt=qs('apex-filter-pt')?.value||'any', aware=qs('apex-artist-aware')?.checked?1:0, autoEn=qs('apex-auto-enable')?.checked?1:0;
          var totalReq=Object.values(counts).reduce((a,b)=>a+parseInt(b||0),0);
          var useChunked = totalReq > 100; // آستانه 1.9.3: >100 = job
          var resultBox=qs('apex-tier-result');
          if(!useChunked){
            // single-request قدیمی برای تعداد کم (<100) — سریع
            var fd=new FormData(); fd.append('action','apex_apply_tier'); fd.append('_ajax_nonce',getNonce());
            for(var k in counts) fd.append('counts['+k+']', counts[k]); fd.append('category',cat); fd.append('post_type',pt); fd.append('artist_aware',aware); fd.append('auto_enable',autoEn);
            function fetchWithRetry(fd, retries){
              retries = retries || 1;
              return fetch(getAjax(),{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){
                if(r.status===503 && retries>0){
                  if(resultBox) resultBox.innerHTML='<div class="apex-alert warning">⏳ سرور موقتا شلوغ است (503) — 5 ثانیه دیگر دوباره تلاش می‌شود...</div>';
                  return new Promise(function(res){ setTimeout(res,5000); }).then(function(){ return fetchWithRetry(fd, retries-1); });
                }
                return r.text().then(function(txt){ try{ return JSON.parse(txt); }catch(e){ var is503 = txt.indexOf('503')!==-1 || txt.indexOf('Service Unavailable')!==-1; if(is503) throw new Error('⛔ 503 — سرور شلوغ است. روش تکه‌تکه را امتحان کن یا در دو مرحله اعمال کن.'); throw new Error('پاسخ HTML به جای JSON: '+txt.slice(0,250).replace(/<[^>]*>/g,' ').trim()); } });
              });
            }
            fetchWithRetry(fd).then(function(res){
              if(res.success){
                if(resultBox) resultBox.innerHTML='<div class="apex-alert success">✅ '+res.data.applied+' آهنگ توزیع شد. شناسه: '+res.data.execution_id+'</div>';
                if(res.data.details && res.data.details.length){
                  var dbox=qs('apex-tier-details-box'); if(dbox) dbox.style.display='block';
                  var tbody=document.querySelector('#apex-result-table tbody'); if(tbody){ tbody.innerHTML=''; res.data.details.forEach(function(d){ var tr=document.createElement('tr'); tr.innerHTML='<td>'+d.post_title+' #'+d.post_id+'</td><td>'+d.tier+'</td><td>'+d.old_views+' → '+d.new_views+'</td><td>'+d.old_casts+' → '+d.new_casts+'</td><td>'+d.old_avg+' → '+d.new_avg+'</td>'; tbody.appendChild(tr);});}
                }
                setTimeout(()=>location.reload(),2200);
              } else if(resultBox) resultBox.innerHTML='<div class="apex-alert error">'+(res.data.message||'خطا')+'</div>';
            }).catch(function(err){ if(resultBox) resultBox.innerHTML='<div class="apex-alert error">❌ '+(err.message||err)+'</div>';}).finally(function(){ btn.disabled=false; btn.textContent=o;});
            return;
          }
          // chunked path: create job → sequential 100chunks
          var fd2=new FormData(); fd2.append('action','apex_start_tier_job'); fd2.append('_ajax_nonce',getNonce());
          for(var k in counts) fd2.append('counts['+k+']', counts[k]); fd2.append('category',cat); fd2.append('post_type',pt); fd2.append('artist_aware',aware); fd2.append('auto_enable',autoEn); fd2.append('chunk_size',100);
          fetch(getAjax(),{method:'POST',body:fd2,credentials:'same-origin'}).then(r=>r.text().then(t=>JSON.parse(t))).then(function(res){
            if(!res.success) throw new Error(res.data.message||'خطا در ایجاد کار');
            var exec=res.data.execution_id, total=res.data.total;
            if(resultBox) resultBox.innerHTML='<div class="apex-alert success">🧩 کار تکه‌تکه آغاز شد — <strong>'+total+'</strong> آهنگ — هر درخواست 100 تا<br><div style="background:#e5e7eb;border-radius:999px;height:16px;margin-top:8px;overflow:hidden;"><div id="apex-job-bar" style="height:100%;width:0%;background:linear-gradient(90deg,#10b981,#06b6d4);transition:width 0.4s;"></div></div><div id="apex-job-txt" style="margin-top:6px;font-size:13px;">0 / '+total+' (0%) — در حال پردازش...</div></div>';
            function processChunk(){
              var fd3=new FormData(); fd3.append('action','apex_process_tier_chunk'); fd3.append('_ajax_nonce',getNonce()); fd3.append('execution_id',exec); fd3.append('chunk_size',100);
              return fetch(getAjax(),{method:'POST',body:fd3,credentials:'same-origin'}).then(r=>r.text().then(t=>{ try{return JSON.parse(t);}catch(e){ throw new Error('HTML به جای JSON: '+t.slice(0,200).replace(/<[^>]*>/g,' '));}})).then(function(r2){
                if(!r2.success){
                  var isLock = (r2.data && r2.data.message||'').indexOf('قفل')!==-1;
                  if(isLock){ if(resultBox){ var txt=qs('apex-job-txt'); if(txt) txt.textContent='⏳ قفل — 3ثانیه دیگر...'; } return new Promise(res=>setTimeout(res,3000)).then(processChunk); }
                  throw new Error(r2.data.message||'خطا chunk');
                }
                var pct=r2.data.percent||0, proc=r2.data.processed||0;
                var bar=qs('apex-job-bar'), txt=qs('apex-job-txt');
                if(bar) bar.style.width=pct+'%'; if(txt) txt.textContent=proc+' / '+total+' ('+pct+'%) — '+(r2.data.status==='done'?'تمام!':'در حال پردازش...');
                if(r2.data.status==='done'){
                  if(resultBox) resultBox.innerHTML+='<div class="apex-alert success" style="margin-top:8px;">✅ توزیع تکه‌تکه کامل شد — '+proc+' آهنگ — شناسه: '+exec+'</div>';
                  setTimeout(()=>location.reload(),1800);
                  return;
                }
                // next chunk with tiny pause to let host breathe
                return new Promise(function(res){ setTimeout(res, 700); }).then(processChunk);
              });
            }
            return processChunk();
          }).catch(function(err){
            if(resultBox) resultBox.innerHTML='<div class="apex-alert error">❌ خطا در کار تکه‌تکه: '+(err.message||err)+'</div>';
          }).finally(function(){ btn.disabled=false; btn.textContent=o; });
        });
        // Undo
        bindOnce('apex-undo-tier', function(btn){
          if(!confirm('بازگردانی آخرین توزیع؟')) return;
          btn.disabled=true; var o=btn.textContent; btn.textContent='در حال بازگردانی…';
          var exec=btn.dataset.exec||''; var resEl=qs('apex-undo-result');
          apexPost('apex_undo_tier',{execution_id:exec}).then(function(j){
            if(j.success){ if(resEl) resEl.textContent='✅ بازگردانی شد: '+j.data.restored+' آهنگ'; setTimeout(()=>location.reload(),800);}
            else if(resEl){resEl.textContent='❌ '+(j.data.message||'خطا'); resEl.style.color='#991b1b';}
          }).catch(e=>{ if(resEl) resEl.textContent='❌ '+e;}).finally(()=>{ btn.disabled=false; btn.textContent=o;});
        });
        // rhythm live %
        var ratio=qs('apex-rhythm-artist-ratio'); if(ratio && !ratio.dataset.vanilla){
          ratio.dataset.vanilla='1';
          ratio.addEventListener('input', function(){ var v=parseInt(this.value||85); var a=qs('apex-rhythm-artist-val'); if(a) a.textContent=v+'%'; var t=qs('apex-rhythm-title-val'); if(t) t.textContent=(100-v)+'%';});
        }
      }
      if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',run); else run();
      setTimeout(run,600);
      // نمایش خطای نانس اگر خالی بود
      setTimeout(function(){ if(!getNonce()){ var warn=document.createElement('div'); warn.className='apex-alert warning'; warn.innerHTML='⚠️ <strong>نشانه امنیتی یافت نشد</strong> — اگر دکمه‌ها کار نکردند، صفحه را با Ctrl+Shift+R رفرش کن یا کش را خالی کن. (ApexOmega.nonce خالی است)'; var hero=document.querySelector('.apex-wrap'); if(hero) hero.prepend(warn); }},1200);
    })();
    </script>
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
        <button type="button" id="apex-ai-save" class="button button-apex">💾 ذخیره</button>
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
        <button type="button" id="apex-undo-tier" class="button button-apex secondary" data-exec="<?php echo esc_attr($last['exec'] ?? ''); ?>" style="background:#fef2f2; border-color:#fca5a5; color:#991b1b;">↩️ بازگردانی آخرین توزیع (Undo)</button>
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
    <strong>قابلیت‌های 1.5.0: حذف حالت ساده/حرفه‌ای، XSS/CSV/SSRF فیکس، همبستگی بازدید↔امتیاز، Evergreen تیره‌محور، الگوی ریتم حرفه‌ای | پیش‌تر 1.4.0: Evergreen + سقف 1.5M + منبع‌های جدید mybia2music/ganja2music/rezmusic + تشخیص عنوان هیت («بهت قول میدم») | پیش‌تر 1.2.2:</strong> اسنپ‌شات کامل قبل از هر توزیع (قابل Undo) — تراکنش یکپارچه هر پست (views+kksr همزمان) — قفل هوشمند <code>TTL = تعداد × ۲ ثانیه</code> — paging برای >۵۰۰۰ آهنگ — رمزنگاری کلید API با <code>AUTH_KEY</code> — تست اتصال GapGPT — انتخاب دستی تاکسونومی — حالت ساده/حرفه‌ای — جدول نتایج دقیق پس از اعمال — فیلتر ۷ روزه برای توزیع خودکار (هر آهنگ حداکثر هفته‌ای یک‌بار عوض می‌شود) — گزارش هفتگی ایمیلی و چک سلامت سایت.
  </div>
</div>
