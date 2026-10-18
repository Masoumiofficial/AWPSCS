jQuery(function($){
  const Apex = window.ApexOmega || {};
  function getNonce(){ return Apex.nonce || ''; }
  function toGmt(localStr){ if(!localStr) return ''; const d=new Date(localStr); return d.toISOString().slice(0,19).replace('T',' '); }

  // ===== دستی متاباکس =====
  $(document).on('click', '#apex-save-manual', function(e){
    e.preventDefault();
    const $btn=$(this); const postId=$('#post_ID').val()||$('input[name="post_id"]').val();
    if(!postId){ alert('شناسه نوشته یافت نشد.'); return; }
    const data={action:'apex_save_manual',_ajax_nonce:getNonce(),post_id:postId,mode:$('#_apex_mode').val()||'manual',views:$('#apex_views').val(),casts:$('#apex_casts').val(),avg:$('#apex_avg').val(),confirm_volatility:$('#apex_confirm_volatility').is(':checked')?1:0,confirm_inconsistency:$('#apex_confirm_inconsistency').is(':checked')?1:0};
    $btn.prop('disabled',true).text('در حال ذخیره…');
    $.post(Apex.ajax_url,data,function(res){
      if(res.success) $('#apex-manual-result').html('<div class="apex-alert success">ذخیره شد. '+ (res.data.execution_id||'') +'</div>');
      else { let msg=res.data.message||JSON.stringify(res.data); if(res.data.requires_confirm) msg=(res.data.warnings||[]).join('<br>')+'<br>لطفاً تأیید را علامت بزنید.'; $('#apex-manual-result').html('<div class="apex-alert error">'+msg+'</div>'); }
    }).fail(function(xhr){ $('#apex-manual-result').html('<div class="apex-alert error">'+(xhr.responseJSON?JSON.stringify(xhr.responseJSON.data):'خطا')+'</div>'); }).always(()=> $btn.prop('disabled',false).text('ذخیره دستی'));
  });

  // ===== زمان‌بندی =====
  $(document).on('click','#apex-preview-schedule',function(e){
    e.preventDefault(); const postId=$('#post_ID').val()||$('input[name="post_id"]').val()||$('#apex_schedule_post_id').val();
    if(!postId){ alert('شناسه نیست'); return; }
    const data={action:'apex_preview_schedule',_ajax_nonce:getNonce(),post_id:postId,field_type:$('#apex_sched_field').val(),target_value:$('#apex_sched_target').val(),start_at_gmt:toGmt($('#apex_sched_start').val()),end_at_gmt:toGmt($('#apex_sched_end').val()),interval_seconds:$('#apex_sched_interval').val()};
    $('#apex-preview-box').html('<div class="apex-alert success">در حال محاسبه…</div>');
    $.post(Apex.ajax_url,data,function(res){
      if(res.success){ let html='<div><strong>فعلی:</strong> '+res.data.current+' | <strong>هدف:</strong> '+res.data.target+' | گام‌ها: '+res.data.total_steps+'</div><table class="apex-table" style="margin-top:8px"><tr><th>#</th><th>مقدار</th><th>زمان</th></tr>'; res.data.steps.forEach(s=> html+='<tr><td>'+s.step+'</td><td>'+s.value+'</td><td>'+s.at+'</td></tr>'); html+='</table>'; $('#apex-preview-box').html(html); }
      else $('#apex-preview-box').html('<div class="apex-alert error">'+(res.data.message||'خطا')+'</div>');
    });
  });
  $(document).on('click','#apex-create-schedule',function(e){
    e.preventDefault(); const postId=$('#post_ID').val()||$('input[name="post_id"]').val()||$('#apex_schedule_post_id').val();
    const data={action:'apex_create_schedule',_ajax_nonce:getNonce(),post_id:postId,field_type:$('#apex_sched_field').val(),target_value:$('#apex_sched_target').val(),start_at_gmt:toGmt($('#apex_sched_start').val()),end_at_gmt:toGmt($('#apex_sched_end').val()),interval_seconds:$('#apex_sched_interval').val(),min_allowed:$('#apex_sched_min').val(),max_allowed:$('#apex_sched_max').val(),max_step:$('#apex_sched_max_step').val(),max_percent_step:$('#apex_sched_max_percent').val(),require_confirm_large_drop:$('#apex_req_confirm').is(':checked')?1:0,confirm_large_drop:$('#apex_confirm_large_drop').is(':checked')?1:0,confirm_volatility:$('#apex_sched_confirm_vol').is(':checked')?1:0};
    const $btn=$(this); $btn.prop('disabled',true).text('در حال ایجاد…');
    $.post(Apex.ajax_url,data,function(res){ if(res.success){ $('#apex-schedule-result').html('<div class="apex-alert success">برنامه #'+res.data.id+' ساخته شد</div>'); setTimeout(()=>location.reload(),800);} else { let msg=res.data.message||(res.data.errors?res.data.errors.join('<br>'):'')||(res.data.warnings?res.data.warnings.join('<br>'):'خطا'); if(res.data.requires_confirm) msg=(res.data.warnings||[]).join('<br>')+'<br>'+(res.data.message||'نیاز به تأیید'); $('#apex-schedule-result').html('<div class="apex-alert error">'+msg+'</div>');}}).fail(function(xhr){ let d=xhr.responseJSON&&xhr.responseJSON.data?xhr.responseJSON.data:{}; let msg=d.message||d.errors||'خطا'; if(Array.isArray(msg)) msg=msg.join('<br>'); if(d.warnings) msg=d.warnings.join('<br>')+'<br>'+msg; $('#apex-schedule-result').html('<div class="apex-alert error">'+msg+'</div>');}).always(()=> $btn.prop('disabled',false).text('ایجاد برنامه'));
  });
  $(document).on('click','.apex-pause',function(){ const id=$(this).data('id'); $.post(Apex.ajax_url,{action:'apex_pause_schedule',_ajax_nonce:getNonce(),schedule_id:id},r=> r.success?location.reload():alert(r.data.message)); });
  $(document).on('click','.apex-resume',function(){ const id=$(this).data('id'); $.post(Apex.ajax_url,{action:'apex_resume_schedule',_ajax_nonce:getNonce(),schedule_id:id},r=> r.success?location.reload():alert(r.data.message)); });
  $(document).on('click','.apex-cancel',function(){ if(!confirm('لغو؟'))return; const id=$(this).data('id'); $.post(Apex.ajax_url,{action:'apex_cancel_schedule',_ajax_nonce:getNonce(),schedule_id:id},r=> r.success?location.reload():alert(r.data.message)); });
  $(document).on('click','.apex-rollback',function(){ if(!confirm('بازگردانی؟'))return; const hid=$(this).data('id'); $.post(Apex.ajax_url,{action:'apex_rollback',_ajax_nonce:getNonce(),history_id:hid},r=> r.success?(alert('بازگردانی شد'),location.reload()):alert(r.data.message)); });
  $(document).on('click','#apex-bulk-apply',function(e){ e.preventDefault(); if(!confirm(Apex.i18n.confirm_bulk))return; const data={action:'apex_bulk_apply',_ajax_nonce:getNonce(),post_ids:$('#apex_bulk_ids').val(),field_type:$('#apex_bulk_field').val(),value:$('#apex_bulk_value').val(),mode:$('#apex_bulk_mode').val()}; const $btn=$(this); $btn.prop('disabled',true).text('در حال اعمال…'); $.post(Apex.ajax_url,data,r=>{ if(r.success) $('#apex-bulk-result').html('<div class="apex-alert success">موفق: '+r.data.success_count+'</div>'); else $('#apex-bulk-result').html('<div class="apex-alert error">'+(r.data.message||'خطا')+'</div>'); }).always(()=> $btn.prop('disabled',false).text('اعمال گروهی')); });
  $(document).on('change','#_apex_mode',function(){ if($(this).val()==='scheduled') $('#apex-schedule-panel').show(); else $('#apex-schedule-panel').hide(); });

  // ======== توزیع هوشمند TIER ===========
  function collectTierCounts(){
    const out={}; $('.apex-tier-count').each(function(){ const k=$(this).data('tier'); const v=parseInt($(this).val()||0); if(v>0) out[k]=v; else if(v===0 && $(this).val()!=='' ) out[k]=0; });
    return out;
  }
  function updateTierTotals(){
    let total=0; $('.apex-tier-count').each(function(){ total+= parseInt($(this).val()||0); });
    $('#apex-total-selected').text(total);
    const avail = parseInt($('#apex-remaining').data('total')|| $('#apex-remaining').text().replace(/,/g,'') || 0);
    // remaining = total_posts - total
    const totalPosts = parseInt($('.apex-hero-stats div:first .big').text().replace(/,/g,'') || '0');
    // better get from initial
    const initTotal = Apex.total_posts || totalPosts;
    $('#apex-remaining').text( Math.max(0, initTotal - total) );
  }
  // stepper
  $(document).on('click','.apex-plus',function(){ const t=$(this).data('tier'); const $in=$('.apex-tier-count[data-tier="'+t+'"]'); let v=parseInt($in.val()||0); $in.val(v+1).trigger('input'); $('.apex-tier-slider[data-tier="'+t+'"]').val(v+1); updateTierTotals(); });
  $(document).on('click','.apex-minus',function(){ const t=$(this).data('tier'); const $in=$('.apex-tier-count[data-tier="'+t+'"]'); let v=parseInt($in.val()||0); if(v>0) $in.val(v-1).trigger('input'); $('.apex-tier-slider[data-tier="'+t+'"]').val(Math.max(0,v-1)); updateTierTotals(); });
  $(document).on('input','.apex-tier-count',function(){ const t=$(this).data('tier'); $('.apex-tier-slider[data-tier="'+t+'"]').val($(this).val()); updateTierTotals(); });
  $(document).on('input','.apex-tier-slider',function(){ const t=$(this).data('tier'); $('.apex-tier-count[data-tier="'+t+'"]').val($(this).val()).trigger('input'); });

  // presets
  $(document).on('click','#apex-preset-pyramid',function(){
    const total = Apex.total_posts || parseInt($('.apex-hero-stats .big').first().text().replace(/,/g,'')||'0');
    if(!total){ alert('تعداد کل آثار 0 است'); return; }
    // هرم استاندارد 2/4/10/14/20/25/25
    const perc={tier_1:0.02,tier_2:0.04,tier_3:0.10,tier_4:0.14,tier_5:0.20,tier_6:0.25,tier_7:0.25};
    let sum=0; const out={};
    Object.keys(perc).forEach(k=>{ const c=Math.round(total*perc[k]); out[k]=c; sum+=c; $('.apex-tier-count[data-tier="'+k+'"]').val(c); $('.apex-tier-slider[data-tier="'+k+'"]').val(c); });
    out['tier_7']+= total - sum;
    $('.apex-tier-count[data-tier="tier_7"]').val(out['tier_7']); $('.apex-tier-slider[data-tier="tier_7"]').val(out['tier_7']);
    updateTierTotals();
  });
  $(document).on('click','#apex-preset-equal',function(){
    const total= Apex.total_posts || 0; if(!total) return;
    const per=Math.floor(total/7); let rem=total - per*7;
    ['tier_1','tier_2','tier_3','tier_4','tier_5','tier_6','tier_7'].forEach(k=>{ let v=per; if(rem>0){v++; rem--;} $('.apex-tier-count[data-tier="'+k+'"]').val(v); $('.apex-tier-slider[data-tier="'+k+'"]').val(v); });
    updateTierTotals();
  });
  $(document).on('click','#apex-preset-clear',function(){ $('.apex-tier-count').val(0); $('.apex-tier-slider').val(0); updateTierTotals(); $('#apex-tier-preview-box').html('صفر شد.'); });

  // preview
  $(document).on('click','#apex-preview-tier',function(e){
    e.preventDefault(); const $btn=$(this); $btn.prop('disabled',true).text('در حال پیش‌نمایش…');
    const counts=collectTierCounts(); if(Object.keys(counts).length===0){ alert('حداقل یک دسته را پر کن'); $btn.prop('disabled',false).text('👁️ پیش‌نمایش'); return; }
    $.post(Apex.ajax_url,{action:'apex_preview_tier',_ajax_nonce:getNonce(),counts:counts,category:$('#apex-filter-cat').val(),post_type:$('#apex-filter-pt').val()},function(res){
      if(res.success){
        let html='<div class="apex-alert success">تعداد درخواستی: '+res.data.total_requested+' | موجود: '+res.data.total_available+' | باقی‌مانده: '+res.data.remaining+'</div>';
        html+='<table class="apex-table"><tr><th>دسته</th><th>تعداد</th><th>میانگین بازدید</th><th>میانگین رأی</th><th>مجموع بازدید</th></tr>';
        Object.keys(res.data.summary).forEach(k=>{ const s=res.data.summary[k]; html+='<tr><td>'+s.icon+' '+s.label+'</td><td>'+s.count+'</td><td>'+s.avg_views.toLocaleString('fa-IR')+'</td><td>'+s.avg_rating+' ('+s.avg_casts+') '+'</td><td>'+s.total_views.toLocaleString('fa-IR')+'</td></tr>'; });
        html+='</table>';
        // نمونه 3 آهنگ اول هر دسته
        html+='<div style="margin-top:10px;max-height:220px;overflow:auto;"><strong>نمونه‌ها (3 اول هر دسته):</strong><ul>';
        Object.keys(res.data.preview).forEach(k=>{ const arr=res.data.preview[k]; const t=res.data.tiers[k]; html+='<li><strong>'+t.icon+' '+t.label+':</strong> '; html+= arr.slice(0,3).map(it=> it.post_title+' → '+it.views.toLocaleString('fa-IR')+' بازدید، '+it.avg+'⭐ ('+it.casts+')').join(' | '); html+='</li>'; });
        html+='</ul></div>';
        $('#apex-tier-preview-box').html(html);
        $('#apex-preview-hint').text('پیش‌نمایش آماده — اگر اوکیه «اعمال خودکار» بزن');
      } else { $('#apex-tier-preview-box').html('<div class="apex-alert error">'+(res.data.message||'خطا')+'</div>'); }
    }).fail(function(xhr){ $('#apex-tier-preview-box').html('<div class="apex-alert error">'+(xhr.responseJSON?JSON.stringify(xhr.responseJSON.data):'خطا')+'</div>'); }).always(()=> $btn.prop('disabled',false).text('👁️ پیش‌نمایش'));
  });

  // apply
  $(document).on('click','#apex-apply-tier',function(e){
    e.preventDefault(); if(!confirm('این عملیات تمام آثار انتخاب‌شده را بازنویسی می‌کند (با تاریخچه و قابل بازگردانی). ادامه؟')) return;
    const $btn=$(this); $btn.prop('disabled',true).text('در حال اعمال…');
    const counts=collectTierCounts();
    $.post(Apex.ajax_url,{action:'apex_apply_tier',_ajax_nonce:getNonce(),counts:counts,category:$('#apex-filter-cat').val(),post_type:$('#apex-filter-pt').val(),auto_enable:$('#apex-auto-enable').is(':checked')?1:0},function(res){
      if(res.success){ $('#apex-tier-result').html('<div class="apex-alert success">✅ '+res.data.applied+' آهنگ با موفقیت توزیع شد. شناسه: '+res.data.execution_id+'<br>خطا: '+(res.data.errors.length? res.data.errors.join('<br>'):'ندارد')+'</div>'); setTimeout(()=>location.reload(),900); }
      else $('#apex-tier-result').html('<div class="apex-alert error">'+(res.data.message||JSON.stringify(res.data))+'</div>');
    }).fail(function(xhr){ $('#apex-tier-result').html('<div class="apex-alert error">'+(xhr.responseJSON?JSON.stringify(xhr.responseJSON.data):'خطا')+'</div>'); }).always(()=> $btn.prop('disabled',false).text('✅ اعمال خودکار (با ثبت تاریخچه)'));
  });

  // AI suggest
  $(document).on('click','#apex-ai-suggest',function(e){
    e.preventDefault(); const $btn=$(this); $btn.prop('disabled',true).text('در حال پرسش از AI…');
    $.post(Apex.ajax_url,{action:'apex_ai_suggest',_ajax_nonce:getNonce()},function(res){
      if(res.success && res.data.suggestion){
        Object.keys(res.data.suggestion).forEach(k=>{ const v=res.data.suggestion[k]; $('.apex-tier-count[data-tier="'+k+'"]').val(v); $('.apex-tier-slider[data-tier="'+k+'"]').val(v); });
        updateTierTotals(); $('#apex-tier-preview-box').html('<div class="apex-alert success">✨ پیشنهاد AI اعمال شد ('+res.data.source+'): '+ (res.data.explanation? res.data.explanation.substring(0,300):'') +'</div>');
      } else { let msg=res.data.message||'خطا'; if(res.data.fallback) msg+=' — از هرم پیش‌فرض استفاده شد'; $('#apex-tier-preview-box').html('<div class="apex-alert error">'+msg+'</div>'); if(res.data.fallback){ Object.keys(res.data.fallback).forEach(k=>{ $('.apex-tier-count[data-tier="'+k+'"]').val(res.data.fallback[k]); $('.apex-tier-slider[data-tier="'+k+'"]').val(res.data.fallback[k]); }); updateTierTotals(); } }
    }).fail(function(xhr){ $('#apex-tier-preview-box').html('<div class="apex-alert error">'+(xhr.responseJSON?JSON.stringify(xhr.responseJSON.data):'خطا')+'</div>'); }).always(()=> $btn.prop('disabled',false).text('✨ پیشنهاد AI'));
  });

  // AI save
  $(document).on('click','#apex-ai-save',function(e){
    e.preventDefault(); const data={action:'apex_ai_save',_ajax_nonce:getNonce(),api_key:$('#apex-ai-key').val(), model:$('#apex-ai-model').val(), endpoint:$('#apex-ai-endpoint').val(), provider:'gapgpt'};
    const $btn=$(this); $btn.text('ذخیره…');
    $.post(Apex.ajax_url,data,function(res){ if(res.success) alert('ذخیره شد'); else alert(res.data.message); }).always(()=> $btn.text('💾 ذخیره کلید'));
  });

  // sync total_posts from PHP
  if(Apex.total_posts) updateTierTotals();
});
