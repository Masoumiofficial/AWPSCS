<?php if (!defined('ABSPATH')) exit;
$artist_tax = \ApexOmega\Core\ArtistManager::detect_artist_taxonomy();
$artists = $artist_tax ? \ApexOmega\Core\ArtistManager::get_all_artists($artist_tax, 80) : [];
$available_taxes = \ApexOmega\Core\ArtistManager::get_available_taxonomies();
$saved_tax = get_option(\ApexOmega\Core\ArtistManager::OPTION_TAXONOMY, '');
$weights_opt = get_option(\ApexOmega\Core\ArtistManager::OPTION_WEIGHTS, []);
$total_artists = count($artists);
$weighted = 0; foreach($artists as $a) if((int)$a['weight'] !== 50) $weighted++;
$pct_artists = $total_artists ? round($weighted / $total_artists * 100) : 0;
$rhythm = \ApexOmega\Core\ArtistManager::get_rhythm_settings();
$tiers = \ApexOmega\Core\TierManager::get_tiers();
$total_posts = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'");
?>
<div class="wrap apex-glass-wrap">
  <div class="apex-hero" style="background: linear-gradient(135deg,#7c3aed 0%,#f59e0b 100%); color:#fff; border:none;">
    <div>
      <h1 style="margin:0;font-size:26px;color:#fff;">🎤 هنرمندان و الگوی ریتم — پنل حرفه‌ای</h1>
      <p style="margin:6px 0 0;opacity:0.9;">وزن هر هنرمند = سرنوشت بازدیدش. اینجا 100% حرفه‌ای و تفکیک‌شده مدیریت می‌شود.</p>
    </div>
    <div class="apex-hero-stats" style="color:#fff;">
      <div style="background:rgba(255,255,255,0.15);"><span class="big" style="color:#fff;"><?php echo esc_html($total_artists); ?></span><small style="color:#fff;">هنرمند شناسایی‌شده</small></div>
      <div style="background:rgba(255,255,255,0.15);"><span class="big" style="color:#fff;"><?php echo esc_html($weighted); ?> / <?php echo esc_html($total_artists); ?></span><small style="color:#fff;">وزن‌گذاری شده (<?php echo $pct_artists; ?>٪)</small></div>
      <div style="background:rgba(255,255,255,0.15);"><span class="big" style="color:#fff;"><?php echo esc_html(number_format_i18n($total_posts)); ?></span><small style="color:#fff;">آهنگ کل</small></div>
    </div>
  </div>

  <div style="display:flex;gap:8px;margin:14px 0;flex-wrap:wrap">
    <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-artists')); ?>" style="padding:8px 14px;border-radius:999px;text-decoration:none;font-size:13px;border:1px solid #7c3aed;background:#7c3aed;color:#fff">🎤 هنرمندان</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-automation')); ?>" style="padding:8px 14px;border-radius:999px;text-decoration:none;font-size:13px;border:1px solid #d1d5db;background:#fff;color:#334155">⚙️ اتوماسیون</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-weight-sync')); ?>" style="padding:8px 14px;border-radius:999px;text-decoration:none;font-size:13px;border:1px solid #d1d5db;background:#fff;color:#334155">⚡ همگام‌سازی</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-hits')); ?>" style="padding:8px 14px;border-radius:999px;text-decoration:none;font-size:13px;border:1px solid #d1d5db;background:#fff;color:#334155">⭐ آثار منتخب</a>
  </div>

  <div class="apex-card" style="margin-top:14px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
      <h3 style="margin:0;">📊 پیشرفت وزن‌گذاری</h3>
      <span class="apex-badge" style="background:<?php echo $pct_artists>=80 ? '#059669' : ($pct_artists>=40 ? '#f59e0b' : '#ef4444'); ?>;color:#fff;"><?php echo $pct_artists; ?>٪ تکمیل</span>
    </div>
    <div style="background:#e5e7eb;border-radius:999px;height:14px;margin-top:10px;overflow:hidden;">
      <div style="height:100%;width:<?php echo $pct_artists; ?>%;background:linear-gradient(90deg,#7c3aed,#f59e0b);transition:width 0.6s;"></div>
    </div>
    <small style="color:#6b7280;"><?php echo $weighted; ?> هنرمند وزن سفارشی دارد، <?php echo $total_artists-$weighted; ?> هنوز پیش‌فرض 50 (نیاز به بررسی). برای نتیجه 100٪، همه ابرستاره‌ها را 90+ کن.</small>
  </div>

  <div class="apex-card" style="border-top:4px solid #f59e0b;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
      <h3 style="margin:0;">🎤 توزیع بر اساس محبوبیت آرتیست</h3>
      <label style="background:#fef3c7;border:1px solid #f59e0b;border-radius:999px;padding:6px 14px;font-weight:800;cursor:pointer;">
        <input type="checkbox" id="apex-artist-aware" checked> فعال: آرتیست‌های پرطرفدار → دسته الماس
      </label>
    </div>
    <p style="color:#6b7280;margin:8px 0 0;">تاکسونومی شناسایی‌شده: <code><?php echo $artist_tax ? esc_html($artist_tax) : 'یافت نشد'; ?></code> — وزن 0–100: 90+=الماس، 75+=پرطرفدار، ... 0–15=تازه</p>
    <div style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap; align-items:center; background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:10px;">
      <label style="font-weight:800;">تاکسونومی آرتیست:</label>
      <select id="apex-artist-taxonomy" style="min-width:220px;">
        <option value="__auto__" <?php selected($saved_tax,''); ?>>🔍 خودکار (تشخیص هوشمند)</option>
        <?php foreach ($available_taxes as $tax => $info): ?>
          <option value="<?php echo esc_attr($tax); ?>" <?php selected($saved_tax,$tax); ?>><?php echo esc_html($info['label'] . ' (' . $tax . ') — ' . $info['count'] . ' ترم' . ($info['public']?'':' — غیرعمومی')); ?></option>
        <?php endforeach; ?>
      </select>
      <button type="button" id="apex-taxonomy-save" class="button button-apex secondary">ذخیره تاکسونومی</button>
      <span id="apex-taxonomy-result" style="color:#065f46;font-weight:700;"></span>
    </div>
    <?php if ($artist_tax && !empty($artists)): ?>
      <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
        <button type="button" id="apex-artist-ai" class="button button-apex secondary">🤖 امتیازدهی خودکار با AI (GapGPT)</button>
        <button type="button" id="apex-artist-ir" class="button button-apex secondary">🌐 امتیاز از سایت ایرانی</button>
        <button type="button" id="apex-artist-save" class="button button-apex">💾 ذخیره وزن‌ها</button>
        <span style="margin-right:auto;color:#6b7280;font-size:12px;">وزن 0–100 — با قفل هوشمند (TTL=تعداد×۲ثانیه)</span>
      </div>
      <div style="max-height:420px;overflow:auto;margin-top:10px;border:1px solid #e5e7eb;border-radius:10px;">
        <table class="apex-table" style="margin:0;">
          <thead><tr><th>آرتیست</th><th>تعداد</th><th>وزن</th><th>دسته</th><th>اسلایدر</th><th>تنوع</th></tr></thead>
          <tbody>
            <?php foreach ($artists as $a): ?>
              <tr data-tid="<?php echo esc_attr($a['term_id']); ?>">
                <td><strong><?php echo esc_html($a['name']); ?></strong> <small>(<?php echo esc_html($a['slug']); ?>)</small></td>
                <td><?php echo esc_html($a['count']); ?> <small style="color:#6b7280;">(<?php echo $total_posts? round($a['count']/$total_posts*100,1):0; ?>٪ کل)</small></td>
                <td><input type="number" class="apex-artist-weight" data-tid="<?php echo esc_attr($a['term_id']); ?>" value="<?php echo esc_attr($a['weight']); ?>" min="0" max="100" style="width:80px;text-align:center;font-weight:800;"></td>
                <td><span class="apex-badge" style="background:<?php echo esc_attr($tiers[array_search($a['tier_hint'], array_column($tiers,'id'))]['color'] ?? '#6b7280'); ?>;color:#fff;"><?php echo esc_html($tiers[array_search($a['tier_hint'], array_column($tiers,'id'))]['label'] ?? $a['tier_hint']); ?></span></td>
                <td><input type="range" class="apex-artist-slider" data-tid="<?php echo esc_attr($a['term_id']); ?>" min="0" max="100" value="<?php echo esc_attr($a['weight']); ?>" style="width:120px;accent-color:#f59e0b;"></td>
                <td><button type="button" class="button button-apex secondary apex-reseed-btn" data-tid="<?php echo esc_attr($a['term_id']); ?>" style="padding:2px 8px;font-size:11px;white-space:nowrap">🔀 تنوع‌دهی</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div id="apex-artist-result" style="margin-top:8px;"></div>
    <?php elseif ($artist_tax): ?>
      <div class="apex-alert warning">تاکسونومی <?php echo esc_html($artist_tax); ?> خالی است.</div>
    <?php else: ?>
      <div class="apex-alert warning">تاکسونومی آرتیست یافت نشد. از انتخابگر بالا دستی انتخاب کن یا این بخش را نادیده بگیر.</div>
    <?php endif; ?>
  </div>

  <div class="apex-card" style="margin-top:14px; border-top:4px solid #7c3aed; background:#f5f3ff;">
    <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:10px;">
      <div>
        <h3 style="margin:0;">🎛️ الگوی ریتم حرفه‌ای — اولویت با اسم خواننده</h3>
        <small style="color:#6b7280;">خواننده نوپا حتی با عنوان "بهت قول میدم" هرگز یک‌شبه ملیونی نمی‌شود.</small>
      </div>
      <span class="apex-badge" style="background:#7c3aed;color:#fff;">قابل کنترل کامل</span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:12px;">
      <label>نسبت وزن خواننده (%):<br><input type="range" id="apex-rhythm-artist-ratio" min="50" max="95" step="5" value="<?php echo esc_attr((int)($rhythm['artist_ratio']*100)); ?>" style="width:100%;"> <strong id="apex-rhythm-artist-val"><?php echo (int)($rhythm['artist_ratio']*100); ?>%</strong> <small style="color:#6b7280;">عنوان: <span id="apex-rhythm-title-val"><?php echo (int)($rhythm['title_ratio']*100); ?>%</span></small></label>
      <label>آستانه نوپا:<br><input type="number" id="apex-rhythm-threshold" min="15" max="50" value="<?php echo esc_attr($rhythm['unknown_threshold']); ?>" style="width:100%;"> <small style="color:#6b7280;">خواننده با وزن کمتر از این = نوپا</small></label>
      <label>سقف نوپا (حتی با هیت):<br><input type="number" id="apex-rhythm-cap" min="30" max="65" value="<?php echo esc_attr($rhythm['unknown_cap']); ?>" style="width:100%;"> <small style="color:#6b7280;">وزن نهایی نوپا از این بیشتر نمی‌شود</small></label>
      <label style="display:flex;align-items:center;gap:8px;background:#fff;border:1px solid #ddd;border-radius:10px;padding:10px;align-self:end;">
        <input type="checkbox" id="apex-rhythm-pair" <?php checked($rhythm['require_pair'], true); ?>> <span><strong>جفت خواننده+عنوان الزامی</strong><br><small>عنوان تکراری نوپا اعتبار ندارد</small></span>
      </label>
    </div>
    <div style="margin-top:10px; background:#fff; border:1px dashed #7c3aed; border-radius:10px; padding:10px; font-size:13px; color:#4c1d95;">
      <strong>مثال:</strong> محسن یگانه (96) + "بهت قول میدم" = <code>96×0.85 + 99×0.15 = 96.5 → الماس</code> | نوپا (18) + همین عنوان = <code>18×0.97 + 99×0.03 = 20.4 → تازه (سقف 45)</code>
    </div>
    <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
      <button type="button" id="apex-rhythm-save" class="button button-apex" style="background:#7c3aed;border-color:#7c3aed;">💾 ذخیره الگوی ریتم</button>
      <span id="apex-rhythm-result" style="font-weight:700;"></span>
    </div>
  </div>
</div>
<script>
(function(){
  function getNonce(){ return (window.ApexOmega&&ApexOmega.nonce) || document.querySelector('input[name=\"_wpnonce\"]')?.value || document.querySelector('#_wpnonce')?.value || ''; }
  function getAjax(){ return (window.ApexOmega&&ApexOmega.ajax_url) || '/wp-admin/admin-ajax.php'; }
  function apexPost(action, extra){
    var fd=new FormData(); fd.append('action',action); fd.append('_ajax_nonce',getNonce());
    for(var k in extra){ if(extra.hasOwnProperty(k)) fd.append(k, extra[k]); }
    return fetch(getAjax(),{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){ return r.text().then(function(txt){ try{ return JSON.parse(txt); }catch(e){ var clean=txt.replace(/<[^>]*>/g,' ').trim().slice(0,300); throw new Error('پاسخ غیرمنتظره: '+clean); } }); });
  }
  function qs(id){ return document.getElementById(id); }
  function bindOnce(id, handler){ var el=qs(id); if(!el || el.dataset.vanilla) return; el.dataset.vanilla='1'; el.addEventListener('click', function(e){ e.preventDefault(); e.stopImmediatePropagation(); handler(el,e); }); }
  function run(){
    bindOnce('apex-taxonomy-save', function(btn){
      btn.disabled=true; var o=btn.textContent; btn.textContent='ذخیره…';
      var tax=qs('apex-artist-taxonomy')?.value||''; var resEl=qs('apex-taxonomy-result');
      apexPost('apex_save_artist_taxonomy',{taxonomy:tax}).then(function(j){
        if(j.success){ if(resEl) resEl.textContent='✅ '+j.data.message+' — '+j.data.taxonomy; resEl.style.color='#065f46'; setTimeout(()=>location.reload(),700); }
        else if(resEl){resEl.textContent='❌ '+j.data.message; resEl.style.color='#991b1b';}
      }).catch(e=>{ if(resEl) resEl.textContent='❌ '+e;}).finally(()=>{ btn.disabled=false; btn.textContent=o;});
    });
    bindOnce('apex-artist-save', function(btn){
      var weights={}; document.querySelectorAll('.apex-artist-weight').forEach(function(el){ weights[el.dataset.tid]=el.value; });
      btn.textContent='ذخیره…'; var resEl=qs('apex-artist-result');
      var fd=new FormData(); fd.append('action','apex_save_artist_weights'); fd.append('_ajax_nonce',getNonce());
      for(var tid in weights) fd.append('weights['+tid+']', weights[tid]);
      fetch(getAjax(),{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(function(j){
        if(j.success){ if(resEl) resEl.innerHTML='<div class=\"apex-alert success\">ذخیره شد ('+j.data.count+' آرتیست)</div>';}
        else if(resEl) resEl.innerHTML='<div class=\"apex-alert error\">'+(j.data.message||'خطا')+'</div>';
      }).catch(e=>{ if(resEl) resEl.innerHTML='<div class=\"apex-alert error\">❌ '+e+'</div>';}).finally(()=>btn.textContent='💾 ذخیره وزن‌ها');
    });
    bindOnce('apex-artist-ai', function(btn){
      btn.disabled=true; var o=btn.textContent; btn.textContent='AI در حال امتیازدهی…'; var resEl=qs('apex-artist-result');
      apexPost('apex_auto_artist_ai',{}).then(function(j){
        if(j.success){ Object.keys(j.data.weights||{}).forEach(function(tid){ var w=j.data.weights[tid]; var inp=document.querySelector('.apex-artist-weight[data-tid=\"'+tid+'\"]'); if(inp) inp.value=w; var sl=document.querySelector('.apex-artist-slider[data-tid=\"'+tid+'\"]'); if(sl) sl.value=w; }); if(resEl) resEl.innerHTML='<div class=\"apex-alert success\">✅ AI امتیاز داد: '+(Object.keys(j.data.weights||{}).length)+' آرتیست</div>';}
        else if(resEl) resEl.innerHTML='<div class=\"apex-alert error\">'+(j.data.message||'خطا')+'</div>';
      }).catch(e=>{ if(resEl) resEl.innerHTML='<div class=\"apex-alert error\">❌ '+e+'</div>';}).finally(()=>{btn.disabled=false; btn.textContent=o;});
    });
    bindOnce('apex-artist-ir', function(btn){
      btn.disabled=true; var o=btn.textContent; btn.textContent='در حال خواندن از سایت ایرانی…'; var resEl=qs('apex-artist-result');
      apexPost('apex_auto_artist_ir',{}).then(function(j){
        if(j.success){ Object.keys(j.data.weights||{}).forEach(function(tid){ var w=j.data.weights[tid]; var inp=document.querySelector('.apex-artist-weight[data-tid=\"'+tid+'\"]'); if(inp) inp.value=w; var sl=document.querySelector('.apex-artist-slider[data-tid=\"'+tid+'\"]'); if(sl) sl.value=w; }); if(resEl) resEl.innerHTML='<div class=\"apex-alert success\">🌐 از سایت ایرانی امتیاز گرفته شد: '+(Object.keys(j.data.weights||{}).length)+'</div>';}
        else if(resEl) resEl.innerHTML='<div class=\"apex-alert error\">'+(j.data.message||'خطا')+'</div>';
      }).catch(e=>{ if(resEl) resEl.innerHTML='<div class=\"apex-alert error\">❌ '+e+'</div>';}).finally(()=>{btn.disabled=false; btn.textContent=o;});
    });
    bindOnce('apex-rhythm-save', function(btn){
      btn.disabled=true; var o=btn.textContent; btn.textContent='ذخیره…';
      var ar=(parseInt(qs('apex-rhythm-artist-ratio')?.value)||85)/100, th=parseInt(qs('apex-rhythm-threshold')?.value)||30, cap=parseInt(qs('apex-rhythm-cap')?.value)||45, pair=qs('apex-rhythm-pair')?.checked?1:0;
      var resEl=qs('apex-rhythm-result'); if(resEl) resEl.textContent='در حال ذخیره…';
      apexPost('apex_save_rhythm',{artist_ratio:ar,unknown_threshold:th,unknown_cap:cap,require_pair:pair}).then(function(j){
        if(j.success){ if(resEl) resEl.textContent='✅ '+j.data.message; resEl.style.color='#065f46';}
        else if(resEl){resEl.textContent='❌ '+(j.data.message||'خطا'); resEl.style.color='#991b1b';}
      }).catch(function(err){ if(resEl) resEl.textContent='❌ '+err; }).finally(function(){ btn.disabled=false; btn.textContent='💾 ذخیره الگوی ریتم';});
    });
    var ratio=qs('apex-rhythm-artist-ratio'); if(ratio && !ratio.dataset.vanilla){ ratio.dataset.vanilla='1'; ratio.addEventListener('input', function(){ var v=parseInt(this.value||85); var a=qs('apex-rhythm-artist-val'); if(a) a.textContent=v+'%'; var t=qs('apex-rhythm-title-val'); if(t) t.textContent=(100-v)+'%';});}
    document.querySelectorAll('.apex-reseed-btn').forEach(function(btn){ btn.addEventListener('click', function(){ var tid=this.dataset.tid; if(!confirm('تنوع‌دهی مجدد برای این هنرمند؟ همه آهنگ‌هایش متنوع می‌شود (هرگز کم نمی‌کند)')) return; var orig=this.textContent; this.disabled=true; this.textContent='در حال تنوع…'; apexPost('apex_reseed_artist',{term_id:tid,taxonomy:qs('apex-artist-taxonomy')?.value||''}).then(function(j){ if(j.success){ alert('✅ '+j.data.message+' — '+j.data.applied+' آهنگ'); } else alert('❌ '+(j.data.message||'خطا')); }).catch(function(e){ alert('❌ '+e.message); }).finally(function(btn2){ return function(){ btn2.disabled=false; btn2.textContent=orig; }; }(this)); }); });
    document.querySelectorAll('.apex-artist-slider').forEach(function(sl){ sl.addEventListener('input', function(){ var tid=this.dataset.tid; var inp=document.querySelector('.apex-artist-weight[data-tid=\"'+tid+'\"]'); if(inp) inp.value=this.value; }); });
    document.querySelectorAll('.apex-artist-weight').forEach(function(inp){ inp.addEventListener('input', function(){ var tid=this.dataset.tid; var sl=document.querySelector('.apex-artist-slider[data-tid=\"'+tid+'\"]'); if(sl) sl.value=this.value; }); });
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',run); else run(); setTimeout(run,600);
})();
</script>
