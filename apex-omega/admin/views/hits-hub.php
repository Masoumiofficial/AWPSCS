<?php
if (!defined('ABSPATH')) exit;
$tax = \ApexOmega\Core\ArtistManager::detect_artist_taxonomy();
$artists = $tax ? \ApexOmega\Core\ArtistManager::get_all_artists($tax, 200) : [];
$weights = get_option(\ApexOmega\Core\ArtistManager::OPTION_WEIGHTS, []);
$hit_ids = \ApexOmega\Core\ArtistManager::get_all_hit_ids();
$hit_set = array_flip($hit_ids);
$selected_hits_count = count($hit_ids);
?>
<div class="wrap apex-omega-wrap" dir="rtl">
<style>
.apex-hits-wrap{max-width:1180px;margin:20px 0;font-family:IRANSans,Tahoma,sans-serif}
.apex-hub-nav{display:flex;gap:8px;margin:10px 0 18px;flex-wrap:wrap}
.apex-hub-nav a{padding:8px 14px;border-radius:999px;text-decoration:none;font-size:13px;border:1px solid #d1d5db;background:#fff;color:#334155}
.apex-hub-nav a.active{background:#7c3aed;color:#fff;border-color:#7c3aed}
.apex-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:16px;margin-bottom:14px;box-shadow:0 6px 18px rgba(15,23,42,.06)}
.apex-card h3{margin:0 0 8px;font-size:15px}
.apex-hint{color:#64748b;font-size:12px;margin:6px 0}
.apex-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:800px){.apex-grid{grid-template-columns:1fr}}
.apex-btn{padding:8px 12px;border-radius:10px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;font-size:13px}
.apex-btn-primary{background:#7c3aed;color:#fff;border-color:#7c3aed}
.apex-btn-danger{background:#fff;color:#dc2626;border-color:#fecaca}
.apex-table{width:100%;border-collapse:collapse;font-size:13px}
.apex-table th,.apex-table td{padding:8px 10px;border-bottom:1px solid #eef2f7;text-align:right}
.apex-table th{background:#f8fafc}
.apex-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px}
.apex-badge-hit{background:#fef3c7;color:#92400e;border:1px solid #fcd34d}
.apex-badge-regular{background:#f1f5f9;color:#334155}
</style>

<div class="apex-hits-wrap">
  <h1 style="font-size:22px;margin:0">آثار منتخب و معروف‌ترین‌ها ⭐ <span style="font-weight:400;color:#64748b;font-size:13px">— هیت ۱۰M / معمولی ۲-۴M</span></h1>
  <p class="apex-hint">هنرمند با وزن ۹۵: فقط آثاری که به‌عنوان «هیت» علامت‌گذاری کنی ۹-۱۰ میلیون بازدید می‌گیرند (میانگین ۴.۹-۵.۰، رای ۱۱-۱۹ هزار). سایر آثار همان هنرمند با همان وزن ۹۵ اما معمولی، ۲-۴ میلیون می‌گیرند — فاصله عقلانی حفظ می‌شود.</p>

  <div class="apex-hub-nav">
    <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-artists')); ?>">🎤 هنرمندان</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-automation')); ?>">⚙️ اتوماسیون</a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-weight-sync')); ?>">⚡ همگام‌سازی</a>
    <a class="active" href="<?php echo esc_url(admin_url('admin.php?page=apex-omega-hits')); ?>">⭐ آثار منتخب</a>
  </div>

  <?php if (!$tax): ?>
    <div class="apex-card" style="border-color:#fecaca;background:#fef2f2"><strong>تاکسونومی هنرمند یافت نشد.</strong> ابتدا در «هنرمندان» یک تاکسونومی را ذخیره کن.</div>
  <?php endif; ?>

  <div class="apex-grid">
    <div class="apex-card">
      <h3>وضعیت فعلی — خلاصه هیت</h3>
      <p class="apex-hint">تعداد کل هیت‌های ثبت‌شده: <strong><?php echo esc_html($selected_hits_count); ?></strong> — اینها در همگام‌سازی بعدی ۹-۱۰M می‌گیرند (ضریب ۰.۸۸+). بقیه با همان وزن، ضریب ۰.۱۵-۰.۵۰ می‌گیرند.</p>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
        <button type="button" class="apex-btn apex-btn-danger" id="apex-clear-hits">پاک کردن همه هیت‌ها</button>
        <button type="button" class="apex-btn" id="apex-refresh-hits" onclick="location.reload()">↻ بروزرسانی</button>
      </div>
      <?php if ($hit_ids): ?>
        <div style="margin-top:12px;max-height:180px;overflow:auto;border:1px solid #e5e7eb;border-radius:10px;padding:8px">
          <?php foreach ($hit_ids as $pid): $p=get_post($pid); if(!$p) continue; $w = \ApexOmega\Core\ArtistManager::get_weight_for_post($pid); ?>
            <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px dashed #f1f5f9"><span><?php echo esc_html($p->post_title); ?> <small style="color:#64748b">(ID <?php echo $pid; ?> · وزن <?php echo $w; ?>)</small></span><button type="button" class="apex-btn" style="padding:2px 8px;font-size:11px" data-remove-hit="<?php echo esc_attr($pid); ?>">حذف</button></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="apex-card">
      <h3>هوش مصنوعی — پیشنهاد هیت</h3>
      <p class="apex-hint">AI با توجه به نام آهنگ و هنرمند، معروف‌ترین‌ها را پیشنهاد می‌کند. هنرمند را انتخاب کن و بزن «پیشنهاد».</p>
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <select id="apex-ai-artist" style="min-width:180px;padding:6px;border-radius:8px;border:1px solid #e5e7eb">
          <option value="">— همه هنرمندان (۳۰ پست اخیر) —</option>
          <?php foreach ($artists as $a): ?>
            <option value="<?php echo esc_attr($a['term_id']); ?>"><?php echo esc_html($a['name']); ?> (وزن <?php echo esc_html($a['weight']); ?> · <?php echo esc_html($a['count']); ?> آهنگ)</option>
          <?php endforeach; ?>
        </select>
        <button type="button" class="apex-btn apex-btn-primary" id="apex-ai-suggest">✨ پیشنهاد AI (۵ هیت)</button>
      </div>
      <div id="apex-ai-result" style="margin-top:10px"></div>
    </div>
  </div>

  <div class="apex-card">
    <h3>انتخاب دستی — لیست آهنگ‌ها (هر هنرمند جداگانه)</h3>
    <p class="apex-hint">تیک بزن: کدام آهنگ‌ها هیت (۱۰M) هستند. بقیه خودکار معمولی (۲-۴M) می‌مانند. دکمه «ذخیره هیت‌ها» را بزن. جستجو و فیلتر هنرمند دارد.</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0">
      <select id="apex-filter-artist" style="padding:6px;border-radius:8px;border:1px solid #e5e7eb;min-width:180px">
        <option value="">— همه هنرمندان —</option>
        <?php foreach ($artists as $a): ?>
          <option value="<?php echo esc_attr($a['term_id']); ?>"><?php echo esc_html($a['name']); ?></option>
        <?php endforeach; ?>
      </select>
      <input id="apex-search-hit" placeholder="جستجوی عنوان..." style="flex:1;min-width:160px;padding:6px 10px;border-radius:8px;border:1px solid #e5e7eb">
      <button type="button" class="apex-btn apex-btn-primary" id="apex-save-hits">💾 ذخیره هیت‌های انتخاب‌شده</button>
      <label style="display:flex;align-items:center;gap:6px;font-size:13px"><input type="checkbox" id="apex-clear-before"> پاک‌سازی قبلی قبل از ذخیره</label>
    </div>
    <div id="apex-hits-table-wrap" style="max-height:520px;overflow:auto;border:1px solid #e5e7eb;border-radius:12px">
      <table class="apex-table" id="apex-hits-table">
        <thead><tr><th style="width:40px"><input type="checkbox" id="apex-check-all"></th><th>عنوان آهنگ</th><th>هنرمند</th><th>وزن</th><th>وضعیت</th><th>بازدید فعلی</th></tr></thead>
        <tbody id="apex-hits-tbody"><tr><td colspan="6" style="text-align:center;padding:20px;color:#64748b">در حال بارگذاری...</td></tr></tbody>
      </table>
    </div>
    <div style="margin-top:10px;display:flex;gap:8px;align-items:center">
      <span id="apex-hits-selected-count" class="apex-badge" style="background:#ede9fe;color:#5b21b6">۰ انتخاب</span>
      <span class="apex-hint">نکته: وزن ۸۵ با ۸۶ تفاوت بازدید خواهد داشت (حدود ۲۶ هزار) — variance وزن‌محور فعال است.</span>
    </div>
  </div>

  <div class="apex-card" style="background:#f8fafc">
    <h3>چطور کار می‌کند؟</h3>
    <ul style="margin:6px 0 0 18px;font-size:13px;color:#334155;line-height:1.9">
      <li>هر هنرمند وزن دارد (مثلاً ۹۵): همه آهنگ‌هایش بر اساس وزن در tier بندی می‌شوند.</li>
      <li>اما داخل همان tier: اگر تیک «هیت» خورده → بازدید = max* (۰.۸۸ + norm*۰.۱۲) ≈ ۹-۱۰M ، رای ۱۱-۱۹K، میانگین ۴.۸-۵.۰</li>
      <li>اگر معمولی → بازدید = max* (۰.۱۵ + norm*۰.۳۵) ≈ ۲-۴M (برای tier_1 با وزن ۹۵: ~۳.۸M)، رای ~واقعی (≈ بازدید/۵۲۰).</li>
      <li>بنابراین هنرمند وزن ۹۵ همزمان ۱۰M و ۳M دارد — طبیعی و عقلانی.</li>
      <li>یک کلیک «همگام‌سازی» همه را داخل بازه وزنش می‌آورد؛ هیت‌ها اتومات ۱۰M.</li>
    </ul>
  </div>
</div>

<script>
jQuery(function($){
  const nonce = (window.ApexOmega && ApexOmega.nonce) || '<?php echo esc_js(wp_create_nonce('apex_omega_nonce')); ?>';
  const ajaxUrl = (window.ApexOmega && ApexOmega.ajax_url) || ajaxurl;
  let allRows = [];
  function loadHitsTable(){
    const termId = $('#apex-filter-artist').val();
    const s = $('#apex-search-hit').val().trim();
    // لود از طریق AJAX: لیست پست‌های هنرمند — برای سرعت، از REST یا مستقیم WP_Query در همین صفحه می‌گیریم
    // ساده: یک درخواست به سرور برای لیست 200 پست
    $('#apex-hits-tbody').html('<tr><td colspan="6" style="text-align:center;padding:18px">در حال دریافت...</td></tr>');
    $.post(ajaxUrl, {action:'apex_preview_weight_sync', _ajax_nonce: nonce, category:'all', post_type:'post', limit: 500}, function(res){
      // preview_weight_sync بر اساس وزن گروه‌بندی می‌کند اما ما همه آهنگ‌ها را می‌خواهیم
      // فعلاً از طریق یک endpoint ساده: لیست پست‌ها را از طریق artists-hub می‌گیریم — fallback: از طریق AJAX خودمان
      // برای سادگی: مستقیم از WordPress REST نمی‌گیریم — یک درخواست جدید به سرور برای لیست پست‌ها با فیلتر هنرمند
    });
    // به جای preview، مستقیماً از یک ajax سفارشی که نداریم استفاده نکنیم — پس از طریق درخواست به سرور برای لیست پست‌ها
    // ما یک درخواست به admin-ajax برای گرفتن پست‌های هنرمند می‌سازیم (از طریق ArtistManager لیست هنرمندان را داریم، اما پست‌ها را از JS می‌گیریم)
    // برای الان: از یک درخواست ساده به /wp-admin/admin-ajax.php با action جدید نیست — پس از طریق fetch به همین صفحه با پارامتر term_id، پست‌ها را از PHP بگیریم
    // راه ساده: یک درخواست POST به همین هاب برای گرفتن JSON پست‌ها — ما آن را اینجا با PHP inline می‌سازیم
    $.ajax({
      url: ajaxUrl,
      method: 'POST',
      data: {action:'apex_save_artist_weights', _ajax_nonce: nonce, weights: JSON.stringify({})}, // dummy to test nonce, not needed
      complete: function(){
        // fallback: لود پست‌ها از طریق WordPress API مستقیم — از طریق یک درخواست به wp-json
        fetch('<?php echo esc_js(rest_url('wp/v2/posts?per_page=100&status=publish')); ?>', {headers:{'X-WP-Nonce':'<?php echo esc_js(wp_create_nonce('wp_rest')); ?>'}})
          .then(r=>r.json())
          .then(posts=>{
            if(!Array.isArray(posts)){ $('#apex-hits-tbody').html('<tr><td colspan="6">خطا در دریافت پست‌ها</td></tr>'); return; }
            // map
            const hitSet = new Set([<?php echo implode(',', array_map('intval',$hit_ids)); ?>]);
            allRows = posts.map(p=>({id:p.id, title: p.title.rendered.replace(/<[^>]*>/g,''), artist: (p._embedded ? '' : ''), is_hit: hitSet.has(p.id)}));
            // چون WP REST embedded artist ندارد، نام هنرمند را از PHP می‌گیریم: یک جدول mapping بسازیم
            renderTable();
          }).catch(()=>$('#apex-hits-tbody').html('<tr><td colspan="6">خطا شبکه</td></tr>'));
      }
    });
    // اما چون embedded نداریم، یک mapping PHP برای پست→هنرمند بسازیم
  }

  // mapping post_id -> artist name via PHP
  const postArtistMap = <?php
    $map=[];
    if($tax){
        global $wpdb;
        $rows=$wpdb->get_results($wpdb->prepare("SELECT tr.object_id, t.name FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id INNER JOIN {$wpdb->terms} t ON t.term_id=tt.term_id WHERE tt.taxonomy=%s LIMIT 2000", $tax), ARRAY_A);
        foreach($rows as $r) $map[(int)$r['object_id']] = $r['name'];
    }
    echo wp_json_encode($map, JSON_UNESCAPED_UNICODE);
  ?>;
  const postWeightsMap = <?php
    $wmap=[];
    if($tax){
        global $wpdb;
        $pids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish' LIMIT 1000");
        foreach($pids as $pid) $wmap[$pid] = \ApexOmega\Core\ArtistManager::get_weight_for_post((int)$pid);
    }
    echo wp_json_encode($wmap);
  ?>;
  const postViewsMap = <?php
    $vmap=[];
    if (!empty($pids)) { foreach($pids as $pid) $vmap[$pid]=(int)get_post_meta($pid,'views',true); }
    echo wp_json_encode($vmap);
  ?>;

  function renderTable(){
    const termFilter = $('#apex-filter-artist').val();
    const search = $('#apex-search-hit').val().trim().toLowerCase();
    let filtered = Object.keys(postWeightsMap).map(pid=>({pid:parseInt(pid), weight: postWeightsMap[pid], artist: postArtistMap[pid]||'—', views: postViewsMap[pid]||0, title: ''}));
    // عنوان‌ها را از DOM نداریم — از یک درخواست دیگر بگیریم: از PHP titles
    // برای سادگی: titles را از PHP بگیر
    const titlesMap = <?php
      $tmap=[];
      foreach(($pids ?? []) as $pid){ $p=get_post($pid); if($p) $tmap[$pid]=$p->post_title; }
      echo wp_json_encode($tmap, JSON_UNESCAPED_UNICODE);
    ?>;
    filtered.forEach(r=> r.title = titlesMap[r.pid] || ('ID '+r.pid));
    // فیلتر هنرمند
    if(termFilter){
      const termName = $('#apex-filter-artist option:selected').text().split('(')[0].trim();
      filtered = filtered.filter(r=> r.artist === termName);
    }
    if(search) filtered = filtered.filter(r=> r.title.toLowerCase().includes(search));
    const hitSet = new Set([<?php echo implode(',', array_map('intval',$hit_ids)); ?>]);
    filtered.forEach(r=> r.is_hit = hitSet.has(r.pid));
    allRows = filtered;
    if(!filtered.length){ $('#apex-hits-tbody').html('<tr><td colspan="6" style="text-align:center;color:#64748b">موردی یافت نشد</td></tr>'); updateCount(); return; }
    let html='';
    filtered.slice(0,400).forEach(r=>{
      const checked = r.is_hit ? 'checked' : '';
      const badge = r.is_hit ? '<span class="apex-badge apex-badge-hit">هیت ⭐ ۱۰M</span>' : '<span class="apex-badge apex-badge-regular">معمولی ۲-۴M</span>';
      html+=`<tr>
        <td><input type="checkbox" class="apex-hit-check" value="${r.pid}" ${checked}></td>
        <td>${esc(r.title)} <small style="color:#64748b">#${r.pid}</small></td>
        <td>${esc(r.artist)}</td>
        <td><span style="background:#f1f5f9;padding:2px 8px;border-radius:999px;font-size:12px">${r.weight}</span></td>
        <td>${badge}</td>
        <td>${r.views?Number(r.views).toLocaleString('fa-IR'): '—'}</td>
      </tr>`;
    });
    $('#apex-hits-tbody').html(html);
    updateCount();
  }
  function esc(s){ return $('<div>').text(s).html(); }
  function updateCount(){
    const n = $('.apex-hit-check:checked').length;
    $('#apex-hits-selected-count').text(n.toLocaleString('fa-IR')+' انتخاب');
  }

  // initial
  renderTable();
  $('#apex-filter-artist').on('change', renderTable);
  $('#apex-search-hit').on('input', renderTable);
  $('#apex-check-all').on('change', function(){ $('.apex-hit-check').prop('checked', this.checked); updateCount(); });
  $(document).on('change','.apex-hit-check', updateCount);

  $('#apex-save-hits').on('click', function(){
    const ids = $('.apex-hit-check:checked').map(function(){return parseInt(this.value)}).get();
    if(!ids.length){ alert('حداقل یک آهنگ انتخاب کن'); return; }
    const clear = $('#apex-clear-before').is(':checked');
    const $b=$(this); $b.prop('disabled',true).text('در حال ذخیره...');
    $.post(ajaxUrl, {action:'apex_save_hits', _ajax_nonce: nonce, post_ids: ids, clear_first: clear?1:0}, function(res){
      $b.prop('disabled',false).text('💾 ذخیره هیت‌های انتخاب‌شده');
      if(res.success){ alert('ذخیره شد: '+res.data.saved+' هیت'); location.reload(); } else alert(res.data.message||'خطا');
    }).fail(function(xhr){ $b.prop('disabled',false).text('💾 ذخیره'); alert('خطا: '+(xhr.responseJSON?.data?.message||xhr.statusText)); });
  });

  $('#apex-clear-hits').on('click', function(){
    if(!confirm('همه هیت‌ها پاک شود؟')) return;
    const $b=$(this); $b.prop('disabled',true).text('...');
    $.post(ajaxUrl,{action:'apex_clear_hits',_ajax_nonce:nonce}, function(res){ alert(res.data.message||'پاک شد'); location.reload(); }).fail(function(xhr){ alert('خطا'); $b.prop('disabled',false).text('پاک کردن همه هیت‌ها'); });
  });
  $(document).on('click','[data-remove-hit]', function(){
    const pid=$(this).data('remove-hit');
    $.post(ajaxUrl,{action:'apex_toggle_hit',_ajax_nonce:nonce,post_id:pid}, function(res){ location.reload(); });
  });

  $('#apex-ai-suggest').on('click', function(){
    const termId=$('#apex-ai-artist').val();
    const $b=$(this); $b.prop('disabled',true).text('در حال پیشنهاد...');
    $('#apex-ai-result').html('<span style="color:#64748b">AI در حال بررسی...</span>');
    $.post(ajaxUrl,{action:'apex_suggest_hits',_ajax_nonce:nonce,term_id:termId,limit:20}, function(res){
      $b.prop('disabled',false).text('✨ پیشنهاد AI (۵ هیت)');
      if(res.success){
        const ids=res.data.suggested||[];
        let html='<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:10px"><strong>پیشنهاد AI برای '+esc(res.data.artist||'')+':</strong> '+ids.join(', ')+'<br><small>این‌ها را تیک بزن و ذخیره کن.</small><div style="margin-top:8px"><button type="button" class="apex-btn apex-btn-primary" id="apex-apply-ai">اعمال پیشنهاد به جدول</button></div></div>';
        $('#apex-ai-result').html(html);
        $('#apex-apply-ai').on('click', function(){
          ids.forEach(id=> $(`.apex-hit-check[value="${id}"]`).prop('checked',true));
          updateCount();
          $('html,body').animate({scrollTop: $('#apex-hits-table-wrap').offset().top-80},300);
        });
      } else alert(res.data.message||'خطا');
    }).fail(function(xhr){
      $b.prop('disabled',false).text('✨ پیشنهاد AI (۵ هیت)');
      const msg=xhr.responseJSON?.data?.message||xhr.statusText;
      $('#apex-ai-result').html('<span style="color:#dc2626">'+esc(msg)+'</span>');
    });
  });

});
</script>
</div>
