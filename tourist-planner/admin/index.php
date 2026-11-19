<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$count = fn(string $sql) => (int)$pdo->query($sql)->fetchColumn();
$stats = [
    ['📍', $count('SELECT COUNT(*) FROM ATTRACTION'), 'Attractions'],
    ['👥', $count('SELECT COUNT(*) FROM USER'), 'Registered users'],
    ['💬', $count("SELECT COUNT(*) FROM REVIEW WHERE Status = 'Pending'"), 'Reviews awaiting approval'],
    ['🗓️', $count('SELECT COUNT(*) FROM ITINERARY'), 'Saved itineraries'],
];
$noPhoto = $pdo->query('SELECT a.AttractionID, a.Name FROM ATTRACTION a
                        WHERE NOT EXISTS (SELECT 1 FROM PHOTO p WHERE p.AttractionID = a.AttractionID) ORDER BY a.Name')->fetchAll();
$pending = $pdo->query("SELECT r.*, u.Username, a.Name AS AttractionName FROM REVIEW r
                        JOIN USER u ON u.UserID = r.UserID JOIN ATTRACTION a ON a.AttractionID = r.AttractionID
                        WHERE r.Status = 'Pending' ORDER BY r.CreatedAt LIMIT 5")->fetchAll();

app_header('Admin overview', 'admin');
?>
<div class="grid4 mb">
  <?php foreach ($stats as $s): ?>
    <div class="stat"><b><?= $s[1] ?></b><span><?= $s[0] ?> <?= e($s[2]) ?></span></div>
  <?php endforeach; ?>
</div>

<div class="split">
  <div class="card">
    <div class="row between"><h3>Reviews waiting for approval</h3><a href="<?= e(url('admin/reviews.php')) ?>">Open moderation →</a></div>
    <?php if (!$pending): ?><p class="muted">Nothing to moderate right now.</p><?php endif; ?>
    <?php foreach ($pending as $r): ?>
      <div class="review"><b><?= e($r['AttractionName']) ?></b> · <small>@<?= e($r['Username']) ?></small><br>
        <span class="stars"><?= e(stars((float)$r['Rating'])) ?></span> <?= e(mb_strimwidth($r['Comment'], 0, 120, '…')) ?></div>
    <?php endforeach; ?>
  </div>
  <div class="card">
    <h3>Quick actions</h3>
    <div class="row">
      <a class="btn" href="<?= e(url('admin/attraction_form.php')) ?>">+ Add attraction</a>
      <a class="btn ghost" href="<?= e(url('admin/attractions.php')) ?>">Manage attractions</a>
    </div>
    <h3 class="mt">Attractions without photos (<?= count($noPhoto) ?>)</h3>
    <?php if (!$noPhoto): ?><p class="muted">Every attraction has at least one photo. 🎉</p><?php endif; ?>
    <?php foreach ($noPhoto as $n): ?>
      <div class="plan-row"><span><?= e($n['Name']) ?></span>
        <a class="btn small ghost" href="<?= e(url('admin/attraction_form.php?id=' . $n['AttractionID'])) ?>">Add photos</a></div>
    <?php endforeach; ?>
  </div>
</div>
<?php app_footer();
