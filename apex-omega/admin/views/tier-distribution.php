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
<div class="wrap apex-wrap">
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
      <button id="apex-ai-suggest" class="button button-apex">✨ پیشنهاد AI برای دسته‌ها</button>
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


  <!-- الگوی ریتم حرفه‌ای — اولویت خواننده (درخواست شما) -->
  <?php $rhythm = \ApexOmega\Core\ArtistManager::get_rhythm_settings(); ?>
  <div class="apex-card" style="margin-top:14px; border-top:4px solid #7c3aed; background:#f5f3ff;">
    <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:10px;">
      <div>
        <h3 style="margin:0;">🎛️ الگوی ریتم حرفه‌ای — اولویت  با  اسم خواننده</h3>
        <small style="color:#6b7280;">خواننده نوپا حتی با عنوان &quot;بهت قول میدم&quot; هرگز یک‌شبه ملیونی نمی‌شود — مو لای درزش نمی‌رود. عنوان فقط وقتی اعتبار دارد که جفت خواننده+عنوان تایید شود.</small>
      </div>
      <span class="apex-badge" style="background:#7c3aed;color:#fff;">قابل کنترل کامل</span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:12px;">
      <label>نسبت وزن خواننده (%):<br><input type="range" id="apex-rhythm-artist-ratio" min="50" max="95" step="5" value="<?php echo esc_attr((int)($rhythm['artist_ratio']*100)); ?>" style="width:100%;"> <strong id="apex-rhythm-artist-val"><?php echo (int)($rhythm['artist_ratio']*100); ?>%</strong> <small style="color:#6b7280;">عنوان: <span id="apex-rhythm-title-val"><?php echo (int)($rhythm['title_ratio']*100); ?>%</span></small></label>
      <label>آستانه نوپا:<br><input type="number" id="apex-rhythm-threshold" min="15" max="50" value="<?php echo esc_attr($rhythm['unknown_threshold']); ?>" style="width:100%;"> <small style="color:#6b7280;">خواننده با وزن کمتر از این = نوپا (عنوان تقریبا نادیده)</small></label>
      <label>سقف نوپا (حتی با هیت):<br><input type="number" id="apex-rhythm-cap" min="30" max="65" value="<?php echo esc_attr($rhythm['unknown_cap']); ?>" style="width:100%;"> <small style="color:#6b7280;">وزن نهایی نوپا از این بیشتر نمی‌شود — مثلا 45 ≈ دسته 4</small></label>
      <label style="display:flex;align-items:center;gap:8px;background:#fff;border:1px solid #ddd;border-radius:10px;padding:10px;align-self:end;">
        <input type="checkbox" id="apex-rhythm-pair" <?php checked($rhythm['require_pair'], true); ?>> <span><strong>جفت خواننده+عنوان الزامی</strong><br><small>اگر تیک باشد، عنوان تکراریِ نوپا اعتبار ندارد</small></span>
      </label>
    </div>
    <div style="margin-top:10px; background:#fff; border:1px dashed #7c3aed; border-radius:10px; padding:10px; font-size:13px; color:#4c1d95;">
      <strong>مثال کنترل‌شده:</strong> محسن یگانه (وزن 96) + &quot;بهت قول میدم&quot; = <code>96×0.85 + 99×0.15 = 96.5 → الماس</code> | خواننده نوپا (وزن 18) + همین عنوان = <code>18×0.97 + 99×0.03 = 20.4 → تازه (سقف 45)</code> — هرگز جهش نمی‌کند.
    </div>
    <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
      <button id="apex-rhythm-save" class="button button-apex" style="background:#7c3aed;border-color:#7c3aed;">💾 ذخیره الگوی ریتم</button>
      <span id="apex-rhythm-result" style="font-weight:700;"></span>
    </div>
    <small>💡 این الگو در پیش‌نمایش و اعمال و همچنین در AI/سایت ایرانی لحاظ می‌شود. با فیلتر <code>apex_omega_hit_pairs</code> جفت‌های هیت واقعی خودت را اضافه کن.</small>
  </div>

  <!-- نوار ابزار سریع -->
  <div class="apex-card" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:16px 0;">
    <strong>ابزار سریع:</strong>
    <button id="apex-preset-pyramid" class="button">🔺 هرم استاندارد</button>
    <button id="apex-preset-equal" class="button">⚖️ مساوی</button>
    <button id="apex-preset-clear" class="button">🧹 صفر</button>
    <span style="margin-right:auto;"></span>
    <label>فیلتر دسته:
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
    <?php $evergreen = \ApexOmega\Core\Evergreen::get_settings(); $ever_enabled = !empty($evergreen['enabled']); ?>
    <div class="apex-card" style="margin:12px 0; background:#f0fdfa; border:1px solid #14b8a6; border-right:4px solid #14b8a6;">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <div>
          <h4 style="margin:0;color:#0f766e;">🌱 رشد مداوم Evergreen — هر ۵ دقیقه افزایش خودکار  ✅ جدید v1.4.0</h4>
          <small style="color:#134e4a;">بعد از توزیع اولیه، بازدید/امتیاز همه آهنگ‌ها به‌صورت کاملاً طبیعی و تدریجی تا سقف 1.5M رشد می‌کند — بدون نیاز به دخالت دستی، دقیقاً مثل ترافیک واقعی روزمره.</small>
        </div>
        <label style="display:flex;align-items:center;gap:8px;background:#fff;border:1px solid #14b8a6;border-radius:999px;padding:6px 14px;font-weight:800;cursor:pointer;">
          <input type="checkbox" id="apex-evergreen-enabled" <?php checked($ever_enabled,true); ?>> فعال
        </label>
      </div>
      <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin-top:10px;">
        <label>نرخ رشد هر ۵ دقیقه (%):<br><input type="number" id="apex-evergreen-rate" step="0.01" min="0.005" max="0.5" value="<?php echo esc_attr($evergreen['rate_percent']); ?>" style="width:110px;"></label>
        <label>سقف بازدید:<br><input type="number" id="apex-evergreen-max" min="50000" max="5000000" value="<?php echo esc_attr($evergreen['max_views']); ?>" style="width:140px;"></label>
        <button id="apex-evergreen-save" class="button button-apex" style="background:#0f766e;border-color:#0f766e;">💾 ذخیره Evergreen</button>
        <span id="apex-evergreen-result" style="font-weight:700;"></span>
        <small style="width:100%;color:#6b7280;">پیشنهاد: 0.03–0.05% = ≈ 5–8% رشد روزانه (واقع‌گرایانه). سقف 1.5M برای حفظ اعتبار — بالاتر نرو.</small>
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
        <button id="apex-autowatch-save" class="button button-apex" style="background:#ea580c;border-color:#ea580c;">💾 ذخیره نگهبان</button>
      </div>
      <div style="margin-top:8px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
        <small style="color:#6b7280;">هر <code>apex_omega_tick</code> (5 دقیقه) تا <span id="apex-autowatch-batch-hint"><?php echo esc_html($autowatch['batch']); ?></span> پست کم‌بازدید چک می‌شود — با cooldown 24ساعته هر پست دوباره شارژ نمی‌شود.</small>
        <span id="apex-autowatch-result" style="font-weight:700;"></span>
        <?php if (!empty($aw_last['at'])): ?><small style="color:#065f46;">آخرین اجرا: <?php echo esc_html($aw_last['at']); ?> — <?php echo esc_html($aw_last['done']); ?> پست شارژ شد <code><?php echo esc_html(substr($aw_last['exec']??'',0,8)); ?></code></small><?php endif; ?>
      </div>
      <small>💡 خواننده تازه‌کار (وزن <30) → 500-3000، ویژه (≥85) → تا 1.6× بیشتر + Evergreen هر تیک صعودی. امتیاز هم همبسته (10-150 رأی، 3.3-5.0⭐).</small>
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
    <strong>قابلیت‌های 1.5.0: حذف حالت ساده/حرفه‌ای، XSS/CSV/SSRF فیکس، همبستگی بازدید↔امتیاز، Evergreen تیره‌محور، الگوی ریتم حرفه‌ای | پیش‌تر 1.4.0: Evergreen + سقف 1.5M + منبع‌های جدید mybia2music/ganja2music/rezmusic + تشخیص عنوان هیت («بهت قول میدم») | پیش‌تر 1.2.2:</strong> اسنپ‌شات کامل قبل از هر توزیع (قابل Undo) — تراکنش یکپارچه هر پست (views+kksr همزمان) — قفل هوشمند <code>TTL = تعداد × ۲ ثانیه</code> — paging برای >۵۰۰۰ آهنگ — رمزنگاری کلید API با <code>AUTH_KEY</code> — تست اتصال GapGPT — انتخاب دستی تاکسونومی — حالت ساده/حرفه‌ای — جدول نتایج دقیق پس از اعمال — فیلتر ۷ روزه برای توزیع خودکار (هر آهنگ حداکثر هفته‌ای یک‌بار عوض می‌شود) — گزارش هفتگی ایمیلی و چک سلامت سایت.
  </div>
</div>
