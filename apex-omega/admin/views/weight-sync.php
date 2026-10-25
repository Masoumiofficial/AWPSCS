<?php if (!defined('ABSPATH')) exit;
$total_posts = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'");
$tiers = \ApexOmega\Core\TierManager::get_tiers();
$last = get_option(\ApexOmega\Core\TierManager::OPTION_LAST_DISTRIBUTION, []);
$artist_tax = \ApexOmega\Core\ArtistManager::detect_artist_taxonomy();
$weights_opt = get_option(\ApexOmega\Core\ArtistManager::OPTION_WEIGHTS, []);
$last_snap = \ApexOmega\Core\Database::get_last_snapshot();
// محاسبه درصد تخصیص‌یافته: پست‌هایی که _apex_tier دارند
$assigned = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_apex_tier' AND meta_value<>''");
$pct_assigned = $total_posts ? round($assigned / $total_posts * 100) : 0;
?>
<div class="wrap apex-wrap">
  <div class="apex-hero" style="background: linear-gradient(135deg,#059669 0%,#06b6d4 50%,#7c3aed 100%); color:#fff; border:none;">
    <div>
      <h1 style="margin:0;font-size:26px;color:#fff;">⚡ همگام‌سازی وزن‌محور — یک‌کلیک حرفه‌ای</h1>
      <p style="margin:6px 0 0;opacity:0.9;">هر هنرمند تمام آهنگ‌هاش دقیق داخل بازه وزنش — ۹۵ هرگز ۱۰k نمی‌ماند. تروتمیز، سریع، با اسنپ‌شات.</p>
    </div>
    <div class="apex-hero-stats" style="color:#fff;">
      <div style="background:rgba(255,255,255,0.15);"><span class="big" style="color:#fff;"><?php echo esc_html(number_format_i18n($total_posts)); ?></span><small style="color:#fff;">کل آهنگ</small></div>
      <div style="background:rgba(255,255,255,0.15);"><span class="big" style="color:#fff;"><?php echo esc_html(number_format_i18n($assigned)); ?></span><small style="color:#fff;">تخصیص‌یافته (<?php echo $pct_assigned; ?>٪)</small></div>
      <div style="background:rgba(255,255,255,0.15);"><span class="big" style="color:#fff;"><?php echo esc_html($artist_tax ?: '—'); ?></span><small style="color:#fff;">تاکسونومی</small></div>
    </div>
  </div>

  <div style="display:flex;gap:8px;margin:14px 0;flex-wrap:wrap">
    <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-artists')); ?>" style="padding:8px 14px;border-radius:999px;text-decoration:none;font-size:13px;border:1px solid #d1d5db;background:#fff;color:#334155">🎤 هنرمندان</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-automation')); ?>" style="padding:8px 14px;border-radius:999px;text-decoration:none;font-size:13px;border:1px solid #d1d5db;background:#fff;color:#334155">⚙️ اتوماسیون</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-weight-sync')); ?>" style="padding:8px 14px;border-radius:999px;text-decoration:none;font-size:13px;border:1px solid #059669;background:#059669;color:#fff">⚡ همگام‌سازی</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-hits')); ?>" style="padding:8px 14px;border-radius:999px;text-decoration:none;font-size:13px;border:1px solid #d1d5db;background:#fff;color:#334155">⭐ آثار منتخب</a>
  </div>

  <div class="apex-card" style="margin-top:14px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
      <h3 style="margin:0;">📊 پیشرفت همگام‌سازی</h3>
      <span class="apex-badge" style="background:<?php echo $pct_assigned>=90?'#059669':($pct_assigned>=50?'#f59e0b':'#ef4444'); ?>;color:#fff;"><?php echo $pct_assigned; ?>٪</span>
    </div>
    <div style="background:#e5e7eb;border-radius:999px;height:14px;margin-top:10px;overflow:hidden;">
      <div style="height:100%;width:<?php echo $pct_assigned; ?>%;background:linear-gradient(90deg,#059669,#06b6d4);transition:width 0.6s;"></div>
    </div>
    <small style="color:#6b7280;"><?php echo $assigned; ?> از <?php echo $total_posts; ?> آهنگ داخل بازه وزنی هستند. برای 100٪، همگام‌سازی را بزن. بعد هر بار وزن عوض شد دوباره بزن.</small>
  </div>

  <div class="apex-card" style="border-top:4px solid #059669; background:#ecfdf5;">
    <h3 style="margin:0;color:#065f46;">⚡ همگام‌سازی یک‌کلیک — وزن → بازه</h3>
    <div style="background:#fff;border:1px solid #a7f3d0;border-radius:10px;padding:10px;margin-top:10px;font-size:13px;color:#064e3b;">
      <strong>چی کار می‌کند؟</strong> وزن هر هنرمند را می‌خواند (۹۵ → الماس <code>900K–10M</code>، ۲۰ → تازه <code>1K–10K</code>) و <strong>تمام آهنگ‌های همان هنرمند</strong> را رندم داخل همان بازه شارژ می‌کند — هر آهنگ عدد جدا. با تراکنش + اسنپ‌شات قابل Undo.
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:12px;">
      <button type="button" id="apex-weight-preview" class="button button-apex secondary">👁️ پیش‌نمایش وزن‌محور</button>
      <button type="button" id="apex-weight-apply" class="button button-apex" style="background:#059669;border-color:#059669;">⚡ همگام‌سازی همه — یک‌کلیک</button>
      <span style="color:#6b7280;font-size:12px;">از فیلتر پایین استفاده می‌کند</span>
    </div>
    <div style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap; align-items:center; background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:10px;">
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
    <div id="apex-weight-preview-box" class="apex-preview-box" style="min-height:70px;margin-top:10px;">هنوز پیش‌نمایش نیست — بزن تا ببینی هر تیره چندتا می‌شود.</div>
    <div id="apex-weight-result" style="margin-top:8px;"></div>
    <?php if (!empty($last)): ?>
      <div style="margin-top:10px; background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:10px;">
        <strong>آخرین همگام‌سازی:</strong> <?php echo esc_html($last['at'] ?? '—'); ?> — <?php echo esc_html($last['applied'] ?? 0); ?> آهنگ — <code><?php echo esc_html(substr($last['exec'] ?? '',0,8)); ?></code>
        <?php if (!empty($last['weight_sync'])) echo ' <span class="apex-badge" style="background:#059669;color:#fff;">وزن‌محور</span>'; ?>
        <button type="button" id="apex-undo-tier" class="button button-apex secondary" data-exec="<?php echo esc_attr($last['exec'] ?? ''); ?>" style="background:#fef2f2; border-color:#fca5a5; color:#991b1b; margin-right:8px;">↩️ بازگردانی (Undo)</button>
        <span id="apex-undo-result" style="font-weight:700;"></span>
      </div>
    <?php endif; ?>
  </div>

  <div class="apex-card" style="background:#f9fafb;">
    <h3 style="margin:0;">📋 بازه هر تیره (قابل تغییر در کد: class-tier.php)</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin-top:10px;">
      <?php foreach($tiers as $t): ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-left:4px solid <?php echo esc_attr($t['color']); ?>;border-radius:10px;padding:10px;">
          <strong><?php echo esc_html($t['icon'].' '.$t['label']); ?></strong><br>
          <small style="color:#6b7280;"><?php echo esc_html($t['desc']); ?></small><br>
          <code><?php echo number_format_i18n($t['views_min']); ?>–<?php echo number_format_i18n($t['views_max']); ?></code> 👁️<br>
          <code><?php echo $t['casts_min']; ?>–<?php echo $t['casts_max']; ?></code> 🗳️ | ⭐ <?php echo $t['avg_min']; ?>–<?php echo $t['avg_max']; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<script>
(function(){
  function getNonce(){ return (window.ApexOmega&&ApexOmega.nonce) || document.querySelector('input[name=\"_wpnonce\"]')?.value || document.querySelector('#_wpnonce')?.value || ''; }
  function getAjax(){ return (window.ApexOmega&&ApexOmega.ajax_url) || '/wp-admin/admin-ajax.php'; }
  function apexPost(action, extra){
    var fd=new FormData(); fd.append('action',action); fd.append('_ajax_nonce',getNonce());
    for(var k in extra) fd.append(k, extra[k]);
    return fetch(getAjax(),{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.text().then(txt=>{ try{return JSON.parse(txt);}catch(e){ throw new Error(txt.slice(0,400).replace(/<[^>]*>/g,''));}}));
  }
  function qs(id){return document.getElementById(id);}
  function bindOnce(id, h){ var el=qs(id); if(!el||el.dataset.vanilla) return; el.dataset.vanilla='1'; el.addEventListener('click', e=>{e.preventDefault(); e.stopImmediatePropagation(); h(el,e);});}
  function run(){
    bindOnce('apex-weight-preview', function(btn){
      btn.disabled=true; var o=btn.textContent; btn.textContent='در حال پیش‌نمایش…';
      var cat=qs('apex-filter-cat')?.value||'all', pt=qs('apex-filter-pt')?.value||'any';
      var box=qs('apex-weight-preview-box');
      if(box) box.innerHTML='⏳ در حال محاسبه وزن هر پست… (batch سریع)';
      apexPost('apex_preview_weight_sync',{category:cat,post_type:pt,limit:10000}).then(res=>{
        if(res.success){
          var html='<div class=\"apex-alert success\">وزن‌محور: '+res.data.total+' آهنگ — هر هنرمند داخل بازه وزنش</div>';
          html+='<table class=\"apex-table\"><tr><th>دسته</th><th>تعداد</th><th>درصد</th><th>میانگین بازدید</th><th>بازدید میانگین/کل</th><th>بازه</th></tr>';
          var total=res.data.total||1;
          for(var kk in res.data.summary){ var s=res.data.summary[kk]; var pct=Math.round(s.count/total*100); html+='<tr><td><span style=\"display:inline-block;width:10px;height:10px;background:'+s.color+';border-radius:999px;margin-left:6px;\"></span>'+s.icon+' '+s.label+'</td><td><strong>'+s.count+'</strong></td><td><div style=\"background:#e5e7eb;border-radius:999px;height:8px;width:80px;overflow:hidden;display:inline-block;vertical-align:middle;\"><div style=\"height:100%;width:'+pct+'%;background:'+s.color+';\"></div></div> '+pct+'٪</td><td>'+s.avg_views.toLocaleString()+'</td><td>'+Number(s.total_views||0).toLocaleString()+'</td><td>'+s.views_min.toLocaleString()+'–'+s.views_max.toLocaleString()+'</td></tr>'; }
          html+='</table>';
          if(box) box.innerHTML=html;
        } else if(box) box.innerHTML='<div class=\"apex-alert error\">'+(res.data.message||'خطا')+'</div>';
      }).catch(err=>{ if(box) box.innerHTML='<div class=\"apex-alert error\">❌ '+err.message+'</div>';}).finally(()=>{btn.disabled=false; btn.textContent=o;});
    });
    bindOnce('apex-weight-apply', function(btn){
      if(!confirm('همگام‌سازی وزن‌محور: همه آهنگ‌ها بر اساس وزن هنرمند بازنویسی می‌شوند (رندم داخل بازه هر تیره). اسنپ‌شات گرفته می‌شود. ادامه؟')) return;
      btn.disabled=true; var o=btn.textContent; btn.textContent='در حال همگام‌سازی…';
      var cat=qs('apex-filter-cat')?.value||'all', pt=qs('apex-filter-pt')?.value||'any';
      var resBox=qs('apex-weight-result');
      apexPost('apex_preview_weight_sync',{category:cat,post_type:pt,limit:10000}).then(prev=>{
        if(!prev.success) throw new Error(prev.data.message||'خطا در پیش‌نمایش');
        var total=prev.data.total||0; if(total===0) throw new Error('هیچ پستی یافت نشد');
        var useChunked = total > 80;
        if(!useChunked){
          if(resBox) resBox.innerHTML='<div class=\"apex-alert warning\">⏳ '+total+' آهنگ — مستقیم…</div>';
          return apexPost('apex_apply_weight_sync',{category:cat,post_type:pt,limit:10000}).then(res=>{
            if(res.success){ if(resBox) resBox.innerHTML='<div class=\"apex-alert success\">✅ '+res.data.applied+' آهنگ همگام شد — شناسه: '+res.data.execution_id+'</div>'; setTimeout(()=>location.reload(),2000);}
            else throw new Error(res.data.message||'خطا');
          });
        } else {
          if(resBox) resBox.innerHTML='<div class=\"apex-alert success\">🧩 کار وزن‌محور آغاز شد — <strong>'+total+'</strong> آهنگ — هر درخواست 100 تا<br><div style=\"background:#e5e7eb;border-radius:999px;height:16px;margin-top:8px;overflow:hidden;\"><div id=\"apex-weight-bar\" style=\"height:100%;width:0%;background:linear-gradient(90deg,#059669,#06b6d4);transition:width 0.4s;\"></div></div><div id=\"apex-weight-txt\" style=\"margin-top:6px;font-size:13px;\">0 / '+total+' (0%) — آماده‌سازی...</div></div>';
          return apexPost('apex_start_weight_sync_job',{category:cat,post_type:pt,limit:10000,chunk_size:100}).then(res=>{
            if(!res.success) throw new Error(res.data.message||'خطا در ایجاد کار');
            var exec=res.data.execution_id;
            function processChunk(){
              return apexPost('apex_process_weight_chunk',{execution_id:exec,chunk_size:100}).then(r2=>{
                if(!r2.success){ var isLock=(r2.data&&r2.data.message||'').indexOf('قفل')!==-1; if(isLock){ var txt=qs('apex-weight-txt'); if(txt) txt.textContent='⏳ قفل — 3ثانیه…'; return new Promise(res=>setTimeout(res,3000)).then(processChunk); } throw new Error(r2.data.message||'خطا chunk'); }
                var pct=r2.data.percent||0, proc=r2.data.processed||0;
                var bar=qs('apex-weight-bar'), txt=qs('apex-weight-txt'); if(bar) bar.style.width=pct+'%'; if(txt) txt.textContent=proc+' / '+total+' ('+pct+'%) — '+(r2.data.status==='done'?'تمام!':'در حال پردازش...');
                if(r2.data.status==='done'){ if(resBox) resBox.innerHTML+='<div class=\"apex-alert success\" style=\"margin-top:8px;\">✅ همگام‌سازی کامل — '+proc+' آهنگ — شناسه: '+exec+'</div>'; setTimeout(()=>location.reload(),1800); return; }
                return new Promise(res=>setTimeout(res,700)).then(processChunk);
              });
            }
            return processChunk();
          });
        }
      }).catch(err=>{ if(resBox) resBox.innerHTML='<div class=\"apex-alert error\">❌ '+(err.message||err)+'</div>';}).finally(()=>{ btn.disabled=false; btn.textContent=o; });
    });
    bindOnce('apex-undo-tier', function(btn){
      if(!confirm('بازگردانی آخرین توزیع؟')) return;
      btn.disabled=true; var o=btn.textContent; btn.textContent='در حال بازگردانی…';
      var exec=btn.dataset.exec||''; var resEl=qs('apex-undo-result');
      apexPost('apex_undo_tier',{execution_id:exec}).then(j=>{
        if(j.success){ if(resEl) resEl.textContent='✅ بازگردانی شد: '+j.data.restored+' آهنگ'; setTimeout(()=>location.reload(),800);}
        else if(resEl){resEl.textContent='❌ '+(j.data.message||'خطا'); resEl.style.color='#991b1b';}
      }).catch(e=>{ if(resEl) resEl.textContent='❌ '+e;}).finally(()=>{ btn.disabled=false; btn.textContent=o;});
    });
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',run); else run(); setTimeout(run,600);
})();
</script>
