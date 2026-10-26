<?php if (!defined('ABSPATH')) exit;
// Gather current values — only core + AI + Data (بدون تکرار هاب‌ها)
$all_settings = wp_parse_args(get_option('apex_omega_settings', []), [
    'large_drop_threshold_percent' => 20,
    'max_default_step_views'       => 500,
    'max_default_percent'          => 15,
    'enable_real_cron_hint'        => true,
    'remove_data_on_uninstall'     => false,
    'never_decrease_views'         => true,
]);
$ai_set   = \ApexOmega\Core\AI_Helper::get_settings();
$cron_ok  = (bool) wp_next_scheduled(\ApexOmega\Core\Scheduler::CRON_HOOK);
$next_cron = $cron_ok ? get_date_from_gmt(gmdate('Y-m-d H:i:s', wp_next_scheduled(\ApexOmega\Core\Scheduler::CRON_HOOK)), 'Y-m-d H:i:s') : '—';
?>
<div class="wrap apex-glass-wrap" dir="rtl">
  <div class="apex-glass-header">
    <div>
      <h1><span class="apex-logo">Ω</span> تنظیمات APEX</h1>
      <p>تمام پیکربندیِ هسته یک‌جا — بدون تکرار هاب‌ها. هر قابلیت صفحه‌ی خودش را دارد؛ اینجا فقط «عمومی / هوش مصنوعی / داده».</p>
    </div>
    <div class="apex-glass-actions">
      <span class="apex-version-pill">نسخه <?php echo esc_html(APEX_OMEGA_VERSION); ?></span>
      <button type="button" class="apex-btn-ghost" onclick="document.getElementById('apex-settings-form').reset();">بازنشانی</button>
      <button type="submit" form="apex-settings-form" class="apex-btn-primary">ذخیره تنظیمات</button>
    </div>
  </div>

  <form method="post" id="apex-settings-form">
    <?php wp_nonce_field('apex_save_settings', '_wpnonce_settings'); ?>
    <input type="hidden" name="apex_save_settings" value="1">

  <div class="apex-glass-layout">
    <nav class="apex-glass-nav" role="tablist" aria-label="بخش‌های تنظیمات">
      <button type="button" role="tab" class="apex-g-tab" aria-selected="true" data-tab="general" id="tab-general">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.7l.1.1-.9 1.6-.1-.1a1.6 1.6 0 0 0-1.7-.3 2.5 2.5 0 0 1-2.4.1 1.6 1.6 0 0 0-1.6.4l-.1.1h-2l-.1-.1a1.6 1.6 0 0 0-1.6-.4 2.5 2.5 0 0 1-2.4-.1 1.6 1.6 0 0 0-1.7.3l-.1.1-.9-1.6.1-.1a1.6 1.6 0 0 0 .3-1.7 2.5 2.5 0 0 1 0-2.6A1.6 1.6 0 0 0 4.3 10l-.1-.1.9-1.6.1.1a1.6 1.6 0 0 0 1.7.3 2.5 2.5 0 0 1 2.4-.1 1.6 1.6 0 0 0 1.6-.4l.1-.1h2l.1.1a1.6 1.6 0 0 0 1.6.4 2.5 2.5 0 0 1 2.4.1 1.6 1.6 0 0 0 1.7-.3l.1-.1.9 1.6-.1.1a1.6 1.6 0 0 0-.3 1.7 2.5 2.5 0 0 1 0 2.6Z"/></svg>
        عمومی و ایمنی
      </button>
      <button type="button" role="tab" class="apex-g-tab" aria-selected="false" data-tab="ai" id="tab-ai">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 8V6"/><path d="M15 8l1-2"/><path d="M9 8 8 6"/><path d="M12 12a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z"/><path d="M12 16h.01"/><path d="M8.5 16A7 7 0 0 1 12 9a7 7 0 0 1 3.5 7"/></svg>
        هوش مصنوعی
        <small>GapGPT</small>
      </button>
      <button type="button" role="tab" class="apex-g-tab" aria-selected="false" data-tab="data" id="tab-data">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><ellipse cx="12" cy="5.8" rx="7" ry="3"/><path d="M5 5.8v6.5c0 1.7 3 3 7 3s7-1.3 7-3V5.8"/><path d="M5 12v4.5c0 1.7 3 3 7 3s7-1.3 7-3V12"/><path d="M12 13l4-2"/><path d="M12 13V9"/></svg>
        داده و پشتیبان
      </button>
    </nav>

    <div class="apex-glass-main">
    <div class="apex-g-panel is-active" role="tabpanel" aria-labelledby="tab-general" data-panel="general">
      <div class="apex-glass-card">
        <h3><span class="dot"></span>کنترل نوسان و سقف‌های اجرایی</h3>
        <p class="desc">محدودیت ایمنی — از پرش ناگهانی جلوگیری می‌کند، نه از توزیع. بیس و ریتم دست‌نخورده می‌ماند.</p>
        <div class="apex-g-grid">
          <div class="apex-f">
            <label for="f-threshold">آستانهٔ تأیید کاهش بزرگ (%)</label>
            <input id="f-threshold" type="number" name="large_drop_threshold_percent" value="<?php echo esc_attr($all_settings['large_drop_threshold_percent']); ?>" min="1" max="100" step="0.5">
            <small>کاهش بیش از این درصد نیاز به تأیید صریح دارد — پیش‌فرض ۲۰٪.</small>
          </div>
          <div class="apex-f">
            <label for="f-max-views">سقف هر اجرا — بازدید</label>
            <input id="f-max-views" type="number" name="max_default_step_views" value="<?php echo esc_attr($all_settings['max_default_step_views']); ?>" min="1" step="10">
            <small>بیشینه تغییر views در یک اجرا.</small>
          </div>
          <div class="apex-f">
            <label for="f-max-percent">سقف هر اجرا — درصد</label>
            <input id="f-max-percent" type="number" name="max_default_percent" value="<?php echo esc_attr($all_settings['max_default_percent']); ?>" min="1" max="100" step="0.5">
            <small>بیشینه تغییر درصدی در هر اجرا.</small>
          </div>
          <div class="apex-f">
            <label for="f-uninstall">&nbsp;</label>
            <label class="apex-switch"><input type="checkbox" name="enable_real_cron_hint" <?php checked($all_settings['enable_real_cron_hint']); ?>><span>نمایش هشدار Cron واقعی</span></label>
          </div>
          <div class="apex-f" style="grid-column:1/-1">
            <label class="apex-switch" style="border-color:rgba(5,150,105,.18);background:rgba(236,253,245,.92)"><input type="checkbox" name="never_decrease_views" <?php checked(!empty($all_settings['never_decrease_views'])); ?>><span>🛡️ هرگز خودکار کم نکن — فقط افزایش یا ثابت (کاهش فقط دستی)</span></label>
            <small>اگر خاموش باشد، Evergreen/AutoWeight می‌تواند کم کند — پیشنهاد: روشن بماند.</small>
          </div>
        </div>
        <div class="apex-help" style="margin-top:12px">
          <strong>زمان‌بندی:</strong>
          <?php if ($cron_ok): ?><span class="apex-pill" style="background:#dcfce7;color:#166534;border-color:#86efac">فعال</span> بعدی: <?php echo esc_html($next_cron); ?>
          <?php else: ?><span class="apex-pill" style="background:#fee2e2;color:#991b1b;border-color:#fca5a5">غیرفعال</span> — خودکار ترمیم می‌شود
          <?php endif; ?>
          <span style="margin-right:8px" class="apex-help">بیس توزیع و الگوی ریتم تغییری نکرده — فقط ایمنی.</span>
        </div>
      </div>
      <div class="apex-glass-card" style="background:rgba(255,255,255,.52)">
        <h3><span class="dot" style="background:#0ea5e9"></span>میانبر هاب‌ها — بدون تکرار</h3>
        <p class="desc">تنظیمات تخصصی هر بخش در هاب خودش است؛ اینجا تکرار نمی‌شود.</p>
        <div class="apex-inline">
          <a class="apex-btn-ghost" href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-artists')); ?>">هنرمندان و ریتم</a>
          <a class="apex-btn-ghost" href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-automation')); ?>">اتوماسیون</a>
          <a class="apex-btn-ghost" href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-weight-sync')); ?>">همگام‌سازی</a>
          <a class="apex-btn-ghost" href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-hits')); ?>">آثار منتخب</a>
        </div>
      </div>
    </div>

    <div class="apex-g-panel" role="tabpanel" aria-labelledby="tab-ai" data-panel="ai">
      <div class="apex-glass-card">
        <h3><span class="dot"></span>اتصال GapGPT — اختیاری</h3>
        <p class="desc">فقط برای «پیشنهاد توزیع» و «انتخاب هیت». کلید رمزنگاری شده ذخیره می‌شود و نمایش داده نمی‌شود.</p>
        <div class="apex-g-grid">
          <div class="apex-f" style="grid-column:1/-1">
            <label for="f-ai-key">API Key (Bearer)</label>
            <input id="f-ai-key" type="password" name="apex_ai_api_key" value="<?php echo isset($ai_set['api_key']) && $ai_set['api_key'] ? '••••••••' : ''; ?>" placeholder="sk-... از gapgpt.app">
            <small>خالی = بدون تغییر. از <a href="https://gapgpt.app/platform-v2" target="_blank">gapgpt.app</a> بگیرید.</small>
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
      <div class="apex-glass-card" style="background:rgba(248,250,252,.72)">
        <h3><span class="dot" style="background:var(--accent-2)"></span>حریم خصوصی</h3>
        <p class="desc">عنوان/نام هنرمند فقط هنگام «پیشنهاد خودکار» ارسال می‌شود — نه خودکار و نه ذخیره.</p>
      </div>
    </div>

    <div class="apex-g-panel" role="tabpanel" aria-labelledby="tab-data" data-panel="data">
      <div class="apex-glass-card">
        <h3><span class="dot" style="background:#06b6d4"></span>برون‌بری و درون‌ریزی</h3>
        <p class="desc">پشتیبان سریع برای جابه‌جایی بین سایت‌ها — بدون افزونه جانبی.</p>
        <div class="apex-inline">
          <button type="button" class="apex-btn-ghost" data-export="artist">CSV هنرمندان</button>
          <button type="button" class="apex-btn-ghost" data-export="tiers">JSON دسته‌ها</button>
          <button type="button" class="apex-btn-ghost" data-export="history">CSV تاریخچه</button>
        </div>
        <div class="apex-divider"></div>
        <div class="apex-f">
          <label for="f-import">درون‌ریزی (CSV/JSON را بچسبانید)</label>
          <textarea id="f-import" rows="4" placeholder="محتوای CSV یا JSON..." style="width:100%;padding:11px 12px;border-radius:12px;border:1px solid rgba(15,23,42,.08);background:rgba(255,255,255,.82);font-family:ui-monospace,monospace;font-size:12px"></textarea>
          <div class="apex-inline" style="margin-top:8px">
            <select id="f-import-type" style="padding:9px 11px;border-radius:11px;border:1px solid rgba(15,23,42,.08);background:#fff"><option value="artist">هنرمندان (CSV)</option><option value="tiers">دسته‌ها (JSON)</option></select>
            <button type="button" class="apex-btn-ghost" id="apex-import-btn">درون‌ریزی</button>
            <span id="apex-import-res" class="apex-pill" style="display:none"></span>
          </div>
        </div>
      </div>
      <div class="apex-glass-card apex-danger">
        <h3><span class="dot" style="background:#dc2626"></span>ناحیهٔ خطر</h3>
        <p class="desc">فقط هنگام <strong>حذف</strong> افزونه از «افزونه‌ها» اثر دارد — غیرفعال‌سازی پاک نمی‌کند.</p>
        <label class="apex-switch"><input type="checkbox" name="remove_data_on_uninstall" value="1" <?php checked($all_settings['remove_data_on_uninstall']); ?>><span>هنگام حذف، جداول APEX و متاهای <code>_apex_*</code> پاک شود</span></label>
        <small style="color:#991b1b;display:block;margin-top:8px">آمار اصلی views/kksr هرگز خودکار حذف نمی‌شود.</small>
      </div>
    </div>
    </div><!-- main -->
  </div><!-- layout -->
  </form>
</div>

<script>
(function(){
  document.documentElement.classList.add('js-enabled');
  const root = document.querySelector('.apex-glass-wrap');
  if(!root) return;
  const tabs = root.querySelectorAll('.apex-g-tab');
  const panels = root.querySelectorAll('.apex-g-panel');
  const key = 'apex_settings_tab_v3';
  function activate(id, save){
    tabs.forEach(t=>{ const on=t.dataset.tab===id; t.setAttribute('aria-selected', on?'true':'false'); t.tabIndex = on?0:-1; });
    panels.forEach(p=> p.classList.toggle('is-active', p.dataset.panel===id));
    if(save) try{ localStorage.setItem(key, id); }catch(e){}
    if(history.replaceState) history.replaceState(null,'','#'+id);
  }
  tabs.forEach(t=>{
    t.addEventListener('click', ()=> activate(t.dataset.tab, true));
    t.addEventListener('keydown', e=>{
      if(e.key==='ArrowDown' || e.key==='ArrowRight'){ e.preventDefault(); const n=[...tabs]; const i=n.indexOf(t); n[(i+1)%n.length].focus(); n[(i+1)%n.length].click(); }
      if(e.key==='ArrowUp' || e.key==='ArrowLeft'){ e.preventDefault(); const n=[...tabs]; const i=n.indexOf(t); n[(i-1+n.length)%n.length].focus(); n[(i-1+n.length)%n.length].click(); }
    });
  });
  let start = 'general';
  try{ const s=localStorage.getItem(key); if(s) start=s; }catch(e){}
  if(location.hash) start = location.hash.replace('#','');
  if(![...tabs].some(t=>t.dataset.tab===start)) start='general';
  activate(start, false);

  const aiBtn = document.getElementById('apex-ai-test');
  if(aiBtn){
    aiBtn.addEventListener('click', ()=>{
      const nonce = (window.ApexOmega && ApexOmega.nonce) || document.querySelector('input[name=_wpnonce_settings]')?.value || '';
      const ajax = (window.ApexOmega && window.ApexOmega.ajax_url) || (typeof ajaxurl!=='undefined'?ajaxurl:'/wp-admin/admin-ajax.php');
      const fd = new FormData(); fd.append('action','apex_test_ai'); fd.append('_ajax_nonce', nonce);
      aiBtn.disabled=true; const old=aiBtn.textContent; aiBtn.textContent='در حال آزمایش…';
      fetch(ajax,{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(j=>{
        const el=document.getElementById('apex-ai-test-res'); if(!el) return;
        el.style.display='inline-flex'; el.textContent = j.success ? 'اتصال موفق' : (j.data?.message||'خطا');
        el.style.background=j.success?'#dcfce7':'#fee2e2'; el.style.color=j.success?'#166534':'#991b1b';
      }).catch(e=>{
        const el=document.getElementById('apex-ai-test-res'); if(el){el.style.display='inline-flex'; el.textContent='خطا: '+e; el.style.background='#fee2e2';}
      }).finally(()=>{ aiBtn.disabled=false; aiBtn.textContent=old; });
    });
  }
  function getAjax(){ return (window.ApexOmega && window.ApexOmega.ajax_url) || (typeof ajaxurl!=='undefined'?ajaxurl:'/wp-admin/admin-ajax.php'); }
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
