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
    $.post(Apex.ajax_url,data,function(res){ if(res.success){ $('#apex-schedule-result').html('<div class="apex-alert success">برنامه #'+res.data.id+' ساخته شد</div>'); setTimeout(()=>location.reload(),800);} else { let msg=res.data.message||(res.data.errors?res.data.errors.join('<br>'):'')||(res.data.warnings?res.data.warnings.join('<br>'):'خطا'); if(res.data.requires_confirm) msg=(res.data.warnings||[]).join('<br>')+'<br>'+(res.data.message||'نیاز به تأیید'); $('#apex-schedule-result').html('<div class="apex-alert error">'+msg+'</div>');}}).fail(function(xhr){ let d=xhr.responseJSON&&xhr.responseJSON.data?xhr.responseJSON.data:{}; let msg=d.message||d.errors||'خطا'; if(Array.isArray(msg)) msg=msg.join('<br>'); if(d.warnings) msg=d.warnings.join('<br>')+'<br>'+msg; $('#apex-schedule-result').html('<div class=\"apex-alert error\">'+msg+'</div>');}).always(()=> $btn.prop('disabled',false).text('ایجاد برنامه'));
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
    const initTotal = Apex.total_posts || parseInt($('#apex-remaining').data('total')||0) || parseInt($('.apex-hero-stats .big').first().text().replace(/,/g,'')||'0');
    $('#apex-remaining').text( Math.max(0, initTotal - total) );
    // dynamic slider max: each slider max = remaining + current value
    const remaining = Math.max(0, initTotal - total);
    $('.apex-tier-count').each(function(){
      const tier=$(this).data('tier');
      const cur=parseInt($(this).val()||0);
      const newMax = remaining + cur;
      const $slider = $('.apex-tier-slider[data-tier="'+tier+'"]');
      $slider.attr('max', newMax);
      $slider.val(cur);
      // update hint
      $('.apex-slider-hint[data-tier="'+tier+'"]').text('حداکثر پویا: '+ newMax.toLocaleString('fa-IR') + ' — باقی‌مانده کل: ' + remaining.toLocaleString('fa-IR'));
    });
  }
  function applyUIMode(){
    const isPro = $('#apex-ui-mode-toggle').is(':checked');
    if(isPro){
      $('.apex-pro-only').show();
      $('.apex-wrap').attr('data-ui-mode','pro');
    } else {
      $('.apex-pro-only').hide();
      $('.apex-wrap').attr('data-ui-mode','simple');
    }
  }
  // init UI mode
  applyUIMode();
  $(document).on('change','#apex-ui-mode-toggle',function(){
    const mode = $(this).is(':checked') ? 'pro' : 'simple';
    applyUIMode();
    $.post(Apex.ajax_url,{action:'apex_save_ui_mode',_ajax_nonce:getNonce(),mode:mode},function(res){
      // no reload needed
    });
  });

  // stepper
  $(document).on('click','.apex-plus',function(){ const t=$(this).data('tier'); const $in=$('.apex-tier-count[data-tier="'+t+'"]'); let v=parseInt($in.val()||0); $in.val(v+1).trigger('input'); updateTierTotals(); });
  $(document).on('click','.apex-minus',function(){ const t=$(this).data('tier'); const $in=$('.apex-tier-count[data-tier="'+t+'"]'); let v=parseInt($in.val()||0); if(v>0) $in.val(v-1).trigger('input'); updateTierTotals(); });
  $(document).on('input','.apex-tier-count',function(){ const t=$(this).data('tier'); $('.apex-tier-slider[data-tier="'+t+'"]').val($(this).val()); updateTierTotals(); });
  $(document).on('input','.apex-tier-slider',function(){ const t=$(this).data('tier'); $('.apex-tier-count[data-tier="'+t+'"]').val($(this).val()).trigger('input'); });

  // presets
  $(document).on('click','#apex-preset-pyramid',function(){
    const total = Apex.total_posts || parseInt($('.apex-hero-stats .big').first().text().replace(/,/g,'')||'0');
    if(!total){ alert('تعداد کل آثار 0 است'); return; }
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
  $(document).on('click','#apex-preset-clear',function(){ $('.apex-tier-count').val(0); $('.apex-tier-slider').val(0); updateTierTotals(); $('#apex-tier-preview-box').html('صفر شد.'); $('#apex-tier-details-box').hide(); });

  // preview
  $(document).on('click','#apex-preview-tier',function(e){
    e.preventDefault(); const $btn=$(this); $btn.prop('disabled',true).text('در حال پیش‌نمایش…');
    const counts=collectTierCounts(); if(Object.keys(counts).length===0){ alert('حداقل یک دسته را پر کن'); $btn.prop('disabled',false).text('👁️ پیش‌نمایش'); return; }
    $.post(Apex.ajax_url,{action:'apex_preview_tier',_ajax_nonce:getNonce(),counts:counts,category:$('#apex-filter-cat').val(),post_type:$('#apex-filter-pt').val(),artist_aware:$('#apex-artist-aware').is(':checked')?1:0},function(res){
      if(res.success){
        let html='<div class="apex-alert success">تعداد درخواستی: '+res.data.total_requested+' | موجود: '+res.data.total_available+' | باقی‌مانده: '+res.data.remaining+' | قفل پیشنهادی: '+(res.data.total_requested*2+15)+'ثانیه</div>';
        html+='<table class="apex-table"><tr><th>دسته</th><th>تعداد</th><th>میانگین بازدید</th><th>میانگین رأی</th><th>مجموع بازدید</th></tr>';
        Object.keys(res.data.summary).forEach(k=>{ const s=res.data.summary[k]; html+='<tr><td>'+s.icon+' '+s.label+'</td><td>'+s.count+'</td><td>'+s.avg_views.toLocaleString('fa-IR')+'</td><td>'+s.avg_rating+' ('+s.avg_casts+') '+'</td><td>'+s.total_views.toLocaleString('fa-IR')+'</td></tr>'; });
        html+='</table>';
        html+='<div style="margin-top:10px;max-height:220px;overflow:auto;"><strong>نمونه‌ها (3 اول هر دسته):</strong><ul>';
        Object.keys(res.data.preview).forEach(k=>{ const arr=res.data.preview[k]; const t=res.data.tiers[k]; html+='<li><strong>'+t.icon+' '+t.label+':</strong> '; html+= arr.slice(0,3).map(it=> it.post_title+' → '+it.views.toLocaleString('fa-IR')+' بازدید، '+it.avg+'⭐ ('+it.casts+')').join(' | '); html+='</li>'; });
        html+='</ul></div>';
        $('#apex-tier-preview-box').html(html);
        $('#apex-preview-hint').text('پیش‌نمایش آماده — اگر اوکیه «اعمال خودکار» بزن (اسنپ‌شات ذخیره می‌شود)');
      } else { $('#apex-tier-preview-box').html('<div class="apex-alert error">'+(res.data.message||'خطا')+'</div>'); }
    }).fail(function(xhr){ $('#apex-tier-preview-box').html('<div class="apex-alert error">'+(xhr.responseJSON?JSON.stringify(xhr.responseJSON.data):'خطا')+'</div>'); }).always(()=> $btn.prop('disabled',false).text('👁️ پیش‌نمایش'));
  });

  // apply with result table
  $(document).on('click','#apex-apply-tier',function(e){
    e.preventDefault(); if(!confirm('این عملیات تمام آثار انتخاب‌شده را بازنویسی می‌کند (با تاریخچه + اسنپ‌شات و قابل بازگردانی). ادامه؟')) return;
    const $btn=$(this); $btn.prop('disabled',true).text('در حال اعمال… (قفل هوشمند)');
    const counts=collectTierCounts();
    $.post(Apex.ajax_url,{action:'apex_apply_tier',_ajax_nonce:getNonce(),counts:counts,category:$('#apex-filter-cat').val(),post_type:$('#apex-filter-pt').val(),auto_enable:$('#apex-auto-enable').is(':checked')?1:0,artist_aware:$('#apex-artist-aware').is(':checked')?1:0},function(res){
      if(res.success){
        let html='<div class="apex-alert success">✅ '+res.data.applied+' آهنگ با موفقیت توزیع شد. شناسه: '+res.data.execution_id+' — TTL قفل: '+(res.data.ttl_used||'?')+'ثانیه — تراکنش یکپارچه فعال<br>خطا: '+(res.data.errors && res.data.errors.length? res.data.errors.join('<br>'):'ندارد')+'</div>';
        html+='<div style="margin-top:8px;"><small>اسنپ‌شات ذخیره شد — برای بازگشت دکمه Undo زیر را بزن یا به تاریخچه برو.</small></div>';
        $('#apex-tier-result').html(html);
        // render details table
        if(res.data.details && res.data.details.length){
          const $box=$('#apex-tier-details-box'); $box.show();
          const $tbody=$('#apex-result-table tbody'); $tbody.empty();
          res.data.details.forEach(d=>{
            const tierLabel = $('.apex-tier-card[data-tier="'+d.tier+'"] h3').text() || d.tier;
            $tbody.append('<tr><td><a href="'+d.edit_url+'" target="_blank">'+ (d.post_title||d.post_id) +'</a> <small>#'+d.post_id+'</small></td><td>'+tierLabel+'</td><td>'+d.old_views.toLocaleString('fa-IR')+' → <strong>'+d.new_views.toLocaleString('fa-IR')+'</strong></td><td>'+d.old_casts+' → <strong>'+d.new_casts+'</strong></td><td>'+d.old_avg+' → <strong>'+d.new_avg+'</strong></td></tr>');
          });
          // also append to preview box summary
        }
        setTimeout(()=>location.reload(),2500);
      }
      else $('#apex-tier-result').html('<div class="apex-alert error">'+(res.data.message||JSON.stringify(res.data))+'</div>');
    }).fail(function(xhr){ $('#apex-tier-result').html('<div class="apex-alert error">'+(xhr.responseJSON?JSON.stringify(xhr.responseJSON.data):'خطا')+'</div>'); }).always(()=> $btn.prop('disabled',false).text('✅ اعمال خودکار (با ثبت تاریخچه + اسنپ‌شات)'));
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

  // AI save + test
  $(document).on('click','#apex-ai-save',function(e){
    e.preventDefault(); const data={action:'apex_ai_save',_ajax_nonce:getNonce(),api_key:$('#apex-ai-key').val(), model:$('#apex-ai-model').val(), endpoint:$('#apex-ai-endpoint').val(), provider:'gapgpt'};
    const $btn=$(this); $btn.text('ذخیره…');
    $.post(Apex.ajax_url,data,function(res){ if(res.success) alert('ذخیره شد (رمزنگاری با AUTH_KEY)'); else alert(res.data.message); }).always(()=> $btn.text('💾 ذخیره کلید'));
  });
  $(document).on('click','#apex-ai-test',function(e){
    e.preventDefault(); const $btn=$(this); $btn.prop('disabled',true).text('تست…'); $('#apex-ai-test-result').text('در حال تست…').css('color','#6b7280');
    $.post(Apex.ajax_url,{action:'apex_test_ai',_ajax_nonce:getNonce()},function(res){
      if(res.success){ $('#apex-ai-test-result').text('✅ '+res.data.message).css('color','#065f46'); }
      else { $('#apex-ai-test-result').text('❌ '+(res.data.message||'خطا')).css('color','#991b1b'); }
    }).fail(function(xhr){ const d=xhr.responseJSON&&xhr.responseJSON.data?xhr.responseJSON.data:{}; $('#apex-ai-test-result').text('❌ '+(d.message||'خطا')).css('color','#991b1b'); }).always(()=> $btn.prop('disabled',false).text('🔌 تست اتصال'));
  });

  // Artist
  $(document).on('input','.apex-artist-weight',function(){ const tid=$(this).data('tid'); $('.apex-artist-slider[data-tid="'+tid+'"]').val($(this).val()); });
  $(document).on('input','.apex-artist-slider',function(){ const tid=$(this).data('tid'); $('.apex-artist-weight[data-tid="'+tid+'"]').val($(this).val()); });
  $(document).on('click','#apex-artist-save',function(e){
    e.preventDefault(); const weights={}; $('.apex-artist-weight').each(function(){ weights[$(this).data('tid')]= $(this).val(); });
    const $btn=$(this); $btn.text('ذخیره…');
    $.post(Apex.ajax_url,{action:'apex_save_artist_weights',_ajax_nonce:getNonce(),weights:weights},function(res){ if(res.success) $('#apex-artist-result').html('<div class="apex-alert success">ذخیره شد ('+res.data.count+' آرتیست)</div>'); else $('#apex-artist-result').html('<div class="apex-alert error">'+res.data.message+'</div>'); }).always(()=> $btn.text('💾 ذخیره وزن‌ها'));
  });
  $(document).on('click','#apex-artist-ai',function(e){
    e.preventDefault(); const $btn=$(this); $btn.prop('disabled',true).text('AI در حال امتیازدهی…');
    $.post(Apex.ajax_url,{action:'apex_auto_artist_ai',_ajax_nonce:getNonce()},function(res){
      if(res.success){ Object.keys(res.data.weights||res.weights||{}).forEach(tid=>{ const w=res.data.weights[tid]||res.weights[tid]; $('.apex-artist-weight[data-tid="'+tid+'"]').val(w); $('.apex-artist-slider[data-tid="'+tid+'"]').val(w); }); $('#apex-artist-result').html('<div class="apex-alert success">✅ AI امتیاز داد: '+Object.keys(res.data.weights||{}).length+' آرتیست</div>'); }
      else $('#apex-artist-result').html('<div class="apex-alert error">'+res.data.message+'</div>');
    }).fail(function(xhr){ $('#apex-artist-result').html('<div class="apex-alert error">'+(xhr.responseJSON?JSON.stringify(xhr.responseJSON.data):'خطا')+'</div>'); }).always(()=> $btn.prop('disabled',false).text('🤖 امتیازدهی خودکار با AI (GapGPT)'));
  });
  $(document).on('click','#apex-artist-ir',function(e){
    e.preventDefault(); const $btn=$(this); $btn.prop('disabled',true).text('در حال خواندن از سایت ایرانی…');
    $.post(Apex.ajax_url,{action:'apex_auto_artist_ir',_ajax_nonce:getNonce()},function(res){
      if(res.success){ Object.keys(res.data.weights||{}).forEach(tid=>{ const w=res.data.weights[tid]; $('.apex-artist-weight[data-tid="'+tid+'"]').val(w); $('.apex-artist-slider[data-tid="'+tid+'"]').val(w); }); $('#apex-artist-result').html('<div class="apex-alert success">🌐 از سایت ایرانی امتیاز گرفته شد: '+Object.keys(res.data.weights||{}).length+' — با کش 24 ساعته + cooldown خطا</div>'); }
      else $('#apex-artist-result').html('<div class="apex-alert error">'+res.data.message+'</div>');
    }).fail(function(xhr){ $('#apex-artist-result').html('<div class="apex-alert error">'+(xhr.responseJSON?JSON.stringify(xhr.responseJSON.data):'خطا')+'</div>'); }).always(()=> $btn.prop('disabled',false).text('🌐 امتیاز از سایت ایرانی (رادیوجوان/نکس1موزیک)'));
  });
  // taxonomy selector
  $(document).on('click','#apex-taxonomy-save',function(e){
    e.preventDefault(); const tax=$('#apex-artist-taxonomy').val(); const $btn=$(this); $btn.text('ذخیره…');
    $.post(Apex.ajax_url,{action:'apex_save_artist_taxonomy',_ajax_nonce:getNonce(),taxonomy:tax},function(res){
      if(res.success){ $('#apex-taxonomy-result').text('✅ '+res.data.message+' — '+res.data.taxonomy).css('color','#065f46'); setTimeout(()=>location.reload(),900); }
      else $('#apex-taxonomy-result').text('❌ '+res.data.message).css('color','#991b1b');
    }).fail(function(xhr){ $('#apex-taxonomy-result').text('❌ '+(xhr.responseJSON?JSON.stringify(xhr.responseJSON.data):'خطا')).css('color','#991b1b'); }).always(()=> $btn.text('ذخیره تاکسونومی'));
  });
  // evergreen
  $(document).on('click','#apex-evergreen-save',function(e){
    e.preventDefault(); const $btn=$(this); $btn.prop('disabled',true).text('ذخیره…');
    const enabled=$('#apex-evergreen-enabled').is(':checked')?1:0, rate=parseFloat($('#apex-evergreen-rate').val())||0.04, max=parseInt($('#apex-evergreen-max').val())||1500000;
    $.post(Apex.ajax_url,{action:'apex_save_evergreen',_ajax_nonce:getNonce(),enabled:enabled,rate_percent:rate,max_views:max},function(res){
      if(res.success) $('#apex-evergreen-result').text('✅ ذخیره شد — '+(res.data.settings.enabled?'فعال':'غیرفعال')+' — نرخ '+res.data.settings.rate_percent+'%').css('color','#065f46');
      else $('#apex-evergreen-result').text('❌ '+(res.data.message||'خطا')).css('color','#991b1b');
    }).fail(function(xhr){ $('#apex-evergreen-result').text('❌ '+(xhr.responseJSON?JSON.stringify(xhr.responseJSON.data):'خطا')).css('color','#991b1b'); }).always(()=> $btn.prop('disabled',false).text('💾 ذخیره Evergreen'));
  });
  // undo last distribution
  $(document).on('click','#apex-undo-tier',function(e){
    e.preventDefault(); if(!confirm('بازگردانی آخرین توزیع؟ تمام آهنگ‌های توزیع‌شده به مقادیر قبلی برمی‌گردند (قابل بازگردانی نیست).')) return;
    const exec=$(this).data('exec'); const $btn=$(this); $btn.prop('disabled',true).text('در حال بازگردانی…');
    $.post(Apex.ajax_url,{action:'apex_undo_tier',_ajax_nonce:getNonce(),execution_id:exec},function(res){
      if(res.success){ $('#apex-undo-result').text('✅ بازگردانی شد: '+res.data.restored+' آهنگ').css('color','#065f46'); setTimeout(()=>location.reload(),900); }
      else $('#apex-undo-result').text('❌ '+res.data.message).css('color','#991b1b');
    }).fail(function(xhr){ $('#apex-undo-result').text('❌ '+(xhr.responseJSON?JSON.stringify(xhr.responseJSON.data):'خطا')).css('color','#991b1b'); }).always(()=> $btn.prop('disabled',false).text('↩️ بازگردانی آخرین توزیع (Undo)'));
  });

  // ======== Analytics — export/import ==========
  function downloadFile(content, filename, mime){
    const blob = new Blob([content], {type: mime||'text/plain;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href=url; a.download=filename; document.body.appendChild(a); a.click(); setTimeout(()=>{document.body.removeChild(a); URL.revokeObjectURL(url);},0);
  }
  $(document).on('click','#apex-export-artist',function(){ const $b=$(this); $b.text('در حال تهیه…'); $.post(Apex.ajax_url,{action:'apex_export_artist_csv',_ajax_nonce:getNonce()},res=>{ if(res.success) downloadFile(res.data.csv, res.data.filename, 'text/csv'); else alert(res.data.message||'خطا'); }).always(()=> $b.text('📥 وزن آرتیست CSV')); });
  $(document).on('click','#apex-export-history',function(){ const $b=$(this); $b.text('در حال تهیه…'); $.post(Apex.ajax_url,{action:'apex_export_history_csv',_ajax_nonce:getNonce(),limit:1000},res=>{ if(res.success) downloadFile(res.data.csv, res.data.filename, 'text/csv'); else alert(res.data.message); }).always(()=> $b.text('📥 تاریخچه CSV')); });
  $(document).on('click','#apex-export-tiers',function(){ const $b=$(this); $b.text('…'); $.post(Apex.ajax_url,{action:'apex_export_tiers_json',_ajax_nonce:getNonce()},res=>{ if(res.success) downloadFile(res.data.json, res.data.filename, 'application/json'); }).always(()=> $b.text('📥 تیره JSON')); });
  $(document).on('click','#apex-export-history-csv',function(){ $('#apex-export-history').trigger('click'); });
  $(document).on('click','#apex-import-artist-btn',function(){ const csv=$('#apex-import-artist-csv').val(); if(!csv) {alert('CSV را وارد کنید'); return;} const $b=$(this); $b.text('وارد کردن…'); $.post(Apex.ajax_url,{action:'apex_import_artist_csv',_ajax_nonce:getNonce(),csv:csv},res=>{ if(res.success) $('#apex-import-artist-res').html('<span style="color:#065f46;">✅ '+res.data.imported+' آرتیست وارد شد</span>'); else $('#apex-import-artist-res').html('<span style="color:#991b1b;">'+(res.data.message||'خطا')+'</span>'); }).always(()=> $b.text('📤 وارد کردن')); });
  $(document).on('click','#apex-import-tiers-btn',function(){ const j=$('#apex-import-tiers-json').val(); if(!j){alert('JSON را وارد کنید');return;} const $b=$(this); $b.text('…'); $.post(Apex.ajax_url,{action:'apex_import_tiers_json',_ajax_nonce:getNonce(),json:j},res=>{ if(res.success) $('#apex-import-tiers-res').html('<span style="color:#065f46;">✅ '+res.data.count+' تیره وارد شد</span>'); else $('#apex-import-tiers-res').html('<span style="color:#991b1b;">'+(res.data.message||'خطا')+'</span>'); }).always(()=> $b.text('📤 وارد کردن تیره')); });

  // sync total_posts from PHP
  if(Apex.total_posts) updateTierTotals();
});
