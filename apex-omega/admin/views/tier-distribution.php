<?php if (!defined('ABSPATH')) exit;
$total_posts = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'");
$tiers = \ApexOmega\Core\TierManager::get_tiers();
$last = get_option(\ApexOmega\Core\TierManager::OPTION_LAST_DISTRIBUTION, []);
$auto = get_option('apex_omega_auto_tier', ['enabled'=>false, 'time'=>'03:00']);
$ai_set = \ApexOmega\Core\AI_Helper::get_settings();
$is_ai = \ApexOmega\Core\AI_Helper::is_configured();
$artist_tax = \ApexOmega\Core\ArtistManager::detect_artist_taxonomy();
$artists = $artist_tax ? \ApexOmega\Core\ArtistManager::get_all_artists($artist_tax, 80) : [];
?>
<div class="wrap apex-wrap">
  <div class="apex-hero">
    <div>
      <h1 style="margin:0;font-size:26px;">🚀 توزیع هوشمند دسته‌ای</h1>
      <p style="margin:6px 0 0;color:#50575e;">بدون انتخاب تکی — فقط بگو هر دسته چند آهنگ داشته باشد، بقیه خودکار و منطقی تنظیم می‌شود. <strong>کاملاً خودکار + آرتیست‌محور</strong></p>
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

  <!-- بخش آرتیست — کلید برای جلوگیری از رفتن آهنگ ناشناس به الماس -->
  <div class="apex-card" style="margin-top:14px; border-top:4px solid #f59e0b;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
      <h3 style="margin:0;">🎤 توزیع بر اساس محبوبیت آرتیست</h3>
      <label style="background:#fef3c7;border:1px solid #f59e0b;border-radius:999px;padding:6px 14px;font-weight:800;cursor:pointer;">
        <input type="checkbox" id="apex-artist-aware" checked> فعال: آرتیست‌های پرطرفدار → دسته الماس، ناشناس → تازه
      </label>
    </div>
    <p style="color:#6b7280;margin:8px 0 0;">وقتی فعال باشد، آهنگ‌های ابی/معین/گوگوش به‌طور خودکار در الماس می‌روند و آهنگ ناشناس هرگز به الماس نمی‌رود — حتی اگر تصادفی باشد. تاکسونومی شناسایی‌شده: <code><?php echo $artist_tax ? esc_html($artist_tax) : 'یافت نشد (از دسته/برچسب استفاده می‌شود)'; ?></code></p>

    <?php if ($artist_tax && !empty($artists)): ?>
      <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
        <button id="apex-artist-ai" class="button button-apex secondary">🤖 امتیازدهی خودکار با AI (GapGPT)</button>
        <button id="apex-artist-ir" class="button button-apex secondary">🌐 امتیاز از سایت ایرانی (رادیوجوان/نکس1موزیک)</button>
        <button id="apex-artist-save" class="button button-apex">💾 ذخیره وزن‌ها</button>
        <span style="margin-right:auto;color:#6b7280;font-size:12px;">وزن 0–100: 90+=الماس، 75+=پرطرفدار، ... 0–15=تازه</span>
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
      <small>💡 نکته: می‌توانی دستی وزن بدهی (مثلاً ابی 95، آرتیست جدید 10) یا دکمه AI/سایت ایرانی را بزنی تا 60 آرتیست اول خودکار امتیاز بگیرند. سپس در توزیع، آهنگ هر آرتیست طبق وزن به دسته درست می‌رود.</small>
    <?php elseif ($artist_tax): ?>
      <div class="apex-alert warning">تاکسونومی <?php echo esc_html($artist_tax); ?> خالی است.</div>
    <?php else: ?>
      <div class="apex-alert warning">
        تاکسونومی آرتیست یافت نشد. افزونه به‌صورت خودکار `artist / singer / post_tag` را چک کرده ولی چیزی نیافته.<br>
        راه‌حل: در `نوشته → برچسب‌ها` یا `دسته‌ها` یا افزونه CPT هنرمند را بساز، یا این بخش را نادیده بگیر — توزیع به‌صورت تصادفیِ هرمی همچنان کار می‌کند.
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
            <input type="range" class="apex-tier-slider" data-tier="<?php echo esc_attr($t['id']); ?>" min="0" max="<?php echo min(500, $total_posts); ?>" value="0">
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- پیش‌نمایش و اجرا -->
  <div class="apex-card" style="margin-top:18px;">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
      <h3 style="margin:0;">👁️ پیش‌نمایش</h3>
      <span style="margin-right:auto;color:#646970;" id="apex-preview-hint">تعدادها را پر کن → پیش‌نمایش → اعمال</span>
    </div>
    <div class="apex-actions" style="margin:12px 0;">
      <button id="apex-preview-tier" class="button-apex secondary">👁️ پیش‌نمایش</button>
      <button id="apex-apply-tier" class="button-apex" style="background:#00a32a;">✅ اعمال خودکار</button>
      <label style="margin-right:12px;"><input type="checkbox" id="apex-auto-enable"> خودکار روزانه 03:00</label>
    </div>
    <div id="apex-tier-preview-box" class="apex-preview-box" style="min-height:90px;">هنوز پیش‌نمایشی نیست.</div>
    <div id="apex-tier-result"></div>
  </div>

  <!-- AI -->
  <div class="apex-card" style="margin-top:18px;">
    <h3>🤖 GapGPT — پیشنهاد کاملاً خودکار (اختیاری)</h3>
    <p>کلید از <a href="https://gapgpt.app/platform-v2" target="_blank">gapgpt.app</a> — <a href="https://gapgpt.app/platform-v2/docs/quickstart" target="_blank">quickstart</a></p>
    <form id="apex-ai-form" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="apex-field"><label>Endpoint</label><input type="url" id="apex-ai-endpoint" value="<?php echo esc_attr($ai_set['endpoint']); ?>"></div>
      <div class="apex-field"><label>مدل</label><input type="text" id="apex-ai-model" value="<?php echo esc_attr($ai_set['model']); ?>"></div>
      <div class="apex-field"><label>API Key</label><input type="password" id="apex-ai-key" value="<?php echo $is_ai ? '••••••••' : ''; ?>" placeholder="sk-..."></div>
      <div class="apex-field" style="display:flex;align-items:end;"><button id="apex-ai-save" class="button button-apex">💾 ذخیره</button></div>
    </form>
  </div>

  <?php if (!empty($last)): ?>
    <div class="apex-card" style="margin-top:16px;border-right:4px solid #2271b1;">
      <h4>آخرین توزیع: <?php echo esc_html($last['at'] ?? '—'); ?> — <?php echo esc_html($last['applied'] ?? 0); ?> آهنگ — <code><?php echo esc_html(substr($last['exec'] ?? '',0,8)); ?></code></h4>
      <small><?php echo esc_html(wp_json_encode($last['counts'] ?? [], JSON_UNESCAPED_UNICODE)); ?></small>
    </div>
  <?php endif; ?>

  <div class="apex-alert success">
    <strong>چطور آرتیست‌محور کار می‌کند؟</strong> وقتی تیک بالا فعال باشد، آهنگ‌ها بر اساس وزن آرتیست مرتب می‌شوند (95=اول الماس، 10=آخر تازه). یعنی حتی اگر دسته پرطرفدار ویژه 10 تا ظرفیت دارد، 10 آرتیستِ 95+ انتخاب می‌شوند، نه تصادفی. وزن را می‌توانی دستی بدهی یا با یک کلیک از AI / سایت ایرانی پر کنی (کش 24ساعته، بدون فشار به سایت مقصد).
  </div>
</div>
