<?php if (!defined('ABSPATH')) exit;
$total_posts = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'");
$tiers = \ApexOmega\Core\TierManager::get_tiers();
$last = get_option(\ApexOmega\Core\TierManager::OPTION_LAST_DISTRIBUTION, []);
$auto = get_option('apex_omega_auto_tier', ['enabled'=>false, 'time'=>'03:00']);
$ai_set = \ApexOmega\Core\AI_Helper::get_settings();
$is_ai = \ApexOmega\Core\AI_Helper::is_configured();
?>
<div class="wrap apex-wrap">
  <div class="apex-hero">
    <div>
      <h1 style="margin:0;font-size:26px;">🚀 توزیع هوشمند دسته‌ای</h1>
      <p style="margin:6px 0 0;color:#50575e;">بدون انتخاب تکی — فقط بگو هر دسته چند آهنگ داشته باشد، بقیه خودکار و منطقی تنظیم می‌شود. <strong>کاملاً خودکار هم می‌تواند باشد.</strong></p>
    </div>
    <div class="apex-hero-stats">
      <div><span class="big"><?php echo esc_html(number_format_i18n($total_posts)); ?></span><small>آهنگ منتشرشده</small></div>
      <div><span class="big" id="apex-total-selected">0</span><small>انتخاب‌شده برای توزیع</small></div>
      <div><span class="big" id="apex-remaining"><?php echo esc_html($total_posts); ?></span><small>باقی‌مانده</small></div>
    </div>
  </div>

  <?php if (!$is_ai): ?>
    <div class="apex-alert warning" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
      <span>💡 <strong>هوش مصنوعی وصل نیست</strong> — توزیع با الگوی هرم استاندارد انجام می‌شود. برای پیشنهاد هوشمند GapGPT، در تنظیمات کلید API را وارد کن.</span>
      <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-settings')); ?>" class="button button-apex secondary">تنظیم AI →</a>
    </div>
  <?php else: ?>
    <div class="apex-alert success" style="display:flex;justify-content:space-between;align-items:center;">
      <span>✨ <strong>GapGPT فعال</strong> (<?php echo esc_html($ai_set['model']); ?>) — می‌توانی با یک کلیک پیشنهاد هوشمند بگیری.</span>
      <button id="apex-ai-suggest" class="button button-apex">✨ پیشنهاد AI</button>
    </div>
  <?php endif; ?>

  <!-- نوار ابزار سریع -->
  <div class="apex-card" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:16px 0;">
    <strong>ابزار سریع:</strong>
    <button id="apex-preset-pyramid" class="button">🔺 هرم استاندارد (توصیه‌شده)</button>
    <button id="apex-preset-equal" class="button">⚖️ مساوی</button>
    <button id="apex-preset-clear" class="button">🧹 صفر کردن</button>
    <span style="margin-right:auto;"></span>
    <label>فیلتر دسته:
      <select id="apex-filter-cat" style="min-width:160px;">
        <option value="all">همه دسته‌ها</option>
        <?php $cats=get_categories(['hide_empty'=>false]); foreach($cats as $c): ?>
          <option value="<?php echo esc_attr($c->slug); ?>"><?php echo esc_html($c->name); ?> (<?php echo $c->count; ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>نوع نوشته:
      <select id="apex-filter-pt">
        <?php $pts=get_post_types(['public'=>true],'objects'); foreach($pts as $pt): if($pt->name==='attachment') continue; ?>
          <option value="<?php echo esc_attr($pt->name); ?>" <?php selected($pt->name,'post'); ?>><?php echo esc_html($pt->label); ?></option>
        <?php endforeach; ?>
        <option value="any">همه انواع</option>
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
          <span class="apex-tier-badge"><?php echo esc_html(number_format_i18n($t['views_min'])); ?> – <?php echo esc_html(number_format_i18n($t['views_max'])); ?></span>
        </div>
        <div class="apex-tier-ranges">
          <div>👁️ <strong><?php echo number_format_i18n($t['views_min']); ?></strong> تا <strong><?php echo number_format_i18n($t['views_max']); ?></strong> بازدید</div>
          <div>🗳️ <strong><?php echo number_format_i18n($t['casts_min']); ?></strong>–<strong><?php echo number_format_i18n($t['casts_max']); ?></strong> رأی &nbsp;|&nbsp; ⭐ <strong><?php echo $t['avg_min']; ?></strong>–<strong><?php echo $t['avg_max']; ?></strong></div>
        </div>
        <div class="apex-tier-control">
          <label>تعداد آهنگ در این دسته</label>
          <div class="apex-stepper">
            <button class="apex-minus" data-tier="<?php echo esc_attr($t['id']); ?>">−</button>
            <input type="number" class="apex-tier-count" data-tier="<?php echo esc_attr($t['id']); ?>" value="0" min="0" max="<?php echo esc_attr($total_posts); ?>" step="1" placeholder="0">
            <button class="apex-plus" data-tier="<?php echo esc_attr($t['id']); ?>">+</button>
          </div>
          <div class="apex-tier-slider-wrap">
            <input type="range" class="apex-tier-slider" data-tier="<?php echo esc_attr($t['id']); ?>" min="0" max="<?php echo min(500, $total_posts); ?>" value="0">
          </div>
        </div>
        <div class="apex-tier-preview-mini" id="preview-<?php echo esc_attr($t['id']); ?>" style="display:none;">
          <small>میانگین بازدید: <span class="pm-avg-views">—</span> | امتیاز: <span class="pm-avg">—</span></small>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- پیش‌نمایش و اجرا -->
  <div class="apex-card" style="margin-top:18px;">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
      <h3 style="margin:0;">👁️ پیش‌نمایش توزیع</h3>
      <span style="margin-right:auto;color:#646970;" id="apex-preview-hint">تعدادها را تنظیم کن و پیش‌نمایش بزن — هیچ چیزی هنوز ذخیره نشده.</span>
    </div>
    <div class="apex-actions" style="margin:12px 0;">
      <button id="apex-preview-tier" class="button-apex secondary">👁️ پیش‌نمایش</button>
      <button id="apex-apply-tier" class="button-apex" style="background:#00a32a;">✅ اعمال خودکار (با ثبت تاریخچه)</button>
      <label style="margin-right:12px;"><input type="checkbox" id="apex-auto-enable"> فعال‌سازی توزیع خودکار روزانه (ساعت 03:00)</label>
    </div>
    <div id="apex-tier-preview-box" class="apex-preview-box" style="min-height:90px;">هنوز پیش‌نمایشی نیست.</div>
    <div id="apex-tier-result"></div>
  </div>

  <!-- تنظیم AI -->
  <div class="apex-card" style="margin-top:18px;">
    <h3>🤖 اتصال GapGPT (اختیاری — برای پیشنهاد کاملاً خودکار)</h3>
    <p>کلید را از <a href="https://gapgpt.app/platform-v2" target="_blank">gapgpt.app/platform-v2</a> بگیر. مستندات: <a href="https://gapgpt.app/platform-v2/docs/quickstart" target="_blank">quickstart</a>. هزینه بسیار کم و سازگار با OpenAI است.</p>
    <form id="apex-ai-form" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="apex-field"><label>Endpoint</label><input type="url" id="apex-ai-endpoint" value="<?php echo esc_attr($ai_set['endpoint']); ?>" placeholder="https://api.gapgpt.app/v1/chat/completions"></div>
      <div class="apex-field"><label>مدل</label><input type="text" id="apex-ai-model" value="<?php echo esc_attr($ai_set['model']); ?>" placeholder="gpt-4o-mini"></div>
      <div class="apex-field"><label>API Key (sk-...)</label><input type="password" id="apex-ai-key" value="<?php echo $is_ai ? '••••••••' : ''; ?>" placeholder="sk-..."></div>
      <div class="apex-field" style="display:flex;align-items:end;"><button id="apex-ai-save" class="button button-apex">💾 ذخیره کلید</button></div>
    </form>
    <small>کلید فقط در `wp_options` ذخیره می‌شود و هرگز لاگ نمی‌شود. برای تست: بعد از ذخیره، دکمه «پیشنهاد AI» را بزن.</small>
  </div>

  <?php if (!empty($last)): ?>
    <div class="apex-card" style="margin-top:16px;border-right:4px solid #2271b1;">
      <h4>آخرین توزیع: <?php echo esc_html($last['at'] ?? '—'); ?> — <?php echo esc_html($last['applied'] ?? 0); ?> آهنگ — شناسه: <code><?php echo esc_html(substr($last['exec'] ?? '',0,8)); ?></code></h4>
      <small>تعدادها: <?php echo esc_html(wp_json_encode($last['counts'] ?? [], JSON_UNESCAPED_UNICODE)); ?></small>
    </div>
  <?php endif; ?>

  <div class="apex-alert success">
    <strong>💡 چطور کار می‌کند؟</strong><br>
    1. هر دسته یک بازه منطقی بازدید/رأی/امتیاز دارد (هرچه پرطرفدارتر، میانگین بالاتر).<br>
    2. برای هر آهنگ، یک عدد تصادفی داخل بازه انتخاب می‌شود — نه همه یکی!<br>
    3. `views` مستقیم روی فیلد قالب، `casts/ratings/avg` سازگار با فرمول `avg*casts/5*best` ذخیره می‌شود.<br>
    4. همه تغییرات با `tier_distribution` و `execution_id` در تاریخچه ثبت و قابل بازگردانی است.<br>
    5. اگر <strong>توزیع خودکار روزانه</strong> را تیک بزنی، هر شب ساعت 03:00 همین توزیع روی آثار جدید هم اعمال می‌شود — بدون دخالت انسانی.
  </div>
</div>
