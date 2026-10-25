<?php if (!defined('ABSPATH')) exit;
// Gather current values for glass panel
$all_settings = wp_parse_args(get_option('apex_omega_settings', []), [
    'large_drop_threshold_percent' => 20,
    'max_default_step_views'       => 500,
    'max_default_percent'          => 15,
    'enable_real_cron_hint'        => true,
    'remove_data_on_uninstall'     => false,
    'never_decrease_views'         => true,
]);
$ai_set   = \ApexOmega\Core\AI_Helper::get_settings();
$rhythm   = \ApexOmega\Core\ArtistManager::get_rhythm_settings();
$ever     = \ApexOmega\Core\Evergreen::get_settings();
$watch    = \ApexOmega\Core\AutoWatch::get_settings();
$comm     = \ApexOmega\Core\CommentGuardian::get_settings();
$cron_ok  = (bool) wp_next_scheduled(\ApexOmega\Core\Scheduler::CRON_HOOK);
$next_cron = $cron_ok ? get_date_from_gmt(gmdate('Y-m-d H:i:s', wp_next_scheduled(\ApexOmega\Core\Scheduler::CRON_HOOK)), 'Y-m-d H:i:s') : '—';
?>
<div class="wrap apex-settings-root" dir="rtl">
<style>
/* ── APEX Ω Settings — Glass (iPhone) ── */
.apex-settings-root{--glass-bg:rgba(255,255,255,.68);--glass-brd:rgba(255,255,255,.62);--glass-blur:16px;--ink:#0f172a;--muted:#64748b;--accent:#5b5bd6;--accent-2:#06b6d4;--radius:18px;max-width:1120px;margin:18px 0 40px}
.apex-settings-root *{box-sizing:border-box}
.apex-g-header{display:flex;gap:16px;align-items:center;justify-content:space-between;flex-wrap:wrap;padding:18px 20px;border-radius:var(--radius);background:linear-gradient(135deg,rgba(255,255,255,.85),rgba(243,244,255,.75));backdrop-filter:blur(var(--glass-blur)) saturate(180%);-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(180%);border:1px solid var(--glass-brd);box-shadow:0 8px 32px rgba(31,38,135,.08)}
.apex-g-title h1{margin:0;font-size:18px;font-weight:800;letter-spacing:-.02em;color:var(--ink);line-height:1.2}
.apex-g-title p{margin:6px 0 0;color:var(--muted);font-size:13px;line-height:1.6;max-width:640px}
.apex-g-actions{display:flex;gap:10px;align-items:center}
.apex-btn-primary{appearance:none;border:1px solid rgba(91,91,214,.35);background:var(--accent);color:#fff;padding:10px 16px;border-radius:12px;font-weight:700;font-size:13px;cursor:pointer;box-shadow:0 6px 16px rgba(91,91,214,.22);transition:.15s}
.apex-btn-primary:hover{transform:translateY(-1px);box-shadow:0 10px 22px rgba(91,91,214,.26)}
.apex-btn-ghost{appearance:none;border:1px solid rgba(15,23,42,.08);background:rgba(255,255,255,.6);color:var(--ink);padding:10px 14px;border-radius:12px;font-weight:600;font-size:13px;cursor:pointer;backdrop-filter:blur(8px)}
.apex-g-layout{display:grid;grid-template-columns:220px 1fr;gap:14px;margin-top:14px}
@media(max-width:860px){.apex-g-layout{grid-template-columns:1fr}}
.apex-g-nav{display:flex;flex-direction:column;gap:8px;position:sticky;top:32px;align-self:start}
@media(max-width:860px){.apex-g-nav{flex-direction:row;overflow:auto;padding-bottom:6px;position:static}}
.apex-g-tab{appearance:none;text-align:right;width:100%;padding:11px 13px;border-radius:14px;border:1px solid transparent;background:rgba(255,255,255,.5);color:#1e293b;font-weight:600;font-size:13px;cursor:pointer;display:flex;gap:10px;align-items:center;transition:.15s;white-space:nowrap}
.apex-g-tab[aria-selected="true"]{background:rgba(91,91,214,.10);border-color:rgba(91,91,214,.18);color:#312e81;box-shadow:0 4px 14px rgba(91,91,214,.10)}
.apex-g-tab:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
.apex-g-tab svg{width:18px;height:18px;flex:0 0 18px;opacity:.9}
.apex-g-panels{min-width:0}
.apex-g-panel{display:none}
.apex-g-panel.is-active{display:block;animation:apexFade .18s ease}
/* fix: if JS disabled, show first panel */
.apex-g-panel:first-of-type{display:block}
.js-enabled .apex-g-panel{display:none}
.js-enabled .apex-g-panel.is-active{display:block}
@keyframes apexFade{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:none}}
.apex-g-card{background:var(--glass-bg);backdrop-filter:blur(var(--glass-blur)) saturate(180%);-webkit-backdrop-filter:blur(var(--glass-blur)) saturate(180%);border:1px solid var(--glass-brd);border-radius:var(--radius);box-shadow:0 8px 32px rgba(31,38,135,.06);padding:16px 16px 14px;margin-bottom:12px}
.apex-g-card h3{margin:0 0 6px;font-size:13px;font-weight:800;color:var(--ink);letter-spacing:-.015em;display:flex;gap:8px;align-items:center}
.apex-g-card h3 span.dot{width:8px;height:8px;border-radius:999px;background:var(--accent);display:inline-block}
.apex-g-card .desc{margin:0 0 12px;color:var(--muted);font-size:12px;line-height:1.7}
.apex-g-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:860px){.apex-g-grid{grid-template-columns:1fr}}
.apex-f{display:flex;flex-direction:column;gap:7px}
.apex-f label{font-size:12px;font-weight:700;color:#1e293b}
.apex-f input[type="number"],.apex-f input[type="text"],.apex-f input[type="password"],.apex-f input[type="url"],.apex-f select{width:100%;padding:10px 12px;border-radius:12px;border:1px solid rgba(15,23,42,.08);background:rgba(255,255,255,.72);color:var(--ink);font-size:13px;transition:.15s;outline:none}
.apex-f input:focus,.apex-f select:focus{border-color:rgba(91,91,214,.35);box-shadow:0 0 0 4px rgba(91,91,214,.10)}
.apex-f small{color:var(--muted);font-size:11px;line-height:1.6}
.apex-switch{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:12px;background:rgba(255,255,255,.55);border:1px solid rgba(15,23,42,.06)}
.apex-switch input{width:36px;height:20px;appearance:none;background:#e2e8f0;border-radius:999px;position:relative;cursor:pointer;transition:.2s;flex:0 0 auto}
.apex-switch input:checked{background:var(--accent)}
.apex-switch input::after{content:"";position:absolute;top:2px;right:2px;width:16px;height:16px;border-radius:999px;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,.15);transition:.2s}
.apex-switch input:checked::after{transform:translateX(-16px)}
.apex-range{display:flex;gap:10px;align-items:center}
.apex-range input[type="range"]{flex:1;accent-color:var(--accent)}
.apex-pill{font-size:11px;font-weight:700;padding:4px 8px;border-radius:999px;border:1px solid rgba(15,23,42,.08);background:rgba(255,255,255,.6);color:var(--muted)}
.apex-divider{height:1px;background:linear-gradient(90deg,transparent,rgba(15,23,42,.08),transparent);margin:10px 0}
.apex-inline{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.apex-help{font-size:12px;color:var(--muted);background:rgba(248,250,252,.75);border:1px dashed rgba(15,23,42,.08);border-radius:12px;padding:10px 12px;line-height:1.7}
.apex-danger{border-color:rgba(220,38,38,.18)!important;background:rgba(255,255,255,.72)}
.apex-danger h3{color:#991b1b}
</style>

  <!-- Header -->
  <div class="apex-g-header">
    <div class="apex-g-title">
      <h1>تنظیمات APEX Ω</h1>
      <p>تمام پیکربندی‌ها یک‌جا — مرتبط‌ها کنار هم، بدون تکرار، با ذخیره یک مرحله‌ای. بیس و الگوی ریتم بدون تغییر باقی مانده است.</p>
    </div>
    <div class="apex-g-actions">
      <button type="button" class="apex-btn-ghost" onclick="document.getElementById('apex-settings-form').reset();">بازنشانی فرم</button>
      <button type="submit" form="apex-settings-form" class="apex-btn-primary">ذخیره تنظیمات</button>
    </div>
  </div>

  <form method="post" id="apex-settings-form">
    <?php wp_nonce_field('apex_save_settings', '_wpnonce_settings'); ?>
    <input type="hidden" name="apex_save_settings" value="1">

  <div class="apex-g-layout">
    <!-- Nav -->
    <nav class="apex-g-nav" role="tablist" aria-label="بخش‌های تنظیمات">
      <button type="button" role="tab" class="apex-g-tab" aria-selected="true" data-tab="general" id="tab-general">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.7l.1.1-.9 1.6-.1-.1a1.6 1.6 0 0 0-1.7-.3 2.5 2.5 0 0 1-2.4.1 1.6 1.6 0 0 0-1.6.4l-.1.1h-2l-.1-.1a1.6 1.6 0 0 0-1.6-.4 2.5 2.5 0 0 1-2.4-.1 1.6 1.6 0 0 0-1.7.3l-.1.1-.9-1.6.1-.1a1.6 1.6 0 0 0 .3-1.7 2.5 2.5 0 0 1 0-2.6A1.6 1.6 0 0 0 4.3 10l-.1-.1.9-1.6.1.1a1.6 1.6 0 0 0 1.7.3 2.5 2.5 0 0 1 2.4-.1 1.6 1.6 0 0 0 1.6-.4l.1-.1h2l.1.1a1.6 1.6 0 0 0 1.6.4 2.5 2.5 0 0 1 2.4.1 1.6 1.6 0 0 0 1.7-.3l.1-.1.9 1.6-.1.1a1.6 1.6 0 0 0-.3 1.7 2.5 2.5 0 0 1 0 2.6Z"/></svg>
        عمومی و ایمنی
      </button>
      <button type="button" role="tab" class="apex-g-tab" aria-selected="false" data-tab="ai" id="tab-ai">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 8V6"/><path d="M15 8l1-2"/><path d="M9 8 8 6"/><path d="M12 12a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z"/><path d="M12 16h.01"/><path d="M8.5 16A7 7 0 0 1 12 9a7 7 0 0 1 3.5 7"/></svg>
        هوش مصنوعی
      </button>
      <button type="button" role="tab" class="apex-g-tab" aria-selected="false" data-tab="artists" id="tab-artists">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8" r="3.2"/><path d="M5.2 18c.6-2.8 3-4.8 6.8-4.8s6.2 2 6.8 4.8"/><path d="M17 8.5 18.6 10 17 11.4"/></svg>
        هنرمندان و ریتم
      </button>
      <button type="button" role="tab" class="apex-g-tab" aria-selected="false" data-tab="growth" id="tab-growth">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 17 9 11l4 4 6-8"/><path d="M14 7h6v6"/></svg>
        اتوماسیون رشد
      </button>
      <button type="button" role="tab" class="apex-g-tab" aria-selected="false" data-tab="weightsync" id="tab-weightsync">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M16 3h4v4"/><path d="M4 20l7-7"/><path d="M20 4L9 15"/><path d="M8 20H4v-4"/></svg>
        همگام‌سازی
      </button>
      <button type="button" role="tab" class="apex-g-tab" aria-selected="false" data-tab="data" id="tab-data">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><ellipse cx="12" cy="5.8" rx="7" ry="3"/><path d="M5 5.8v6.5c0 1.7 3.1 3 7 3s7-1.3 7-3V5.8"/><path d="M5 12.3v4.9c0 1.7 3.1 3 7 3s7-1.3 7-3v-4.9"/></svg>
        داده و نگهداری
      </button>
    </nav>

    <!-- Panels -->
    <div class="apex-g-panels">
    <div class="apex-g-panel is-active" role="tabpanel" aria-labelledby="tab-general" data-panel="general">
      <div class="apex-g-card">
        <h3><span class="dot"></span>کنترل نوسان و سقف‌های اجرایی</h3>
        <p class="desc">این مقادیر فقط محدودیت ایمنی هستند — توزیع‌ها و زمان‌بندی‌ها را متوقف نمی‌کنند، فقط از پرش ناگهانی جلوگیری می‌کنند.</p>
        <div class="apex-g-grid">
          <div class="apex-f">
            <label for="f-threshold">آستانه تأیید کاهش بزرگ (%)</label>
            <input id="f-threshold" type="number" name="large_drop_threshold_percent" value="<?php echo esc_attr($all_settings['large_drop_threshold_percent']); ?>" min="1" max="100" step="0.5">
            <small>کاهش بیش از این درصد نیاز به تأیید صریح دارد. پیش‌فرض ۲۰٪.</small>
          </div>
          <div class="apex-f">
            <label for="f-max-views">سقف مطلق هر اجرا — بازدید (عدد)</label>
            <input id="f-max-views" type="number" name="max_default_step_views" value="<?php echo esc_attr($all_settings['max_default_step_views']); ?>" min="1" step="10">
            <small>بیشینه تغییر views در یک اجرای زمان‌بندی‌شده.</small>
          </div>
          <div class="apex-f">
            <label for="f-max-percent">سقف نسبی هر اجرا — درصد (%)</label>
            <input id="f-max-percent" type="number" name="max_default_percent" value="<?php echo esc_attr($all_settings['max_default_percent']); ?>" min="1" max="100" step="0.5">
            <small>بیشینه تغییر درصدی در هر اجرا.</small>
          </div>
          <label class="apex-switch">
            <input type="checkbox" name="enable_real_cron_hint" <?php checked($all_settings['enable_real_cron_hint']); ?>>
            <span>نمایش هشدار Cron واقعی سرور</span>
          </label>
          <label class="apex-switch" style="border-color:rgba(5,150,105,.22);background:rgba(236,253,245,.9)">
            <input type="checkbox" name="never_decrease_views" <?php checked(!empty($all_settings['never_decrease_views'])); ?>>
            <span>🛡️ هرگز خودکار کم نکن — بازدید/امتیاز فقط افزایش یا ثابت (کاهش فقط دستی مجاز)</span>
          </label>
        </div>
        <div class="apex-help" style="margin-top:12px">
          <strong>وضعیت زمان‌بندی:</strong>
          <?php if ($cron_ok): ?>
            <span class="apex-pill" style="background:#dcfce7;color:#166534;border-color:#86efac">فعال</span> اجرای بعدی: <?php echo esc_html($next_cron); ?>
          <?php else: ?>
            <span class="apex-pill" style="background:#fee2e2;color:#991b1b;border-color:#fca5a5">غیرفعال</span> — افزونه خودکار ترمیم می‌کند، در صورت تداوم، افزونه را یک‌بار غیر/فعال کنید.
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="apex-g-panel" role="tabpanel" aria-labelledby="tab-ai" data-panel="ai">
      <div class="apex-g-card">
        <h3><span class="dot"></span>اتصال هوش مصنوعی — GapGPT</h3>
        <p class="desc">اختیاری. برای پیشنهاد توزیع و انتخاب آثار منتخب استفاده می‌شود. کلید در دیتابیس ذخیره می‌شود و نمایش داده نمی‌شود.</p>
        <div class="apex-g-grid">
          <div class="apex-f" style="grid-column:1/-1">
            <label for="f-ai-key">API Key (Bearer)</label>
            <input id="f-ai-key" type="password" name="apex_ai_api_key" value="<?php echo isset($ai_set['api_key']) && $ai_set['api_key'] ? '••••••••' : ''; ?>" placeholder="sk-... از gapgpt.app">
            <small>خالی بگذارید یعنی بدون تغییر. از <a href="https://gapgpt.app/platform-v2" target="_blank">gapgpt.app</a> دریافت کنید.</small>
          </div>
          <div class="apex-f">
            <label for="f-ai-model">مدل</label>
            <input id="f-ai-model" type="text" name="apex_ai_model" value="<?php echo esc_attr($ai_set['model'] ?? 'gpt-4o-mini'); ?>" placeholder="gpt-4o-mini">
          </div>
          <div class="apex-f">
            <label for="f-ai-endpoint">Endpoint</label>
            <input id="f-ai-endpoint" type="url" name="apex_ai_endpoint" value="<?php echo esc_attr($ai_set['endpoint'] ?? 'https://api.gapgpt.app/v1/chat/completions'); ?>">
          </div>
        </div>
        <div class="apex-inline" style="margin-top:12px">
          <button type="button" class="apex-btn-ghost" id="apex-ai-test">آزمایش اتصال</button>
          <span id="apex-ai-test-res" class="apex-pill" style="display:none"></span>
        </div>
      </div>
      <div class="apex-g-card">
        <h3><span class="dot" style="background:var(--accent-2)"></span>نکته حریم خصوصی</h3>
        <p class="desc">هیچ عنوان آهنگ یا نام هنرمند بدون درخواست صریح شما به AI ارسال نمی‌شود. فقط در زمان «پیشنهاد خودکار» ارسال می‌گردد.</p>
      </div>
    </div>

    <div class="apex-g-panel" role="tabpanel" aria-labelledby="tab-artists" data-panel="artists">
      <div class="apex-g-card">
        <h3><span class="dot"></span>الگوی ریتم — نسبت هنرمند به عنوان</h3>
        <p class="desc">بیس و منطق توزیع بدون تغییر است. اینجا فقط توازن ریتم را تنظیم می‌کنید — خواننده ناشناس حتی با عنوان «بهت قول میدم» هرگز یک‌شبه ملیونی نمی‌شود.</p>
        <div class="apex-f">
          <label>سهم هنرمند: <strong id="apex-rhythm-artist-val"><?php echo (int)($rhythm['artist_ratio']*100); ?>٪</strong> — سهم عنوان: <span id="apex-rhythm-title-val"><?php echo (int)($rhythm['title_ratio']*100); ?>٪</span></label>
          <div class="apex-range">
            <input type="range" id="apex-rhythm-artist-ratio" name="rhythm_artist_ratio" min="50" max="95" step="1" value="<?php echo esc_attr((int)($rhythm['artist_ratio']*100)); ?>">
            <span class="apex-pill"><?php echo esc_html($rhythm['artist_ratio']); ?></span>
          </div>
          <small>پیشنهادی 84٪ — 85٪ هنرمند + 15٪ عنوان تعادل جهانی است.</small>
        </div>
        <div class="apex-g-grid">
          <div class="apex-f">
            <label for="f-rhythm-threshold">آستانه نوپا</label>
            <input id="f-rhythm-threshold" type="number" name="rhythm_unknown_threshold" min="15" max="50" value="<?php echo esc_attr($rhythm['unknown_threshold']); ?>">
            <small>هنرمند با وزن کمتر از این، نوپا محسوب می‌شود.</small>
          </div>
          <div class="apex-f">
            <label for="f-rhythm-cap">سقف نوپا (حتی با هیت)</label>
            <input id="f-rhythm-cap" type="number" name="rhythm_unknown_cap" min="30" max="65" value="<?php echo esc_attr($rhythm['unknown_cap']); ?>">
            <small>وزن نهایی نوپا از این بیشتر نمی‌شود.</small>
          </div>
        </div>
        <label class="apex-switch" style="margin-top:12px">
          <input type="checkbox" name="rhythm_require_pair" value="1" <?php checked($rhythm['require_pair'], true); ?>>
          <span>جفت هنرمند + عنوان باید هم‌زمان تأیید شود (عنوان تکراریِ نوپا اعتبار ندارد)</span>
        </label>
        <div class="apex-help" style="margin-top:12px">
          مثال: محسن یگانه (۹۶) + «بهت قول میدم» → <code>96×0.84 + 99×0.16 ≈ 96.5 → الماس</code> — نوپا (۱۸) + همین عنوان → <code>18×0.97 + 99×0.03 ≈ 20.4 → تازه (سقف ۴۲)</code>
        </div>
      </div>
      <div class="apex-g-card" style="border-color:rgba(99,102,241,.18);background:linear-gradient(135deg,rgba(99,102,241,.08),rgba(168,85,247,.06))">
        <h3><span class="dot" style="background:#6366f1"></span>دسته آثار منتخب — پرفکت خودکار</h3>
        <p class="desc">هر نوشته در دسته با نامک <code>selected-works</code> (<a href="https://ahangban.ir/selected-works/" target="_blank" rel="noopener">ahangban.ir/selected-works</a>) به‌صورت خودکار هیت پرفکت می‌شود — بدون نیاز به تیک دستی: بازدید تا ۱۱٫۸M، رای ۱۶-۲۴K، میانگین ۴٫۹-۵٫۰، دیدگاه ۳۵٪ بیشتر بدون انتقاد، رشد Evergreen/AutoWatch سریع‌تر. کافی‌ست نوشته را در آن دسته قرار دهید.</p>
        <div class="apex-help">تشخیص از روی <code>category</code> و تمام تاکسونومی‌های عمومی با slug <code>selected-works</code> انجام می‌شود.</div>
      </div>
      <div class="apex-g-card">
        <h3><span class="dot" style="background:#10b981"></span>مدیریت هنرمندان</h3>
        <p class="desc">وزن هر هنرمند (۰–۱۰۰) سرنوشت بازدید آثارش را تعیین می‌کند. جدول کامل و اسلایدرها در هاب اختصاصی قرار دارد.</p>
        <div class="apex-inline">
          <a class="apex-btn-primary" href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-artists')); ?>" style="text-decoration:none;display:inline-flex;align-items:center">رفتن به هنرمندان</a>
          <span class="apex-pill">وزن پیش‌فرض نوپا: ۲۰</span>
        </div>
      </div>
    </div>

    <div class="apex-g-panel" role="tabpanel" aria-labelledby="tab-growth" data-panel="growth">
      <div class="apex-g-card">
        <h3><span class="dot"></span>رشد مداوم Evergreen</h3>
        <p class="desc">پس از توزیع اولیه، همه آثار هر ۵ دقیقه رشد طبیعی می‌کنند — S-curve + ضریب دسته — تا سقف ۱۰M.</p>
        <div class="apex-g-grid">
          <label class="apex-switch">
            <input type="checkbox" name="evergreen_enabled" value="1" <?php checked(!empty($ever['enabled'])); ?>>
            <span>فعال باشد</span>
          </label>
          <div class="apex-f">
            <label for="f-ever-rate">نرخ هر ۵ دقیقه (%)</label>
            <input id="f-ever-rate" type="number" name="evergreen_rate_percent" step="0.01" min="0.005" max="0.5" value="<?php echo esc_attr($ever['rate_percent']); ?>">
            <small>پیشنهاد ۰.۰۳–۰.۰۵٪ ≈ ۵–۸٪ روزانه.</small>
          </div>
          <div class="apex-f">
            <label for="f-ever-max">سقف بازدید</label>
            <input id="f-ever-max" type="number" name="evergreen_max_views" min="50000" max="10000000" value="<?php echo esc_attr($ever['max_views']); ?>">
          </div>
        </div>
      </div>
      <div class="apex-g-card">
        <h3><span class="dot" style="background:#f59e0b"></span>نگهبان آثار تازه (AutoWatch)</h3>
        <p class="desc">پست‌های زیر آستانه را شارژ می‌کند تا صفر نماند — وزن‌محور و cooldown دار.</p>
        <div class="apex-g-grid">
          <label class="apex-switch">
            <input type="checkbox" name="autowatch_enabled" value="1" <?php checked(!empty($watch['enabled'])); ?>>
            <span>فعال</span>
          </label>
          <div class="apex-f">
            <label for="f-aw-thr">آستانه تازه (بازدید)</label>
            <input id="f-aw-thr" type="number" name="autowatch_threshold" min="1000" max="50000" step="1000" value="<?php echo esc_attr($watch['threshold']); ?>">
          </div>
          <div class="apex-f">
            <label for="f-aw-batch">پردازش هر تیک (تعداد پست)</label>
            <input id="f-aw-batch" type="number" name="autowatch_batch" min="5" max="50" value="<?php echo esc_attr($watch['batch']); ?>">
          </div>
          <div class="apex-f">
            <label for="f-aw-min">کف شارژ</label>
            <input id="f-aw-min" type="number" name="autowatch_min_views" min="100" max="5000" step="100" value="<?php echo esc_attr($watch['min_views']); ?>">
          </div>
          <div class="apex-f">
            <label for="f-aw-max">سقف شارژ</label>
            <input id="f-aw-max" type="number" name="autowatch_max_views" min="1000" max="50000" step="1000" value="<?php echo esc_attr($watch['max_views']); ?>">
          </div>
          <div class="apex-f">
            <label for="f-aw-days">فقط N روز اخیر (۰ = همه)</label>
            <input id="f-aw-days" type="number" name="autowatch_only_recent_days" min="0" max="365" value="<?php echo esc_attr($watch['only_recent_days']); ?>">
          </div>
        </div>
      </div>
      <div class="apex-g-card">
        <h3><span class="dot" style="background:#8b5cf6"></span>نگهبان دیدگاه</h3>
        <p class="desc">برای هر آهنگ دیدگاه مرتبط می‌گذارد — وزن‌محور، طبیعی، سقف‌دار.</p>
        <div class="apex-g-grid">
          <label class="apex-switch">
            <input type="checkbox" name="commentor_enabled" value="1" <?php checked(!empty($comm['enabled'])); ?>>
            <span>فعال</span>
          </label>
          <div class="apex-f">
            <label for="f-cm-thr">آستانه تازه</label>
            <input id="f-cm-thr" type="number" name="commentor_threshold" min="1000" max="50000" step="1000" value="<?php echo esc_attr($comm['threshold']); ?>">
          </div>
          <div class="apex-f">
            <label for="f-cm-maxpost">سقف هر پست</label>
            <input id="f-cm-maxpost" type="number" name="commentor_max_per_post" min="10" max="500" step="5" value="<?php echo esc_attr($comm['max_per_post']); ?>">
          </div>
          <div class="apex-f">
            <label for="f-cm-min">حداقل دیدگاه تازه</label>
            <input id="f-cm-min" type="number" name="commentor_min_comments" min="1" max="10" value="<?php echo esc_attr($comm['min_comments']); ?>">
          </div>
          <div class="apex-f">
            <label for="f-cm-max">حداکثر دیدگاه تازه</label>
            <input id="f-cm-max" type="number" name="commentor_max_comments" min="1" max="10" value="<?php echo esc_attr($comm['max_comments']); ?>">
          </div>
          <div class="apex-f">
            <label for="f-cm-daily">رشد روزانه پرطرفدار</label>
            <input id="f-cm-daily" type="number" name="commentor_daily_growth" min="1" max="5" value="<?php echo esc_attr($comm['daily_growth']); ?>">
          </div>
          <div class="apex-f">
            <label for="f-cm-batch">پردازش روزانه (۰ = همه)</label>
            <input id="f-cm-batch" type="number" name="commentor_batch" min="0" max="500" step="10" value="<?php echo esc_attr($comm['batch']); ?>">
          </div>
          <div class="apex-f">
            <label for="f-cm-days">فقط N روز اخیر (۰ = همه)</label>
            <input id="f-cm-days" type="number" name="commentor_only_recent_days" min="0" max="365" value="<?php echo esc_attr($comm['only_recent_days']); ?>">
          </div>
        </div>
        <div class="apex-help" style="margin-top:10px">
          از <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-automation')); ?>">هاب اتوماسیون</a> می‌توانید اجرای دستی و گزارش‌ها را ببینید — تنظیمات اینجا یک‌جا متمرکز شده تا تکرار نشود.
        </div>
      </div>
    </div>

    <div class="apex-g-panel" role="tabpanel" aria-labelledby="tab-weightsync" data-panel="weightsync">
      <?php $ws = \ApexOmega\Core\ArtistManager::get_weight_sync_settings(); ?>
      <div class="apex-g-card">
        <h3><span class="dot" style="background:#059669"></span>همگام‌سازی وزن‌محور — کنترل بازنویسی</h3>
        <p class="desc">اینجا تعیین می‌کنید دکمه «همگام‌سازی همه» چه کند — دیگر کل پست‌ها بی‌دلیل بازنویسی نمی‌شوند. حالت پیش‌فرض: فقط اگر از آخرین همگام‌سازی ۷۲ ساعت گذشته باشد.</p>
        <div class="apex-f">
          <label for="f-ws-mode">حالت همگام‌سازی</label>
          <select id="f-ws-mode" name="weight_sync_mode">
            <option value="all" <?php selected($ws['mode'],'all'); ?>>همه را بازنویسی کن (رفتار قدیمی)</option>
            <option value="cooldown" <?php selected($ws['mode'],'cooldown'); ?>>فقط اگر cooldown گذشته (پیشنهادی)</option>
            <option value="unsynced" <?php selected($ws['mode'],'unsynced'); ?>>فقط هنوز همگام‌نشده‌ها</option>
            <option value="lower_only" <?php selected($ws['mode'],'lower_only'); ?>>فقط اگر بازدید فعلی کمتر از کف تیره است (رشد، نه کاهش)</option>
          </select>
          <small>cooldown یعنی اگر پستی ۳ روز پیش همگام شده، دوباره نزن — از نوسان بی‌دلیل جلوگیری می‌کند.</small>
        </div>
        <div class="apex-g-grid">
          <div class="apex-f">
            <label for="f-ws-cooldown">Cooldown (ساعت)</label>
            <input id="f-ws-cooldown" type="number" name="weight_sync_cooldown_hours" min="1" max="720" value="<?php echo esc_attr($ws['cooldown_hours']); ?>">
            <small>پیش‌فرض ۷۲ساعت (۳روز). برای محافظت ۱۶۸ساعت (۷روز) بگذار.</small>
          </div>
          <div class="apex-f">
            <label for="f-ws-batch">Batch هر درخواست</label>
            <input id="f-ws-batch" type="number" name="weight_sync_batch" min="10" max="500" step="10" value="<?php echo esc_attr($ws['batch']); ?>">
            <small>پیش‌فرض ۱۰۰ — برای هاست ضعیف ۵۰.</small>
          </div>
          <div class="apex-f">
            <label for="f-ws-exclude">فقط N روز اخیر (۰=همه)</label>
            <input id="f-ws-exclude" type="number" name="weight_sync_exclude_recent_days" min="0" max="365" value="<?php echo esc_attr($ws['exclude_recent_days']); ?>">
            <small>اگر فقط آثار تازه را می‌خواهی همگام کنی، ۳۰ بگذار.</small>
          </div>
          <label class="apex-switch"><input type="checkbox" name="weight_sync_protect_selected" value="1" <?php checked($ws['protect_selected']); ?>><span>محافظت دسته منتخب (selected-works) — همیشه پرمیوم بماند</span></label>
          <label class="apex-switch"><input type="checkbox" name="weight_sync_protect_manual" value="1" <?php checked($ws['protect_manual']); ?>><span>محافظت ویرایش دستی — اگر کسی دستی عدد زده، بازنویسی نکن</span></label>
        </div>
        <div class="apex-help" style="margin-top:12px">پس از ذخیره، پیش‌نمایش همگام‌سازی تعداد واقعیِ قابل اجرا را نشان می‌دهد — اگر ۰ بود یعنی همه در cooldown هستند.</div>
      </div>
      <div class="apex-g-card">
        <h3><span class="dot" style="background:#7c3aed"></span>وضعیت آخرین همگام‌سازی</h3>
        <?php $last_ws = get_option(\ApexOmega\Core\TierManager::OPTION_LAST_DISTRIBUTION, []); if (!empty($last_ws)): ?>
          <p class="desc">آخرین اجرا: <?php echo esc_html($last_ws['at'] ?? '—'); ?> — <?php echo esc_html($last_ws['applied'] ?? 0); ?> آهنگ — <code><?php echo esc_html(substr($last_ws['exec'] ?? '',0,8)); ?></code> <?php if(!empty($last_ws['weight_sync'])) echo '<span class="apex-pill" style="background:#ecfdf5;color:#065f46">وزن‌محور</span>'; ?></p>
          <div class="apex-inline"><a class="apex-btn-ghost" href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-weight-sync')); ?>">رفتن به همگام‌سازی</a><span class="apex-pill">Cooldown فعلی: <?php echo esc_html($ws['cooldown_hours']); ?>ساعت — حالت: <?php echo esc_html($ws['mode']); ?></span></div>
        <?php else: ?><p class="desc">هنوز همگام‌سازی انجام نشده.</p><?php endif; ?>
      </div>
    </div>

    <div class="apex-g-panel" role="tabpanel" aria-labelledby="tab-data" data-panel="data">
      <div class="apex-g-card">
        <h3><span class="dot" style="background:#06b6d4"></span>برون‌بری و درون‌ریزی</h3>
        <p class="desc">پشتیبان و انتقال تنظیمات بین سایت‌ها.</p>
        <div class="apex-inline">
          <button type="button" class="apex-btn-ghost" data-export="artist">CSV هنرمندان</button>
          <button type="button" class="apex-btn-ghost" data-export="tiers">JSON دسته‌ها</button>
          <button type="button" class="apex-btn-ghost" data-export="history">CSV تاریخچه</button>
        </div>
        <div class="apex-divider"></div>
        <div class="apex-f">
          <label for="f-import">درون‌ریزی (CSV/JSON را اینجا بچسبانید)</label>
          <textarea id="f-import" rows="4" placeholder="محتوای CSV یا JSON..." style="width:100%;padding:10px 12px;border-radius:12px;border:1px solid rgba(15,23,42,.08);background:rgba(255,255,255,.72);font-family:ui-monospace,monospace;font-size:12px"></textarea>
          <div class="apex-inline" style="margin-top:8px">
            <select id="f-import-type" style="padding:8px 10px;border-radius:10px;border:1px solid rgba(15,23,42,.08);background:#fff">
              <option value="artist">هنرمندان (CSV)</option>
              <option value="tiers">دسته‌ها (JSON)</option>
            </select>
            <button type="button" class="apex-btn-ghost" id="apex-import-btn">درون‌ریزی</button>
            <span id="apex-import-res" class="apex-pill" style="display:none"></span>
          </div>
        </div>
      </div>
      <div class="apex-g-card apex-danger">
        <h3><span class="dot" style="background:#dc2626"></span>ناحیه خطر</h3>
        <p class="desc">این گزینه فقط هنگام حذف افزونه از بخش افزونه‌ها اثر می‌گذارد — نه با غیرفعال‌سازی.</p>
        <label class="apex-switch">
          <input type="checkbox" name="remove_data_on_uninstall" value="1" <?php checked($all_settings['remove_data_on_uninstall']); ?>>
          <span>هنگام حذف افزونه، جداول APEX و متاهای <code>_apex_*</code> پاک شود</span>
        </label>
        <small style="color:#991b1b;display:block;margin-top:8px;line-height:1.6">آمار اصلی (views و kksr) هرگز خودکار حذف نمی‌شود.</small>
      </div>
    </div>
    </div><!-- panels -->
  </div><!-- layout -->
  </form>
</div>

<script>
(function(){
  document.documentElement.classList.add('js-enabled');
  document.documentElement.classList.remove('no-js');
  const root = document.querySelector('.apex-settings-root');
  if(!root) return;
  const tabs = root.querySelectorAll('.apex-g-tab');
  const panels = root.querySelectorAll('.apex-g-panel');
  const key = 'apex_settings_tab';
  function activate(id, save){
    tabs.forEach(t=>{ const on=t.dataset.tab===id; t.setAttribute('aria-selected', on?'true':'false'); t.tabIndex = on?0:-1; });
    panels.forEach(p=> p.classList.toggle('is-active', p.dataset.panel===id));
    if(save) try{ localStorage.setItem(key, id); }catch(e){}
    // update url hash without scroll
    if(history.replaceState) history.replaceState(null,'','#'+id);
  }
  tabs.forEach(t=>{
    t.addEventListener('click', ()=> activate(t.dataset.tab, true));
    t.addEventListener('keydown', e=>{
      if(e.key==='ArrowDown' || e.key==='ArrowRight'){ e.preventDefault(); const n=[...tabs]; const i=n.indexOf(t); n[(i+1)%n.length].focus(); n[(i+1)%n.length].click(); }
      if(e.key==='ArrowUp' || e.key==='ArrowLeft'){ e.preventDefault(); const n=[...tabs]; const i=n.indexOf(t); n[(i-1+n.length)%n.length].focus(); n[(i-1+n.length)%n.length].click(); }
    });
  });
  // restore
  let start = 'general';
  try{ const s=localStorage.getItem(key); if(s) start=s; }catch(e){}
  if(location.hash) start = location.hash.replace('#','');
  if(![...tabs].some(t=>t.dataset.tab===start)) start='general';
  activate(start, false);

  // rhythm slider live
  const r = document.getElementById('apex-rhythm-artist-ratio');
  const av = document.getElementById('apex-rhythm-artist-val');
  const tv = document.getElementById('apex-rhythm-title-val');
  if(r){
    r.addEventListener('input', ()=>{
      const v = parseInt(r.value||84);
      if(av) av.textContent = v+'٪';
      if(tv) tv.textContent = (100-v)+'٪';
    });
  }

  // AI test
  const aiBtn = document.getElementById('apex-ai-test');
  if(aiBtn){
    aiBtn.addEventListener('click', ()=>{
      const nonce = (window.ApexOmega && ApexOmega.nonce) || document.querySelector('input[name=_wpnonce_settings]')?.value || '';
      const ajax = (window.ApexOmega && ApexOmega.ajax_url) || ajaxurl;
      const fd = new FormData(); fd.append('action','apex_test_ai'); fd.append('_ajax_nonce', nonce);
      aiBtn.disabled=true; const old=aiBtn.textContent; aiBtn.textContent='در حال آزمایش…';
      fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(j=>{
        const el=document.getElementById('apex-ai-test-res'); if(!el) return;
        el.style.display='inline-flex'; el.textContent = j.success ? 'اتصال موفق' : (j.data?.message||'خطا');
        el.style.background=j.success?'#dcfce7':'#fee2e2'; el.style.color=j.success?'#166534':'#991b1b'; el.style.borderColor=j.success?'#86efac':'#fca5a5';
      }).catch(e=>{
        const el=document.getElementById('apex-ai-test-res'); if(el){el.style.display='inline-flex'; el.textContent='خطا: '+e; el.style.background='#fee2e2';}
      }).finally(()=>{ aiBtn.disabled=false; aiBtn.textContent=old; });
    });
  }

  // export/import
  function getAjax(){ return (window.ApexOmega && window.ApexOmega.ajax_url) || ajaxurl; }
  function getNonce(){ return (window.ApexOmega && window.ApexOmega.nonce) || document.querySelector('input[name=_wpnonce_settings]')?.value || ''; }
  root.querySelectorAll('[data-export]').forEach(b=>{
    b.addEventListener('click', ()=>{
      const type=b.getAttribute('data-export');
      const map={artist:'apex_export_artist_csv', tiers:'apex_export_tiers_json', history:'apex_export_history_csv'};
      const action=map[type]; if(!action) return;
      b.disabled=true; const old=b.textContent; b.textContent='...';
      const fd=new FormData(); fd.append('action',action); fd.append('_ajax_nonce', getNonce());
      fetch(getAjax(),{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(j=>{
        if(!j.success) throw new Error(j.data?.message||'خطا');
        const data = j.data.csv || j.data.json || '';
        const blob = new Blob([data], {type:'text/plain;charset=utf-8'});
        const url = URL.createObjectURL(blob);
        const a=document.createElement('a'); a.href=url; a.download=j.data.filename||('apex-'+type+'.txt'); document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
      }).catch(e=> alert('خطا: '+e.message)).finally(()=>{b.disabled=false; b.textContent=old;});
    });
  });
  const impBtn=document.getElementById('apex-import-btn');
  if(impBtn){
    impBtn.addEventListener('click', ()=>{
      const type=document.getElementById('f-import-type')?.value||'artist';
      const content=document.getElementById('f-import')?.value||'';
      if(!content.trim()){ alert('محتوا خالی است'); return; }
      const action = type==='tiers' ? 'apex_import_tiers_json' : 'apex_import_artist_csv';
      const key = type==='tiers' ? 'json' : 'csv';
      const fd=new FormData(); fd.append('action',action); fd.append('_ajax_nonce', getNonce()); fd.append(key, content);
      impBtn.disabled=true; const old=impBtn.textContent; impBtn.textContent='...';
      fetch(getAjax(),{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(j=>{
        const el=document.getElementById('apex-import-res'); if(el){ el.style.display='inline-flex'; el.textContent=j.success ? 'انجام شد: '+(j.data.imported||j.data.count||'') : (j.data?.message||'خطا'); el.style.background=j.success?'#dcfce7':'#fee2e2'; }
        if(j.success) setTimeout(()=>location.reload(),900);
      }).catch(e=> alert('خطا: '+e.message)).finally(()=>{impBtn.disabled=false; impBtn.textContent=old;});
    });
  }

})();
</script>
