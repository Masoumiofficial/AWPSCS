jQuery(function($){
  const Apex = window.ApexOmega || {};
  function getNonce(){ return Apex.nonce || ''; }

  // ذخیره دستی از متاباکس
  $(document).on('click', '#apex-save-manual', function(e){
    e.preventDefault();
    const $btn = $(this);
    const postId = $('#post_ID').val() || $('input[name="post_id"]').val();
    if(!postId){ alert('شناسه نوشته یافت نشد.'); return; }
    const data = {
      action: 'apex_save_manual',
      _ajax_nonce: getNonce(),
      post_id: postId,
      mode: $('#_apex_mode').val() || 'manual',
      views: $('#apex_views').val(),
      casts: $('#apex_casts').val(),
      avg: $('#apex_avg').val(),
      confirm_volatility: $('#apex_confirm_volatility').is(':checked') ? 1 : 0,
      confirm_inconsistency: $('#apex_confirm_inconsistency').is(':checked') ? 1 : 0,
    };
    $btn.prop('disabled', true).text('در حال ذخیره…');
    $.post(Apex.ajax_url, data, function(res){
      if(res.success){
        $('#apex-manual-result').html('<div class="apex-alert success">ذخیره شد. شناسه اجرا: '+ (res.data.execution_id||'') +'</div>');
        // نمایش نتیجه جزئی
        if(res.data.results){
          console.log('APEX manual results', res.data.results);
        }
      } else {
        let msg = res.data && res.data.message ? res.data.message : (res.data && res.data.results ? JSON.stringify(res.data.results) : 'خطا');
        if(res.data && res.data.requires_confirm){
          msg = (res.data.warnings||[]).join('<br>') + '<br>لطفاً تأیید را علامت بزنید.';
        }
        $('#apex-manual-result').html('<div class="apex-alert error">'+msg+'</div>');
      }
    }).fail(function(xhr){
      let msg = xhr.responseJSON && xhr.responseJSON.data ? JSON.stringify(xhr.responseJSON.data) : 'خطای ارتباط';
      $('#apex-manual-result').html('<div class="apex-alert error">'+msg+'</div>');
    }).always(function(){ $btn.prop('disabled', false).text('ذخیره دستی'); });
  });

  // پیش‌نمایش زمان‌بندی
  $(document).on('click', '#apex-preview-schedule', function(e){
    e.preventDefault();
    const postId = $('#post_ID').val() || $('input[name="post_id"]').val() || $('#apex_schedule_post_id').val();
    if(!postId){ alert('شناسه نوشته یافت نشد.'); return; }
    const field = $('#apex_sched_field').val();
    const target = $('#apex_sched_target').val();
    const start_gmt = $('#apex_sched_start').val(); // input datetime-local is local, we convert to GMT
    const end_gmt = $('#apex_sched_end').val();
    const interval = $('#apex_sched_interval').val();
    if(!target){ alert('مقدار هدف را وارد کنید.'); return; }

    // تبدیل به GMT ساده: فرض می‌کنیم ورودی به وقت محلی است، به GMT تبدیل
    function toGmt(localStr){
      if(!localStr) return '';
      const d = new Date(localStr);
      return d.toISOString().slice(0,19).replace('T',' ');
    }

    const data = {
      action: 'apex_preview_schedule',
      _ajax_nonce: getNonce(),
      post_id: postId,
      field_type: field,
      target_value: target,
      start_at_gmt: toGmt(start_gmt),
      end_at_gmt: toGmt(end_gmt),
      interval_seconds: interval
    };
    $('#apex-preview-box').html('<div class="apex-alert success">در حال محاسبه…</div>');
    $.post(Apex.ajax_url, data, function(res){
      if(res.success){
        const d = res.data;
        let html = '<div><strong>مقدار فعلی:</strong> '+d.current+' | <strong>هدف:</strong> '+d.target+' | <strong>کل گام‌ها:</strong> '+d.total_steps+'</div>';
        html += '<table class="apex-table" style="margin-top:8px"><tr><th>#</th><th>مقدار</th><th>زمان (محلی)</th></tr>';
        d.steps.forEach(s=>{
          html += '<tr><td>'+s.step+'</td><td>'+s.value+'</td><td>'+s.at+'</td></tr>';
        });
        html += '</table>';
        $('#apex-preview-box').html(html);
      } else {
        $('#apex-preview-box').html('<div class="apex-alert error">'+ (res.data.message||'خطا') +'</div>');
      }
    });
  });

  // ایجاد برنامه
  $(document).on('click', '#apex-create-schedule', function(e){
    e.preventDefault();
    const postId = $('#post_ID').val() || $('input[name="post_id"]').val() || $('#apex_schedule_post_id').val();
    const field = $('#apex_sched_field').val();
    const target = $('#apex_sched_target').val();
    const start_gmt = $('#apex_sched_start').val();
    const end_gmt = $('#apex_sched_end').val();
    const interval = $('#apex_sched_interval').val();
    function toGmt(localStr){
      if(!localStr) return '';
      const d = new Date(localStr);
      return d.toISOString().slice(0,19).replace('T',' ');
    }
    const data = {
      action: 'apex_create_schedule',
      _ajax_nonce: getNonce(),
      post_id: postId,
      field_type: field,
      target_value: target,
      start_at_gmt: toGmt(start_gmt),
      end_at_gmt: toGmt(end_gmt),
      interval_seconds: interval,
      min_allowed: $('#apex_sched_min').val(),
      max_allowed: $('#apex_sched_max').val(),
      max_step: $('#apex_sched_max_step').val(),
      max_percent_step: $('#apex_sched_max_percent').val(),
      require_confirm_large_drop: $('#apex_req_confirm').is(':checked')?1:0,
      confirm_large_drop: $('#apex_confirm_large_drop').is(':checked')?1:0,
      confirm_volatility: $('#apex_sched_confirm_vol').is(':checked')?1:0,
    };
    const $btn=$(this);
    $btn.prop('disabled',true).text('در حال ایجاد…');
    $.post(Apex.ajax_url, data, function(res){
      if(res.success){
        $('#apex-schedule-result').html('<div class="apex-alert success">برنامه با شناسه '+res.data.id+' ایجاد شد.</div>');
        setTimeout(()=>location.reload(), 800);
      } else {
        let msg = res.data.message || (res.data.errors? res.data.errors.join('<br>') : '') || (res.data.warnings? res.data.warnings.join('<br>'):'خطا');
        if(res.data.requires_confirm){
          msg = (res.data.warnings||[]).join('<br>') + '<br>' + (res.data.message||'نیاز به تأیید');
        }
        $('#apex-schedule-result').html('<div class="apex-alert error">'+msg+'</div>');
      }
    }).fail(function(xhr){
      const d = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : {};
      let msg = d.message || d.errors || 'خطا';
      if(Array.isArray(msg)) msg=msg.join('<br>');
      if(d.warnings) msg = d.warnings.join('<br>') + '<br>' + msg;
      $('#apex-schedule-result').html('<div class="apex-alert error">'+msg+'</div>');
    }).always(()=> $btn.prop('disabled',false).text('ایجاد برنامه'));
  });

  // کنترل برنامه
  $(document).on('click', '.apex-pause', function(){
    const id=$(this).data('id');
    $.post(Apex.ajax_url, {action:'apex_pause_schedule', _ajax_nonce:getNonce(), schedule_id:id}, function(res){
      if(res.success) location.reload();
      else alert(res.data.message||'خطا');
    });
  });
  $(document).on('click', '.apex-resume', function(){
    const id=$(this).data('id');
    $.post(Apex.ajax_url, {action:'apex_resume_schedule', _ajax_nonce:getNonce(), schedule_id:id}, function(res){
      if(res.success) location.reload();
      else alert(res.data.message||'خطا');
    });
  });
  $(document).on('click', '.apex-cancel', function(){
    if(!confirm('لغو برنامه؟')) return;
    const id=$(this).data('id');
    $.post(Apex.ajax_url, {action:'apex_cancel_schedule', _ajax_nonce:getNonce(), schedule_id:id}, function(res){
      if(res.success) location.reload();
      else alert(res.data.message||'خطا');
    });
  });
  $(document).on('click', '.apex-rollback', function(){
    if(!confirm('بازگردانی به مقدار قبلی؟')) return;
    const hid=$(this).data('id');
    $.post(Apex.ajax_url, {action:'apex_rollback', _ajax_nonce:getNonce(), history_id:hid}, function(res){
      if(res.success) { alert('بازگردانی شد.'); location.reload(); }
      else alert(res.data.message||'خطا');
    });
  });

  // Bulk
  $(document).on('click', '#apex-bulk-apply', function(e){
    e.preventDefault();
    if(!confirm(Apex.i18n.confirm_bulk)) return;
    const ids = $('#apex_bulk_ids').val();
    if(!ids){ alert('شناسه‌ای انتخاب نشده'); return; }
    const data={
      action:'apex_bulk_apply',
      _ajax_nonce:getNonce(),
      post_ids: ids,
      field_type: $('#apex_bulk_field').val(),
      value: $('#apex_bulk_value').val(),
      mode: $('#apex_bulk_mode').val()
    };
    const $btn=$(this);
    $btn.prop('disabled',true).text('در حال اعمال…');
    $.post(Apex.ajax_url, data, function(res){
      if(res.success){
        $('#apex-bulk-result').html('<div class="apex-alert success">موفق: '+res.data.success_count+' | خطا: '+(res.data.errors||[]).join('<br>')+'</div>');
      } else {
        $('#apex-bulk-result').html('<div class="apex-alert error">'+ (res.data.message||'خطا')+'</div>');
      }
    }).always(()=> $btn.prop('disabled',false).text('اعمال گروهی'));
  });

  // نمایش فرم زمان‌بندی در متاباکس
  $(document).on('change', '#_apex_mode', function(){
    if($(this).val()==='scheduled') $('#apex-schedule-panel').show();
    else $('#apex-schedule-panel').hide();
  });
});
