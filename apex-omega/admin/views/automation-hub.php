<?php if (!defined('ABSPATH')) exit;
$total_posts = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'");
$evergreen = \ApexOmega\Core\Evergreen::get_settings();
$autowatch = \ApexOmega\Core\AutoWatch::get_settings();
$commentor = \ApexOmega\Core\CommentGuardian::get_settings();
$ever_enabled = !empty($evergreen['enabled']);
$aw_enabled = !empty($autowatch['enabled']);
$cm_enabled = !empty($commentor['enabled']);
$overall = ($ever_enabled?33:0)+($aw_enabled?33:0)+($cm_enabled?34:0);
$autowatch_last = get_option('apex_omega_autowatch_last_run', []);
$commentor_last = get_option('apex_omega_commentor_last_run', []);
$rhythm = \ApexOmega\Core\ArtistManager::get_rhythm_settings();
?>
<div class="wrap apex-wrap">
  <div class="apex-hero" style="background: linear-gradient(135deg,#0e7490 0%,#059669 50%,#9333ea 100%); color:#fff; border:none;">
    <div>
      <h1 style="margin:0;font-size:26px;color:#fff;">⚙️ اتوماسیون حرفه‌ای — رشد خودکار و اصولی</h1>
      <p style="margin:6px 0 0;opacity:0.9;">۳ موتور مستقل: Evergreen (رشد روزانه) + نگهبان تازه‌ها + نگهبان کامنت — هر کدام تفکیک‌شده، قابل کنترل، با درصد پیشرفت.</p>
    </div>
    <div class="apex-hero-stats" style="color:#fff;">
      <div style="background:rgba(255,255,255,0.15);"><span class="big" style="color:#fff;"><?php echo $overall; ?>٪</span><small style="color:#fff;">اتوماسیون فعال</small></div>
      <div style="background:rgba(255,255,255,0.15);"><span class="big" style="color:#fff;"><?php echo ($ever_enabled?1:0)+($aw_enabled?1:0)+($cm_enabled?1:0); ?> / 3</span><small style="color:#fff;">موتور روشن</small></div>
      <div style="background:rgba(255,255,255,0.15);"><span class="big" style="color:#fff;"><?php echo esc_html(number_format_i18n($total_posts)); ?></span><small style="color:#fff;">آهنگ تحت پوشش</small></div>
    </div>
  </div>

  <div class="apex-card" style="margin-top:14px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
      <h3 style="margin:0;">📈 وضعیت کلی اتوماسیون</h3>
      <span class="apex-badge" style="background:<?php echo $overall>=80?'#059669':($overall>=40?'#f59e0b':'#ef4444'); ?>;color:#fff;"><?php echo $overall; ?>٪ فعال</span>
    </div>
    <div style="background:#e5e7eb;border-radius:999px;height:14px;margin-top:10px;overflow:hidden;">
      <div style="height:100%;width:<?php echo $overall; ?>%;background:linear-gradient(90deg,#0e7490,#059669);transition:width 0.6s;"></div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:10px;text-align:center;font-size:12px;">
      <div style="background:<?php echo $ever_enabled?'#ecfeff':'#fef2f2'; ?>;border:1px solid <?php echo $ever_enabled?'#06b6d4':'#fca5a5'; ?>;border-radius:8px;padding:8px;">🌱 Evergreen<br><strong><?php echo $ever_enabled?'✅ فعال':'⭕ خاموش'; ?></strong> — <?php echo $evergreen['rate_percent']; ?>٪/۵دقیقه</div>
      <div style="background:<?php echo $aw_enabled?'#fff7ed':'#fef2f2'; ?>;border:1px solid <?php echo $aw_enabled?'#f97316':'#fca5a5'; ?>;border-radius:8px;padding:8px;">👀 نگهبان تازه‌ها<br><strong><?php echo $aw_enabled?'✅ فعال':'⭕ خاموش'; ?></strong> — آستانه <?php echo number_format_i18n($autowatch['threshold']); ?></div>
      <div style="background:<?php echo $cm_enabled?'#faf5ff':'#fef2f2'; ?>;border:1px solid <?php echo $cm_enabled?'#9333ea':'#fca5a5'; ?>;border-radius:8px;padding:8px;">💬 نگهبان کامنت<br><strong><?php echo $cm_enabled?'✅ فعال':'⭕ خاموش'; ?></strong> — سقف <?php echo $commentor['max_per_post']; ?>/پست</div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:14px;margin-top:14px;">
    <!-- Evergreen -->
    <div class="apex-card" style="border-top:4px solid #14b8a6; background:#f0fdfa;">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
        <h3 style="margin:0;color:#0f766e;">🌱 رشد مداوم Evergreen</h3>
        <label style="display:flex;align-items:center;gap:6px;background:#fff;border:1px solid #14b8a6;border-radius:999px;padding:4px 12px;font-weight:800;cursor:pointer;">
          <input type="checkbox" id="apex-evergreen-enabled" <?php checked($ever_enabled,true); ?>> فعال
        </label>
      </div>
      <small style="color:#134e4a;">بعد توزیع اولیه، همه آهنگ‌ها هر ۵ دقیقه طبیعی رشد می‌کنند تا سقف 10M — S-curve + ضریب تیره.</small>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px;">
        <label>نرخ رشد هر ۵ دقیقه (%):<br><input type="number" id="apex-evergreen-rate" step="0.01" min="0.005" max="0.5" value="<?php echo esc_attr($evergreen['rate_percent']); ?>" style="width:100%;"></label>
        <label>سقف بازدید:<br><input type="number" id="apex-evergreen-max" min="50000" max="10000000" value="<?php echo esc_attr($evergreen['max_views']); ?>" style="width:100%;"></label>
      </div>
      <div style="margin-top:10px;display:flex;gap:8px;align-items:center;">
        <button id="apex-evergreen-save" type="button" class="button button-apex" style="background:#0f766e;border-color:#0f766e;">💾 ذخیره Evergreen</button>
        <span id="apex-evergreen-result" style="font-weight:700;"></span>
      </div>
      <div style="background:#fff;border-radius:8px;padding:8px;margin-top:10px;font-size:12px;color:#6b7280;">
        پیشنهاد: 0.03–0.05% = 5–8% روزانه. الماس 30٪ سریع‌تر، تازه 40٪ کندتر. <code>apex_omega_tick</code> هر ۵ دقیقه.
      </div>
    </div>

    <!-- AutoWatch -->
    <div class="apex-card" style="border-top:4px solid #f97316; background:#fff7ed;">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
        <h3 style="margin:0;color:#9a3412;">👀 نگهبان پست‌های تازه</h3>
        <label style="display:flex;align-items:center;gap:6px;background:#fff;border:1px solid #f97316;border-radius:999px;padding:4px 12px;font-weight:800;cursor:pointer;">
          <input type="checkbox" id="apex-autowatch-enabled" <?php checked($aw_enabled,true); ?>> فعال
        </label>
      </div>
      <small style="color:#7c2d12;">پست‌های زیر 10K (تازه اسکریپتی) را شارژ می‌کند تا ۰ نماند — وزن‌محور + cooldown.</small>
      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-top:12px;">
        <label>آستانه تازه:<br><input type="number" id="apex-autowatch-threshold" min="1000" max="50000" step="1000" value="<?php echo esc_attr($autowatch['threshold']); ?>" style="width:100%;"></label>
        <label>هر تیک چند پست:<br><input type="number" id="apex-autowatch-batch" min="5" max="50" step="1" value="<?php echo esc_attr($autowatch['batch']); ?>" style="width:100%;"></label>
        <label>حداقل شارژ:<br><input type="number" id="apex-autowatch-min" min="100" max="5000" step="100" value="<?php echo esc_attr($autowatch['min_views']); ?>" style="width:100%;"></label>
        <label>حداکثر شارژ:<br><input type="number" id="apex-autowatch-max" min="1000" max="50000" step="1000" value="<?php echo esc_attr($autowatch['max_views']); ?>" style="width:100%;"></label>
        <label>فقط چند روز اخیر:<br><input type="number" id="apex-autowatch-recent" min="0" max="365" step="1" value="<?php echo esc_attr($autowatch['only_recent_days']); ?>" style="width:100%;"> <small>0=همه</small></label>
        <button id="apex-autowatch-save" type="button" class="button button-apex" style="background:#ea580c;border-color:#ea580c;align-self:end;">💾 ذخیره نگهبان</button>
      </div>
      <div style="margin-top:8px;font-size:12px;color:#6b7280;">
        <?php if (!empty($autowatch_last['at'])): ?><span style="color:#065f46;">آخرین: <?php echo esc_html($autowatch_last['at']); ?> — <?php echo esc_html($autowatch_last['done']); ?> پست</span><?php endif; ?>
        <span id="apex-autowatch-result" style="font-weight:700;"></span>
      </div>
    </div>

    <!-- Commentor -->
    <div class="apex-card" style="border-top:4px solid #9333ea; background:#faf5ff;">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
        <h3 style="margin:0;color:#6b21a8;">💬 نگهبان کامنت هوشمند</h3>
        <label style="display:flex;align-items:center;gap:6px;background:#fff;border:1px solid #9333ea;border-radius:999px;padding:4px 12px;font-weight:800;cursor:pointer;">
          <input type="checkbox" id="apex-commentor-enabled" <?php checked($cm_enabled,true); ?>> فعال
        </label>
      </div>
      <small style="color:#581c87;">برای هر آهنگ کامنت فارسی مرتبط با نام ایرانی انگلیسی می‌گذارد — وزن‌محور، طبیعی.</small>
      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-top:12px;">
        <label>آستانه تازه:<br><input type="number" id="apex-commentor-threshold" min="1000" max="50000" step="1000" value="<?php echo esc_attr($commentor['threshold']); ?>" style="width:100%;"></label>
        <label>سقف هر پست:<br><input type="number" id="apex-commentor-maxpost" min="10" max="100" step="5" value="<?php echo esc_attr($commentor['max_per_post']); ?>" style="width:100%;"></label>
        <label>حداقل تازه:<br><input type="number" id="apex-commentor-min" min="1" max="5" step="1" value="<?php echo esc_attr($commentor['min_comments']); ?>" style="width:100%;"></label>
        <label>حداکثر تازه:<br><input type="number" id="apex-commentor-max" min="1" max="5" step="1" value="<?php echo esc_attr($commentor['max_comments']); ?>" style="width:100%;"></label>
        <label>رشد روزانه پرطرفدار:<br><input type="number" id="apex-commentor-daily" min="1" max="5" step="1" value="<?php echo esc_attr($commentor['daily_growth']); ?>" style="width:100%;"></label>
        <label>پردازش روزانه:<br><input type="number" id="apex-commentor-batch" min="0" max="500" step="10" value="<?php echo esc_attr($commentor['batch']); ?>" style="width:100%;"> <small>0=همه</small></label>
        <label>فقط چند روز اخیر:<br><input type="number" id="apex-commentor-recent" min="0" max="365" step="1" value="<?php echo esc_attr($commentor['only_recent_days']); ?>" style="width:100%;"> <small>0=همه</small></label>
        <button type="button" id="apex-commentor-save" class="button button-apex" style="background:#7c3aed;">💬 ذخیره</button>
      </div>
      <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;">
        <button type="button" id="apex-commentor-run" class="button button-apex secondary" style="background:#fff;color:#7c3aed;border-color:#7c3aed;">▶️ اجرای فوری (تست)</button>
        <span id="apex-commentor-result" style="font-weight:700;"></span>
        <?php if (!empty($commentor_last['at'])): ?><small style="color:#065f46;">آخرین: <?php echo esc_html($commentor_last['at']); ?> → <?php echo esc_html($commentor_last['posts']); ?> پست → <?php echo esc_html($commentor_last['comments']); ?> کامنت</small><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="apex-card" style="margin-top:14px; border-top:4px solid #0e7490; background:#ecfeff;">
    <h3 style="margin:0;color:#0e7490;">🧠 چطور اصولی نگه داریم؟</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;margin-top:10px;">
      <div style="background:#fff;border:1px solid #a5f3fc;border-radius:10px;padding:12px;"><strong>۱) وزن‌ها واقعی</strong><br><small>معین 95، ناشناس 20 — بعد وزن عوض شد یک‌کلیک همگام‌سازی بزن.</small></div>
      <div style="background:#fff;border:1px solid #99f6e4;border-radius:10px;padding:12px;"><strong>۲) Evergreen روشن</strong><br><small>0.03–0.04% روزانه 6–8% — تا سقف تیره می‌ماند.</small></div>
      <div style="background:#fff;border:1px solid #fed7aa;border-radius:10px;padding:12px;"><strong>۳) نگهبان‌ها روشن</strong><br><small>تازه‌ها 0 نمی‌مانند، میلیونی‌ها دست نمی‌خورند.</small></div>
    </div>
  </div>
</div>
<script>
(function(){
  function getNonce(){ return (window.ApexOmega&&ApexOmega.nonce) || document.querySelector('input[name=\"_wpnonce\"]')?.value || ''; }
  function getAjax(){ return (window.ApexOmega&&ApexOmega.ajax_url) || '/wp-admin/admin-ajax.php'; }
  function apexPost(action, extra){ var fd=new FormData(); fd.append('action',action); fd.append('_ajax_nonce',getNonce()); for(var k in extra) fd.append(k, extra[k]); return fetch(getAjax(),{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.text().then(t=>{ try{return JSON.parse(t);}catch(e){ throw new Error(t.slice(0,300).replace(/<[^>]*>/g,''));}})); }
  function qs(id){return document.getElementById(id);}
  function bindOnce(id, h){ var el=qs(id); if(!el||el.dataset.vanilla) return; el.dataset.vanilla='1'; el.addEventListener('click', e=>{e.preventDefault(); e.stopImmediatePropagation(); h(el,e);});}
  function run(){
    bindOnce('apex-evergreen-save', function(btn){
      btn.disabled=true; var o=btn.textContent; btn.textContent='ذخیره…';
      var enabled=qs('apex-evergreen-enabled')?.checked?1:0, rate=parseFloat(qs('apex-evergreen-rate')?.value)||0.04, max=parseInt(qs('apex-evergreen-max')?.value)||10000000;
      var resEl=qs('apex-evergreen-result'); if(resEl) resEl.textContent='در حال ذخیره…';
      apexPost('apex_save_evergreen',{enabled:enabled,rate_percent:rate,max_views:max}).then(j=>{ if(j.success){ if(resEl){resEl.textContent='✅ ذخیره شد'; resEl.style.color='#065f46';}} else if(resEl){resEl.textContent='❌ '+(j.data.message||'خطا'); resEl.style.color='#991b1b';}}).catch(err=>{ if(resEl){resEl.textContent='❌ '+err; resEl.style.color='#991b1b';}}).finally(()=>{ btn.disabled=false; btn.textContent=o; });
    });
    bindOnce('apex-autowatch-save', function(btn){
      btn.disabled=true; var o=btn.textContent; btn.textContent='ذخیره…';
      var enabled=qs('apex-autowatch-enabled')?.checked?1:0, threshold=parseInt(qs('apex-autowatch-threshold')?.value)||10000, minv=parseInt(qs('apex-autowatch-min')?.value)||500, maxv=parseInt(qs('apex-autowatch-max')?.value)||10000, batch=parseInt(qs('apex-autowatch-batch')?.value)||20, recent=parseInt(qs('apex-autowatch-recent')?.value)||60;
      var resEl=qs('apex-autowatch-result'); if(resEl) resEl.textContent='در حال ذخیره…';
      apexPost('apex_save_autowatch',{enabled:enabled,threshold:threshold,min_views:minv,max_views:maxv,batch:batch,only_recent_days:recent}).then(j=>{ if(j.success){ if(resEl){resEl.textContent='✅ '+j.data.message; resEl.style.color='#065f46';}} else if(resEl){resEl.textContent='❌ '+(j.data?.message||'خطا'); resEl.style.color='#991b1b';}}).catch(err=>{ if(resEl){resEl.textContent='❌ '+err; resEl.style.color='#991b1b';}}).finally(()=>{ btn.disabled=false; btn.textContent='💾 ذخیره نگهبان'; });
    });
    bindOnce('apex-commentor-save', function(btn){
      btn.disabled=true; var o=btn.textContent; btn.textContent='ذخیره…';
      var enabled=qs('apex-commentor-enabled')?.checked?1:0, threshold=parseInt(qs('apex-commentor-threshold')?.value)||10000, minc=parseInt(qs('apex-commentor-min')?.value)||2, maxc=parseInt(qs('apex-commentor-max')?.value)||4, daily=parseInt(qs('apex-commentor-daily')?.value)||2, maxpost=parseInt(qs('apex-commentor-maxpost')?.value)||60, batch=parseInt(qs('apex-commentor-batch')?.value), recent=parseInt(qs('apex-commentor-recent')?.value)||0; if(isNaN(batch)) batch=0;
      var resEl=qs('apex-commentor-result'); if(resEl) resEl.textContent='در حال ذخیره…';
      apexPost('apex_save_commentor',{enabled:enabled,threshold:threshold,min_comments:minc,max_comments:maxc,daily_growth:daily,max_per_post:maxpost,batch:batch,only_recent_days:recent}).then(j=>{ if(j.success){ if(resEl){resEl.textContent='✅ '+j.data.message; resEl.style.color='#065f46';}} else if(resEl){resEl.textContent='❌ '+(j.data?.message||'خطا'); resEl.style.color='#991b1b';}}).catch(err=>{ if(resEl){resEl.textContent='❌ '+err; resEl.style.color='#991b1b';}}).finally(()=>{ btn.disabled=false; btn.textContent='💬 ذخیره'; });
    });
    bindOnce('apex-commentor-run', function(btn){
      btn.disabled=true; var o=btn.textContent; btn.textContent='در حال اجرا…'; var resEl=qs('apex-commentor-result'); if(resEl) resEl.textContent='⏳ در حال اجرا…';
      apexPost('apex_run_commentor_now',{}).then(j=>{ if(j.success){ if(resEl){ var l=j.data.last||{}; resEl.innerHTML='✅ '+(l.posts||0)+' پست → '+(l.comments||0)+' کامنت'; resEl.style.color='#065f46';}} else if(resEl){ resEl.textContent='❌ '+(j.data?.message||'خطا'); resEl.style.color='#991b1b'; }}).catch(err=>{ if(resEl){resEl.textContent='❌ '+err;}}).finally(()=>{ btn.disabled=false; btn.textContent='▶️ اجرای فوری (تست)'; });
    });
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',run); else run(); setTimeout(run,600);
})();
</script>
