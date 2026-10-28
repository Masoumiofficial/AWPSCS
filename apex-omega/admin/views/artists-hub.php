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
                <td><button type="button" class="button button-apex secondary apex-reseed-btn" data-tid="<?php echo esc_attr($a['term_id']); ?>" data-name="<?php echo esc_attr($a['name']); ?>" data-count="<?php echo esc_attr($a['count']); ?>" style="padding:2px 8px;font-size:11px;white-space:nowrap">🔀 تنوع‌دهی</button></td>
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

<!-- 🎛️ پاپ‌آپ تنوع‌دهی با بازه قابل تنظیم — glass iPhone -->
<div id="apex-reseed-modal" style="display:none;position:fixed;inset:0;z-index:99999;align-items:center;justify-content:center;padding:16px;">
  <div id="apex-reseed-backdrop" style="position:absolute;inset:0;background:rgba(15,23,42,0.45);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);"></div>
  <div style="position:relative;width:100%;max-width:560px;max-height:90vh;overflow:auto;background:rgba(255,255,255,0.96);backdrop-filter:blur(22px) saturate(180%);-webkit-backdrop-filter:blur(22px) saturate(180%);border:1px solid rgba(255,255,255,0.85);border-radius:22px;box-shadow:0 24px 64px rgba(15,23,42,0.18), 0 1px 0 rgba(255,255,255,0.9) inset;padding:0;animation:apexFade 0.2s ease;">
    <div style="padding:18px 20px 14px;border-bottom:1px solid rgba(15,23,42,0.06);display:flex;justify-content:space-between;align-items:center;gap:12px;position:sticky;top:0;background:rgba(255,255,255,0.96);border-radius:22px 22px 0 0;z-index:1;">
      <div>
        <h3 style="margin:0;font-size:16px;font-weight:900;color:#0f172a;display:flex;gap:8px;align-items:center;">🔀 تنوع‌دهی هنرمند <span style="background:linear-gradient(135deg,#7c3aed,#f59e0b);-webkit-background-clip:text;-webkit-text-fill-color:transparent;" id="apex-reseed-artist-name"></span></h3>
        <small id="apex-reseed-artist-meta" style="color:#64748b;font-size:11px;"></small>
      </div>
      <button type="button" id="apex-reseed-close" style="width:32px;height:32px;border-radius:999px;border:1px solid rgba(15,23,42,0.08);background:rgba(255,255,255,0.8);cursor:pointer;font-size:16px;line-height:1;">✕</button>
    </div>
    <div style="padding:16px 20px;">
      <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:14px;padding:10px 12px;font-size:12px;color:#92400e;line-height:1.7;">
        <strong>هرگز کم نمی‌شود</strong> — بازه انتخابی فقط برای <strong>افزایش متنوع</strong> استفاده می‌شود. اگر آهنگی الان ۶۰۰k باشد و بازه ۱۰۰k-۳۰۰k انتخاب کنید، به‌جای کاهش، تا <strong>۳-۲۲٪ افزایش</strong> می‌گیرد. تنوع واقعی با هش ID هر آهنگ تضمین می‌شود.
      </div>
      <div style="margin-top:14px;">
        <label style="font-size:12px;font-weight:800;color:#1e293b;display:block;margin-bottom:6px;">بازه دلخواه بازدید (هر آهنگ عددی متفاوت در همین بازه می‌گیرد):</label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <label class="apex-f" style="margin:0;"><span style="font-size:11px;font-weight:700;color:#475569;">کف بازه (حداقل)</span><input type="number" id="apex-reseed-min" min="1000" max="12000000" step="1000" placeholder="100000" style="width:100%;padding:10px 12px;border-radius:12px;border:1px solid rgba(15,23,42,0.08);background:rgba(255,255,255,0.9);font-weight:700;"></label>
          <label class="apex-f" style="margin:0;"><span style="font-size:11px;font-weight:700;color:#475569;">سقف بازه (حداکثر)</span><input type="number" id="apex-reseed-max" min="1000" max="12000000" step="1000" placeholder="300000" style="width:100%;padding:10px 12px;border-radius:12px;border:1px solid rgba(15,23,42,0.08);background:rgba(255,255,255,0.9);font-weight:700;"></label>
        </div>
        <small style="color:#64748b;font-size:11px;display:block;margin-top:6px;">مثال: ۱۰۰,۰۰۰ تا ۳۰۰,۰۰۰ → هر آهنگ عددی یکتا بین این دو می‌گیرد + هرگز کم نمی‌شود.</small>
      </div>
      <div style="margin-top:12px;">
        <span style="font-size:11px;font-weight:800;color:#334155;">پیشنهادهای سریع:</span>
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;">
          <button type="button" class="apex-range-preset" data-min="10000" data-max="30000" style="padding:6px 10px;border-radius:999px;border:1px solid rgba(15,23,42,0.08);background:rgba(255,255,255,0.85);font-size:11px;font-weight:700;cursor:pointer;">10k – 30k</button>
          <button type="button" class="apex-range-preset" data-min="30000" data-max="80000" style="padding:6px 10px;border-radius:999px;border:1px solid rgba(15,23,42,0.08);background:rgba(255,255,255,0.85);font-size:11px;font-weight:700;cursor:pointer;">30k – 80k</button>
          <button type="button" class="apex-range-preset" data-min="80000" data-max="200000" style="padding:6px 10px;border-radius:999px;border:1px solid rgba(15,23,42,0.08);background:rgba(255,255,255,0.85);font-size:11px;font-weight:700;cursor:pointer;">80k – 200k</button>
          <button type="button" class="apex-range-preset" data-min="100000" data-max="300000" style="padding:6px 10px;border-radius:999px;border:1px solid rgba(15,23,42,0.08);background:rgba(255,255,255,0.85);font-size:11px;font-weight:700;cursor:pointer;">100k – 300k ⭐</button>
          <button type="button" class="apex-range-preset" data-min="200000" data-max="500000" style="padding:6px 10px;border-radius:999px;border:1px solid rgba(15,23,42,0.08);background:rgba(255,255,255,0.85);font-size:11px;font-weight:700;cursor:pointer;">200k – 500k</button>
          <button type="button" class="apex-range-preset" data-min="500000" data-max="900000" style="padding:6px 10px;border-radius:999px;border:1px solid rgba(15,23,42,0.08);background:rgba(255,255,255,0.85);font-size:11px;font-weight:700;cursor:pointer;">500k – 900k</button>
          <button type="button" class="apex-range-preset" data-min="900000" data-max="2000000" style="padding:6px 10px;border-radius:999px;border:1px solid rgba(15,23,42,0.08);background:rgba(255,255,255,0.85);font-size:11px;font-weight:700;cursor:pointer;">0.9M – 2M</button>
          <button type="button" class="apex-range-preset" data-min="1000000" data-max="5000000" style="padding:6px 10px;border-radius:999px;border:1px solid rgba(15,23,42,0.08);background:rgba(255,255,255,0.85);font-size:11px;font-weight:700;cursor:pointer;">1M – 5M</button>
          <button type="button" class="apex-range-preset" data-min="8000000" data-max="12000000" style="padding:6px 10px;border-radius:999px;border:1px solid #f59e0b;background:#fffbeb;font-size:11px;font-weight:800;cursor:pointer;">💎 8M – 12M پرمیوم</button>
          <button type="button" class="apex-range-preset" data-min="" data-max="" data-auto="1" style="padding:6px 10px;border-radius:999px;border:1px dashed #7c3aed;background:#f5f3ff;font-size:11px;font-weight:800;cursor:pointer;">🤖 خودکار هوشمند</button>
        </div>
      </div>
      <div id="apex-reseed-preview" style="margin-top:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:10px 12px;font-size:11px;color:#475569;line-height:1.7;display:none;"></div>
    </div>
    <div style="padding:12px 20px 18px;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;border-top:1px solid rgba(15,23,42,0.06);background:rgba(248,250,252,0.7);border-radius:0 0 22px 22px;">
      <button type="button" id="apex-reseed-cancel" style="padding:10px 16px;border-radius:12px;border:1px solid rgba(15,23,42,0.08);background:rgba(255,255,255,0.9);font-weight:700;cursor:pointer;">لغو</button>
      <button type="button" id="apex-reseed-apply" style="padding:10px 18px;border-radius:12px;border:1px solid rgba(124,58,237,0.2);background:linear-gradient(180deg,#7c3aed,#6d28d9);color:#fff;font-weight:800;cursor:pointer;box-shadow:0 8px 18px rgba(124,58,237,0.22);">✅ اعمال تنوع</button>
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
    // ——— تنوع‌دهی پاپ‌آپ با بازه قابل تنظیم ———
    var _reseedTid=null,_reseedBtn=null;
    function formatNum(n){ if(!n) return '-'; return Number(n).toLocaleString('fa-IR'); }
    function openReseedModal(tid,name,count){
      _reseedTid=tid;
      var modal=qs('apex-reseed-modal'); if(!modal) return;
      qs('apex-reseed-artist-name').textContent=name||('ID '+tid);
      qs('apex-reseed-artist-meta').textContent=(count? count+' آهنگ • ':'')+'بازه مورد نظر را انتخاب کن (هرگز کم نمی‌شود)';
      var minEl=qs('apex-reseed-min'), maxEl=qs('apex-reseed-max');
      if(!minEl.value) minEl.value='100000';
      if(!maxEl.value) maxEl.value='300000';
      updateReseedPreview();
      modal.style.display='flex';
      document.body.style.overflow='hidden';
    }
    function closeReseedModal(){
      var modal=qs('apex-reseed-modal'); if(modal) modal.style.display='none';
      document.body.style.overflow='';
      _reseedTid=null; _reseedBtn=null;
    }
    function updateReseedPreview(){
      var minEl=qs('apex-reseed-min'), maxEl=qs('apex-reseed-max'), box=qs('apex-reseed-preview');
      if(!minEl||!maxEl||!box) return;
      var mn=parseInt(minEl.value||0), mx=parseInt(maxEl.value||0);
      if(!mn && !mx){ box.style.display='none'; return; }
      if(mn && mx && mn>mx){ box.innerHTML='<span style="color:#dc2626;">⚠️ کف بازه از سقف بیشتر است — جابجا می‌شود</span>'; box.style.display='block'; return; }
      var range = mn && mx ? formatNum(mn)+' تا '+formatNum(mx) : (mn? 'از '+formatNum(mn): 'تا '+formatNum(mx));
      box.innerHTML='📊 پیش‌نمایش: هر آهنگ عددی یکتا در بازه <strong>'+range+'</strong> می‌گیرد. تفاوت 100k-300k حدود <strong>200,000</strong> تنوع ایجاد می‌کند (نه 100). همه افزایشی.';
      box.style.display='block';
    }
    document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeReseedModal(); });
    document.querySelectorAll('.apex-range-preset').forEach(function(b){
      b.addEventListener('click', function(){
        var mn=this.getAttribute('data-min'), mx=this.getAttribute('data-max'), auto=this.getAttribute('data-auto');
        var minEl=qs('apex-reseed-min'), maxEl=qs('apex-reseed-max');
        if(auto){ minEl.value=''; maxEl.value=''; }
        else { minEl.value=mn; maxEl.value=mx; }
        updateReseedPreview();
        document.querySelectorAll('.apex-range-preset').forEach(function(x){ x.style.outline=''; x.style.boxShadow=''; });
        this.style.outline='2px solid #7c3aed'; this.style.boxShadow='0 0 0 4px rgba(124,58,237,0.12)';
      });
    });
    var _minEl=document.getElementById('apex-reseed-min'), _maxEl=document.getElementById('apex-reseed-max');
    if(_minEl) _minEl.addEventListener('input', updateReseedPreview);
    if(_maxEl) _maxEl.addEventListener('input', updateReseedPreview);
    var _back=qs('apex-reseed-backdrop'), _close=qs('apex-reseed-close'), _cancel=qs('apex-reseed-cancel');
    if(_back) _back.addEventListener('click', closeReseedModal);
    if(_close) _close.addEventListener('click', closeReseedModal);
    if(_cancel) _cancel.addEventListener('click', closeReseedModal);
    var _apply=qs('apex-reseed-apply');
    if(_apply) _apply.addEventListener('click', function(){
      if(!_reseedTid) return;
      var mn=parseInt(qs('apex-reseed-min').value||0), mx=parseInt(qs('apex-reseed-max').value||0);
      if(mn && mx && mn>mx){ alert('کف بازه نباید از سقف بیشتر باشد'); return; }
      var payload={term_id:_reseedTid, taxonomy: qs('apex-artist-taxonomy')?.value||''};
      if(mn) payload.range_min=mn;
      if(mx) payload.range_max=mx;
      var origBtn=document.querySelector('.apex-reseed-btn[data-tid="'+_reseedTid+'"]');
      if(origBtn){ origBtn.disabled=true; var o=origBtn.textContent; origBtn.textContent='در حال تنوع…'; }
      _apply.disabled=true; _apply.textContent='در حال اعمال…';
      apexPost('apex_reseed_artist', payload).then(function(j){
        if(j.success){ alert('✅ '+j.data.message+' — '+j.data.applied+' آهنگ'); closeReseedModal(); }
        else alert('❌ '+(j.data.message||'خطا'));
      }).catch(function(e){ alert('❌ '+e.message); }).finally(function(){
        if(origBtn){ origBtn.disabled=false; origBtn.textContent=o; }
        _apply.disabled=false; _apply.textContent='✅ اعمال تنوع';
      });
    });

    document.querySelectorAll('.apex-reseed-btn').forEach(function(btn){ if(btn.dataset.vanilla) return; btn.dataset.vanilla='1'; btn.addEventListener('click', function(e){ e.preventDefault(); e.stopImmediatePropagation(); openReseedModal(this.dataset.tid, this.dataset.name, this.dataset.count); }); });

    document.querySelectorAll('.apex-artist-slider').forEach(function(sl){ if(sl.dataset.vanilla) return; sl.dataset.vanilla='1'; sl.addEventListener('input', function(){ var tid=this.dataset.tid; var inp=document.querySelector('.apex-artist-weight[data-tid=\"'+tid+'\"]'); if(inp) inp.value=this.value; }); });
    document.querySelectorAll('.apex-artist-weight').forEach(function(inp){ if(inp.dataset.vanilla) return; inp.dataset.vanilla='1'; inp.addEventListener('input', function(){ var tid=this.dataset.tid; var sl=document.querySelector('.apex-artist-slider[data-tid=\"'+tid+'\"]'); if(sl) sl.value=this.value; }); });
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',run); else run(); setTimeout(run,600);
})();
</script>
