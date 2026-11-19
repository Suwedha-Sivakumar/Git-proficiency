<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/layout.php';
require_login();
ensure_plan();

$q = trim($_GET['q'] ?? '');
$cat = trim($_GET['cat'] ?? '');
$sort = $_GET['sort'] ?? 'name';
if (!in_array($cat, CATEGORIES, true)) {
    $cat = '';
}

$sql = ATTR_SELECT . ' WHERE 1=1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (a.Name LIKE :q OR a.Description LIKE :q2 OR a.Location LIKE :q3)';
    $params['q'] = $params['q2'] = $params['q3'] = '%' . $q . '%';
}
if ($cat !== '') {
    $sql .= ' AND a.Category LIKE :cat';
    $params['cat'] = '%' . $cat . '%';
}
$orders = [
    'name'     => 'a.Name ASC',
    'distance' => '(a.DistanceKm IS NULL), a.DistanceKm ASC',
    'rating'   => '(AvgRating IS NULL), AvgRating DESC, a.Name ASC',
    'time'     => 'a.VisitMinutes ASC',
];
if (!isset($orders[$sort])) {
    $sort = 'name';
}
$sql .= ' ORDER BY ' . $orders[$sort];

$st = $pdo->prepare($sql);
$st->execute($params);
$places = $st->fetchAll();
$inPlan = $_SESSION['plan']['ids'];

app_header('Browse attractions', 'browse');
?>
<form method="get" class="toolbar">
  <input type="search" name="q" placeholder="Search by name, area or description…" value="<?= e($q) ?>">
  <select name="cat" data-autosubmit>
    <option value="">All categories</option>
    <?php foreach (CATEGORIES as $c): ?>
      <option value="<?= e($c) ?>" <?= $cat === $c ? 'selected' : '' ?>><?= e($c) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="sort" data-autosubmit>
    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Sort: A to Z</option>
    <option value="distance" <?= $sort === 'distance' ? 'selected' : '' ?>>Sort: Nearest first</option>
    <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Sort: Best rated</option>
    <option value="time" <?= $sort === 'time' ? 'selected' : '' ?>>Sort: Shortest visit</option>
  </select>
  <button class="btn teal" type="submit">Search</button>
</form>

<p class="muted"><?= count($places) ?> place<?= count($places) === 1 ? '' : 's' ?> found
  <?php if ($q !== '' || $cat !== ''): ?> · <a href="<?= e(url('browse.php')) ?>">clear filters</a><?php endif; ?></p>

<?php if (!$places): ?>
  <div class="card empty"><div class="big">🔍</div><p>No attractions match your search. Try a different word or clear the filters.</p></div>
<?php endif; ?>

<div class="cards">
  <?php foreach ($places as $a): ?>
    <?php $already = in_array((int)$a['AttractionID'], $inPlan, true); ?>
    <div class="acard">
      <?php if ($a['Photo']): ?>
        <div class="pic" style="background-image:url('<?= e(photo_src($a['Photo'])) ?>')"></div>
      <?php else: ?>
        <div class="pic"><?= cat_icon($a['Category']) ?></div>
      <?php endif; ?>
      <div class="body">
        <h3><a href="<?= e(url('attraction.php?id=' . $a['AttractionID'])) ?>"><?= e($a['Name']) ?></a></h3>
        <div>
          <?php foreach (explode('/', $a['Category']) as $c): ?><span class="tag"><?= e($c) ?></span><?php endforeach; ?>
        </div>
        <?php if ($a['AvgRating'] !== null): ?>
          <div><span class="stars"><?= e(stars((float)$a['AvgRating'])) ?></span> <small><?= e($a['AvgRating']) ?> (<?= (int)$a['ReviewCount'] ?>)</small></div>
        <?php endif; ?>
        <div class="meta"><span>📏 <?= e($a['DistanceKm']) ?> km</span><span>⏱️ <?= e(fmt_duration((int)$a['VisitMinutes'])) ?></span><span>🎟️ <?= e(fee_text($a['Fees'])) ?></span></div>
        <div class="actions">
          <a class="btn small teal" href="<?= e(url('attraction.php?id=' . $a['AttractionID'])) ?>">View details</a>
          <?php if ($already): ?>
            <button class="btn small" disabled>✓ In itinerary</button>
          <?php else: ?>
            <button class="btn small js-add" data-id="<?= (int)$a['AttractionID'] ?>">+ Itinerary</button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php app_footer();
