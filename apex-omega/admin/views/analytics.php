<?php if (!defined('ABSPATH')) exit;
$summary = \ApexOmega\Core\Analytics::summary();
$tierDist = \ApexOmega\Core\Analytics::tier_distribution();
$hist = \ApexOmega\Core\Analytics::views_histogram();
$timeline = \ApexOmega\Core\Analytics::history_timeline(14);
$tops = \ApexOmega\Core\Analytics::top_posts(10);
$tiers = \ApexOmega\Core\TierManager::get_tiers();
?>
<div class="wrap apex-wrap">
  <div class="apex-hero">
    <div>
      <h1 style="margin:0;font-size:24px;">📊 آنالیتیکس حرفه‌ای — APEX Ω 1.3.0</h1>
      <p style="margin:6px 0 0;">نمای کلی توزیع، هیستوگرام بازدید، روند 14روزه و برترین آهنگ‌ها — با صادرات CSV</p>
    </div>
    <div class="apex-hero-stats">
      <div><span class="big"><?php echo esc_html(number_format_i18n($summary['total_posts'])); ?></span><small>کل آهنگ</small></div>
      <div><span class="big"><?php echo esc_html(number_format_i18n($summary['avg_views'])); ?></span><small>میانگین بازدید</small></div>
      <div><span class="big"><?php echo esc_html(number_format_i18n($summary['max_views'])); ?></span><small>بیشترین بازدید</small></div>
    </div>
  </div>

  <div class="apex-cards" style="margin-top:16px;">
    <div class="apex-card apx-info"><h3>توزیع تیره‌ها</h3><canvas id="apex-chart-tier" height="200"></canvas></div>
    <div class="apex-card apx-success"><h3>هیستوگرام بازدید</h3><canvas id="apex-chart-hist" height="200"></canvas></div>
  </div>
  <div class="apex-card" style="margin-top:14px;">
    <h3>روند 14 روز اخیر (تعداد تغییرات)</h3>
    <canvas id="apex-chart-timeline" height="120"></canvas>
  </div>

  <div class="apex-metabox-grid" style="margin-top:14px;">
    <div class="apex-card">
      <h3>🏆 برترین آهنگ‌ها بر اساس بازدید</h3>
      <table class="apex-table"><thead><tr><th>#</th><th>عنوان</th><th>بازدید</th><th>تیره</th><th>امتیاز</th></tr></thead><tbody>
        <?php foreach ($tops as $i=>$t): ?>
          <tr><td><?php echo $i+1; ?></td><td><a href="<?php echo esc_url(get_edit_post_link($t['ID'])); ?>"><?php echo esc_html($t['post_title']); ?></a></td><td><?php echo esc_html(number_format_i18n($t['views'])); ?></td><td><span class="apex-badge"><?php echo esc_html($t['tier']?:'—'); ?></span></td><td><?php echo esc_html($t['avg'].' ('.$t['casts'].')'); ?></td></tr>
        <?php endforeach; ?>
        <?php if (empty($tops)): ?><tr><td colspan="5">داده‌ای نیست</td></tr><?php endif; ?>
      </tbody></table>
    </div>
    <div class="apex-card">
      <h3>📥 صادرات / 📤 واردات</h3>
      <p><small>پشتیبان‌گیری از وزن آرتیست‌ها و تاریخچه — CSV/JSON</small></p>
      <div class="apex-actions">
        <button id="apex-export-artist" class="button button-apex secondary">📥 وزن آرتیست CSV</button>
        <button id="apex-export-history" class="button button-apex secondary">📥 تاریخچه CSV</button>
        <button id="apex-export-tiers" class="button button-apex secondary">📥 تیره JSON</button>
      </div>
      <hr class="apex-hr">
      <div class="apex-field"><label>واردات وزن آرتیست (CSV جای‌گذاری)</label><textarea id="apex-import-artist-csv" rows="4" placeholder="term_id,name,slug,count,weight,tier_hint ..."></textarea><button id="apex-import-artist-btn" class="button" style="margin-top:6px;">📤 وارد کردن</button> <span id="apex-import-artist-res"></span></div>
      <div class="apex-field" style="margin-top:10px;"><label>واردات تیره (JSON)</label><textarea id="apex-import-tiers-json" rows="3" placeholder='[{"id":"tier_1",...}]'></textarea><button id="apex-import-tiers-btn" class="button" style="margin-top:6px;">📤 وارد کردن تیره</button> <span id="apex-import-tiers-res"></span></div>
      <div id="apex-analytics-result" style="margin-top:10px;"></div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script>
  // داده‌های PHP به JS
  const apexTierLabels = <?php echo wp_json_encode(array_map(fn($t)=>$t['icon'].' '.$t['label'], $tiers), JSON_UNESCAPED_UNICODE); ?>;
  const apexTierCounts = <?php echo wp_json_encode(array_map(fn($t)=> $tierDist[$t['id']] ?? 0, $tiers)); ?>;
  const apexTierColors = <?php echo wp_json_encode(array_column($tiers,'color')); ?>;
  const apexHistLabels = <?php echo wp_json_encode(array_keys($hist), JSON_UNESCAPED_UNICODE); ?>;
  const apexHistCounts = <?php echo wp_json_encode(array_values($hist)); ?>;
  const apexTimelineLabels = <?php echo wp_json_encode(array_keys($timeline)); ?>;
  const apexTimelineCounts = <?php echo wp_json_encode(array_values($timeline)); ?>;

  document.addEventListener('DOMContentLoaded', function(){
    if (typeof Chart !== 'undefined') {
      new Chart(document.getElementById('apex-chart-tier'), {
        type: 'doughnut',
        data: { labels: apexTierLabels, datasets: [{ data: apexTierCounts, backgroundColor: apexTierColors }] },
        options: { plugins:{ legend:{ position:'bottom' } } }
      });
      new Chart(document.getElementById('apex-chart-hist'), {
        type: 'bar',
        data: { labels: apexHistLabels, datasets: [{ label:'تعداد آهنگ', data: apexHistCounts, backgroundColor:'#7c3aed' }] },
        options: { scales:{ y:{ beginAtZero:true } } }
      });
      new Chart(document.getElementById('apex-chart-timeline'), {
        type: 'line',
        data: { labels: apexTimelineLabels, datasets: [{ label:'تغییرات', data: apexTimelineCounts, borderColor:'#06b6d4', backgroundColor:'rgba(6,182,214,.15)', fill:true, tension:.3 }] },
        options: { scales:{ y:{ beginAtZero:true } } }
      });
    }
    // export handlers handled in admin.js
  });
  </script>
</div>
