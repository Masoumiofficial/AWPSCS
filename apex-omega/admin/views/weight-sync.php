<?php if (!defined('ABSPATH')) exit;
$total_posts = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'");
$tiers = \ApexOmega\Core\TierManager::get_tiers();
$last = get_option(\ApexOmega\Core\TierManager::OPTION_LAST_DISTRIBUTION, []);
$artist_tax = \ApexOmega\Core\ArtistManager::detect_artist_taxonomy();
$ws = \ApexOmega\Core\ArtistManager::get_weight_sync_settings();
$last_snap = \ApexOmega\Core\Database::get_last_snapshot();
$assigned = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_apex_tier' AND meta_value<>''");
$pct_assigned = $total_posts ? round($assigned / $total_posts * 100) : 0;
$pending_cooldown = $ws['mode']==='cooldown' ? 'فعال — فقط اگر '.$ws['cooldown_hours'].'ساعت گذشته باشد' : '—';
?>
<div class="wrap apex-glass-wrap" dir="rtl">
  <div class="apex-glass-header">
    <div>
      <h1>همگام‌سازی وزن‌محور — یک‌کلیک حرفه‌ای</h1>
      <p>هر هنرمند → بازه وزنش — ۹۵ هرگز ۱۰k نمی‌ماند. کنترل بازنویسی از <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-settings#weightsync')); ?>">تنظیمات → همگام‌سازی</a> — با اسنپ‌شات قابل بازگشت.</p>
    </div>
    <div class="apex-glass-actions">
      <span class="apex-pill" style="background:<?php echo $pct_assigned>=90?'#dcfce7':'#fef3c7'; ?>;color:<?php echo $pct_assigned>=90?'#166534':'#92400e'; ?>"><?php echo $pct_assigned; ?>٪ تخصیص</span>
      <span class="apex-pill"><?php echo esc_html($artist_tax ?: '—'); ?> تاکسونومی</span>
    </div>
  </div>

  <div class="apex-hub-nav">
    <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-artists')); ?>">هنرمندان</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-automation')); ?>">اتوماسیون</a>
    <a class="active" href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-weight-sync')); ?>">همگام‌سازی</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-hits')); ?>">آثار منتخب</a>
  </div>

  <div class="apex-glass-card">
    <h3><span class="dot" style="background:#059669"></span>پیشرفت تخصیص</h3>
    <div style="background:#e5e7eb;border-radius:999px;height:12px;overflow:hidden"><div style="height:100%;width:<?php echo $pct_assigned; ?>%;background:linear-gradient(90deg,#059669,#06b6d4);transition:width .6s"></div></div>
    <p class="desc"><?php echo $assigned; ?> از <?php echo $total_posts; ?> آهنگ داخل بازه وزنی هستند. برای ۱۰۰٪، همگام‌سازی را بزن. حالت فعلی: <code><?php echo esc_html($ws['mode']); ?></code> — <?php echo esc_html($pending_cooldown); ?> — Batch <?php echo esc_html($ws['batch']); ?></p>
    <div class="apex-help">حالت‌ها: all=همه، cooldown=فقط قدیمی، unsynced=فقط بی‌تیره، lower_only=فقط اگر زیر کف. تنظیم از صفحه تنظیمات.</div>
  </div>

  <div class="apex-glass-card">
    <h3><span class="dot"></span>اجرا — وزن → بازه</h3>
    <p class="desc">وزن هر هنرمند را می‌خواند (۹۵ → <code>900K–10M</code>، ۲۰ → <code>1K–10K</code>) و تمام آهنگ‌هایش را رندم داخل همان بازه شارژ می‌کند — هر آهنگ عدد جدا. با تراکنش + اسنپ‌شات.</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <button type="button" id="apex-weight-preview" class="apex-btn-primary">پیش‌نمایش وزن‌محور</button>
      <button type="button" id="apex-weight-apply" class="apex-btn-ghost" style="background:#059669;color:#fff;border-color:#059669">همگام‌سازی قابل اعمال — یک‌کلیک</button>
      <span class="apex-pill"><?php echo esc_html($total_posts); ?> آهنگ کل</span>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:10px;margin-top:10px">
      <label class="apex-f" style="min-width:160px">فیلتر دسته
        <select id="apex-filter-cat" style="padding:8px;border-radius:10px;border:1px solid #e5e7eb;background:#fff">
          <option value="all">همه دسته‌ها</option>
          <?php $cats=get_categories(['hide_empty'=>false]); foreach($cats as $c): ?>
            <option value="<?php echo esc_attr($c->slug); ?>"><?php echo esc_html($c->name); ?> (<?php echo $c->count; ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="apex-f">نوع
        <select id="apex-filter-pt" style="padding:8px;border-radius:10px;border:1px solid #e5e7eb;background:#fff">
          <?php $pts=get_post_types(['public'=>true],'objects'); foreach($pts as $pt): if($pt->name==='attachment') continue; ?>
            <option value="<?php echo esc_attr($pt->name); ?>" <?php selected($pt->name,'post'); ?>><?php echo esc_html($pt->label); ?></option>
          <?php endforeach; ?>
          <option value="any">همه</option>
        </select>
      </label>
      <span class="apex-help" style="flex:1">فیلترها روی preview/apply اعمال می‌شود؛ تنظیمات سراسری (cooldown/protect) خودکار اعمال است.</span>
    </div>
    <div id="apex-weight-preview-box" class="apex-preview-box" style="min-height:70px;margin-top:10px">هنوز پیش‌نمایش نیست — بزن تا ببینی هر تیره چندتا می‌شود (حالت محافظ اعمال می‌شود).</div>
    <div id="apex-weight-result" style="margin-top:8px"></div>
    <?php if (!empty($last)): ?>
      <div style="margin-top:10px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <strong>آخرین همگام‌سازی:</strong> <?php echo esc_html($last['at'] ?? '—'); ?> — <?php echo esc_html($last['applied'] ?? 0); ?> آهنگ — <code><?php echo esc_html(substr($last['exec'] ?? '',0,8)); ?></code>
        <?php if (!empty($last['weight_sync'])) echo ' <span class="apex-pill" style="background:#dcfce7;color:#166534">وزن‌محور</span>'; ?>
        <button type="button" id="apex-undo-tier" class="apex-btn-ghost" data-exec="<?php echo esc_attr($last['exec'] ?? ''); ?>" style="color:#991b1b;border-color:#fca5a5">بازگردانی</button>
        <span id="apex-undo-result" style="font-weight:700"></span>
      </div>
    <?php endif; ?>
  </div>

  <div class="apex-glass-card">
    <h3><span class="dot" style="background:#7c3aed"></span>بازه هر تیره</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin-top:10px">
      <?php foreach($tiers as $t): ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-inline-start:4px solid <?php echo esc_attr($t['color']); ?>;border-radius:12px;padding:10px">
          <strong><?php echo esc_html($t['icon'].' '.$t['label']); ?></strong><br>
          <small style="color:#64748b"><?php echo esc_html($t['desc']); ?></small><br>
          <code><?php echo number_format_i18n($t['views_min']); ?>–<?php echo number_format_i18n($t['views_max']); ?></code><br>
          <small><?php echo $t['casts_min']; ?>–<?php echo $t['casts_max']; ?> 🗳️ | ⭐ <?php echo $t['avg_min']; ?>–<?php echo $t['avg_max']; ?></small>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="desc" style="margin-top:8px">بازه‌ها در <code>class-tier.php</code> قابل تغییرند — تغییر وزن هنرمند → همگام‌سازی مجدد کافی است.</p>
  </div>
</div>
<script>
(function(){
  function getNonce(){ return (window.ApexOmega&&ApexOmega.nonce) || document.querySelector('input[name="_wpnonce"]')?.value || ''; }
  function getAjax(){ return (window.ApexOmega&&ApexOmega.ajax_url) || ajaxurl; }
  function apexPost(a,e){ var fd=new FormData(); fd.append('action',a); fd.append('_ajax_nonce',getNonce()); for(var k in e) fd.append(k,e[k]); return fetch(getAjax(),{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.text().then(t=>{ try{return JSON.parse(t);}catch(e){throw new Error(t.slice(0,600).replace(/<[^>]*>/g,''));}}));}
  function qs(id){return document.getElementById(id);}
  function bindOnce(id,h){ var el=qs(id); if(!el||el.dataset.vanilla) return; el.dataset.vanilla='1'; el.addEventListener('click',e=>{e.preventDefault(); e.stopImmediatePropagation(); h(el,e);});}
  function run(){
    bindOnce('apex-weight-preview', function(btn){
      btn.disabled=true; var o=btn.textContent; btn.textContent='در حال پیش‌نمایش...';
      var cat=qs('apex-filter-cat')?.value||'all', pt=qs('apex-filter-pt')?.value||'any';
      var box=qs('apex-weight-preview-box'); if(box) box.textContent='⏳ در حال محاسبه وزن هر پست…';
      apexPost('apex_preview_weight_sync',{category:cat,post_type:pt,limit:10000}).then(res=>{
        if(res.success){
          var html='<div class="apex-help" style="background:#ecfdf5;border-color:#86efac;color:#065f46">وزن‌محور: '+res.data.total+' آهنگ — حالت محافظ اعمال شد</div>';
          html+='<table class="apex-table"><tr><th>دسته</th><th>تعداد</th><th>٪</th><th>میانگین</th><th>مجموع</th><th>بازه</th></tr>';
          var tot=res.data.total||1; for(var k in res.data.summary){ var s=res.data.summary[k]; var pct=Math.round(s.count/tot*100); html+='<tr><td><span style="display:inline-block;width:10px;height:10px;background:'+s.color+';border-radius:999px;margin-inline-end:6px"></span>'+s.icon+' '+s.label+'</td><td><strong>'+s.count+'</strong></td><td><div style="background:#e5e7eb;border-radius:999px;height:8px;width:80px;overflow:hidden;display:inline-block;vertical-align:middle"><div style="height:100%;width:'+pct+'%;background:'+s.color+'"></div></div> '+pct+'٪</td><td>'+s.avg_views.toLocaleString()+'</td><td>'+Number(s.total_views||0).toLocaleString()+'</td><td>'+s.views_min.toLocaleString()+'–'+s.views_max.toLocaleString()+'</td></tr>';}
          html+='</table>'; if(box) box.innerHTML=html;
        } else if(box) box.innerHTML='<div class="apex-help" style="background:#fee2e2;border-color:#fca5a5;color:#991b1b">'+(res.data.message||'خطا')+'</div>';
      }).catch(e=>{ if(box) box.innerHTML='<div class="apex-help" style="background:#fee2e2">'+e.message+'</div>';}).finally(()=>{btn.disabled=false; btn.textContent=o;});
    });
    bindOnce('apex-weight-apply', function(btn){
      if(!confirm('همگام‌سازی وزن‌محور با حالت محافظ: فقط موارد مجاز بازنویسی می‌شود (اسنپ‌شات گرفته می‌شود). ادامه؟')) return;
      btn.disabled=true; var o=btn.textContent; btn.textContent='در حال همگام‌سازی...';
      var cat=qs('apex-filter-cat')?.value||'all', pt=qs('apex-filter-pt')?.value||'any';
      var resBox=qs('apex-weight-result');
      apexPost('apex_preview_weight_sync',{category:cat,post_type:pt,limit:10000}).then(prev=>{
        if(!prev.success) throw new Error(prev.data.message||'خطا');
        var total=prev.data.total||0; if(total===0) throw new Error('هیچ پستی برای همگام‌سازی نیست (احتمالا همه در cooldown)');
        var useChunked = total > <?php echo (int)$ws['batch']; ?>;
        if(!useChunked){
          if(resBox) resBox.innerHTML='<div class="apex-help">⏳ '+total+' آهنگ — مستقیم...</div>';
          return apexPost('apex_apply_weight_sync',{category:cat,post_type:pt,limit:10000}).then(res=>{ if(res.success){ if(resBox) resBox.innerHTML='<div class="apex-help" style="background:#dcfce7">✅ '+res.data.applied+' آهنگ — '+res.data.execution_id+'</div>'; setTimeout(()=>location.reload(),2000);} else throw new Error(res.data.message);});
        } else {
          if(resBox) resBox.innerHTML='<div class="apex-help" style="background:#ecfdf5">🧩 کار آغاز شد — <strong>'+total+'</strong> آهنگ — Batch <?php echo (int)$ws['batch']; ?><br><div style="background:#e5e7eb;border-radius:999px;height:14px;margin-top:8px;overflow:hidden"><div id="apex-weight-bar" style="height:100%;width:0%;background:linear-gradient(90deg,#059669,#06b6d4);transition:width .4s"></div></div><div id="apex-weight-txt" style="margin-top:6px;font-size:13px">0 / '+total+' (0%)</div></div>';
          return apexPost('apex_start_weight_sync_job',{category:cat,post_type:pt,limit:10000,chunk_size:<?php echo (int)$ws['batch']; ?>}).then(res=>{ if(!res.success) throw new Error(res.data.message); var exec=res.data.execution_id; function loop(){ return apexPost('apex_process_weight_chunk',{execution_id:exec,chunk_size:<?php echo (int)$ws['batch']; ?>}).then(r2=>{ if(!r2.success){ if((r2.data.message||'').includes('قفل')){ var t=qs('apex-weight-txt'); if(t) t.textContent='⏳ قفل — 3ثانیه...'; return new Promise(r=>setTimeout(r,3000)).then(loop);} throw new Error(r2.data.message);} var pct=r2.data.percent||0, proc=r2.data.processed||0; var bar=qs('apex-weight-bar'), txt=qs('apex-weight-txt'); if(bar) bar.style.width=pct+'%'; if(txt) txt.textContent=proc+' / '+total+' ('+pct+'%) — '+(r2.data.status==='done'?'تمام!':'در حال پردازش...'); if(r2.data.status==='done'){ if(resBox) resBox.innerHTML+='<div class="apex-help" style="background:#dcfce7;margin-top:8px">✅ کامل — '+proc+' آهنگ</div>'; setTimeout(()=>location.reload(),1800); return;} return new Promise(r=>setTimeout(r,700)).then(loop);});} return loop();});
        }
      }).catch(e=>{ if(resBox) resBox.innerHTML='<div class="apex-help" style="background:#fee2e2;color:#991b1b">❌ '+(e.message||e)+'</div>';}).finally(()=>{btn.disabled=false; btn.textContent=o;});
    });
    bindOnce('apex-undo-tier', function(btn){
      if(!confirm('بازگردانی آخرین توزیع؟')) return; btn.disabled=true; var o=btn.textContent; btn.textContent='در حال بازگردانی...'; var exec=btn.dataset.exec||''; var resEl=qs('apex-undo-result');
      apexPost('apex_undo_tier',{execution_id:exec}).then(j=>{ if(j.success){ if(resEl) resEl.textContent='✅ '+j.data.restored; setTimeout(()=>location.reload(),800);} else if(resEl){resEl.textContent='❌ '+(j.data.message||'خطا'); resEl.style.color='#991b1b';}}).catch(e=>{ if(resEl) resEl.textContent='❌ '+e;}).finally(()=>{ btn.disabled=false; btn.textContent=o;});
    });
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',run); else run(); setTimeout(run,600);
})();
</script>
