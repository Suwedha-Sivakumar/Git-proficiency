<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/layout.php';
require_login();
ensure_plan();
$uid = (int)$_SESSION['uid'];

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare(ATTR_SELECT . ' WHERE a.AttractionID = ?');
$st->execute([$id]);
$a = $st->fetch();
if (!$a) {
    flash('That attraction was not found.', 'err');
    redirect('browse.php');
}

// ---- Submit a review (saved as Pending until an admin approves it) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review') {
    csrf_check();
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    if ($rating < 1 || $rating > 5) {
        flash('Please choose a rating from 1 to 5.', 'err');
    } elseif ($comment === '' || mb_strlen($comment) > 1000) {
        flash('Please write a short comment (up to 1000 characters).', 'err');
    } else {
        $ins = $pdo->prepare("INSERT INTO REVIEW (UserID, AttractionID, Rating, Comment, Status) VALUES (?, ?, ?, ?, 'Pending')");
        $ins->execute([$uid, $id, $rating, $comment]);
        flash('Thank you! Your review was submitted and will appear after an administrator approves it.');
    }
    redirect('attraction.php?id=' . $id);
}

$photos = $pdo->prepare('SELECT * FROM PHOTO WHERE AttractionID = ? ORDER BY SortOrder, PhotoID');
$photos->execute([$id]);
$photos = $photos->fetchAll();

$reviews = $pdo->prepare("SELECT r.*, u.FullName, u.Username FROM REVIEW r JOIN USER u ON u.UserID = r.UserID
                          WHERE r.AttractionID = ? AND r.Status = 'Approved' ORDER BY r.CreatedAt DESC");
$reviews->execute([$id]);
$reviews = $reviews->fetchAll();

$mine = $pdo->prepare("SELECT * FROM REVIEW WHERE AttractionID = ? AND UserID = ? AND Status = 'Pending'");
$mine->execute([$id, $uid]);
$myPending = $mine->fetchAll();

$inPlan = in_array($id, $_SESSION['plan']['ids'], true);
$query = $a['MapQuery'] ?: ($a['Name'] . ', Sri Lanka');
$embed = 'https://www.google.com/maps?q=' . rawurlencode($query) . '&output=embed';
$openMaps = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($query);
$directions = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($query);
$mainPhoto = $photos ? photo_src($photos[0]['Path']) : '';

app_header($a['Name'], 'browse');
?>
<p><a href="<?= e(url('browse.php')) ?>">← Back to browse</a></p>

<div class="split">
  <div>
    <?php if ($mainPhoto): ?>
      <div class="hero-pic" id="heroPic" style="background-image:url('<?= e($mainPhoto) ?>')"></div>
      <?php if (count($photos) > 1): ?>
        <div class="thumbs">
          <?php foreach ($photos as $i => $ph): ?>
            <img src="<?= e(photo_src($ph['Path'])) ?>" data-full="<?= e(photo_src($ph['Path'])) ?>" alt="Photo <?= $i + 1 ?>" class="<?= $i === 0 ? 'on' : '' ?>">
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="hero-pic" id="heroPic"><?= cat_icon($a['Category']) ?></div>
      <p class="hint">No photos yet. <?= is_admin() ? 'Add some from the admin edit page.' : 'An administrator can add photos.' ?></p>
    <?php endif; ?>
  </div>

  <div>
    <div><?php foreach (explode('/', $a['Category']) as $c): ?><span class="tag"><?= e($c) ?></span><?php endforeach; ?></div>
    <h2 style="font-size:26px;margin-top:8px"><?= e($a['Name']) ?></h2>
    <p class="muted"><?= e($a['Location']) ?> · <?= e($a['DistanceKm']) ?> km from Piliyandala</p>
    <?php if ($a['AvgRating'] !== null): ?>
      <p><span class="stars"><?= e(stars((float)$a['AvgRating'])) ?></span> <b><?= e($a['AvgRating']) ?></b> <small>(<?= (int)$a['ReviewCount'] ?> review<?= (int)$a['ReviewCount'] === 1 ? '' : 's' ?>)</small></p>
    <?php else: ?>
      <p class="muted">No reviews yet.</p>
    <?php endif; ?>
    <p><?= nl2br(e($a['Description'])) ?></p>
    <div class="row mt">
      <?php if ($inPlan): ?>
        <a class="btn" href="<?= e(url('itinerary.php')) ?>">✓ In itinerary - view plan</a>
      <?php else: ?>
        <button class="btn js-add" data-id="<?= $id ?>">+ Add to my itinerary</button>
      <?php endif; ?>
      <a class="btn ghost" target="_blank" rel="noopener" href="<?= e($directions) ?>">🚗 Get directions</a>
      <?php if (is_admin()): ?><a class="btn ghost" href="<?= e(url('admin/attraction_form.php?id=' . $id)) ?>">✏️ Edit</a><?php endif; ?>
    </div>
  </div>
</div>

<div class="facts">
  <div class="fact"><small>Opening hours</small><b><?= e($a['OpeningHours'] ?: 'Check locally') ?></b></div>
  <div class="fact"><small>Entrance</small><b><?= e(fee_text($a['Fees'])) ?></b></div>
  <div class="fact"><small>Typical visit</small><b><?= e(fmt_duration((int)$a['VisitMinutes'])) ?></b></div>
  <div class="fact"><small>Best time</small><b><?= e($a['BestTime'] ?: 'Any time') ?></b></div>
</div>

<div class="grid2 mb">
  <div class="card">
    <h3>Plan your visit</h3>
    <div class="info-list">
      <div class="info-item"><small>Parking</small><?= e($a['Parking'] ?: 'Not listed') ?></div>
      <div class="info-item"><small>Facilities</small><?= e($a['Facilities'] ?: 'Not listed') ?></div>
      <div class="info-item"><small>Accessibility</small><?= e($a['Accessibility'] ?: 'Not listed') ?></div>
    </div>
  </div>
  <div class="card">
    <h3>Safety and rules</h3>
    <div class="info-list">
      <div class="info-item"><small>Safety</small><?= e($a['SafetyInfo'] ?: 'Not listed') ?></div>
      <div class="info-item"><small>Rules and etiquette</small><?= e($a['Rules'] ?: 'Not listed') ?></div>
    </div>
  </div>
</div>

<div class="split mb">
  <div class="card">
    <div class="row between"><h3>Location on the map</h3>
      <a target="_blank" rel="noopener" href="<?= e($openMaps) ?>">Open in Google Maps ↗</a></div>
    <iframe class="map-frame" src="<?= e($embed) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map of <?= e($a['Name']) ?>" allowfullscreen></iframe>
  </div>
  <div class="card">
    <h3>Weather here today</h3>
    <?php if ($a['Latitude'] !== null && $a['Longitude'] !== null): ?>
      <div class="weather" data-weather data-lat="<?= e($a['Latitude']) ?>" data-lng="<?= e($a['Longitude']) ?>"><span class="big">⏳</span><div><small>Loading weather…</small></div></div>
    <?php else: ?>
      <p class="muted">Weather is shown once the place has map coordinates.</p>
    <?php endif; ?>
    <p class="hint mt">Weather comes from the free Open-Meteo service and needs an internet connection.</p>
  </div>
</div>

<div class="card">
  <h3>Visitor reviews</h3>
  <?php if (!$reviews): ?><p class="muted">No approved reviews yet. Be the first to share your experience.</p><?php endif; ?>
  <?php foreach ($reviews as $r): ?>
    <div class="review">
      <b><?= e($r['FullName']) ?></b> <small>@<?= e($r['Username']) ?> · <?= e(date('d M Y', strtotime($r['CreatedAt']))) ?></small><br>
      <span class="stars"><?= e(stars((float)$r['Rating'])) ?></span>
      <div><?= nl2br(e($r['Comment'])) ?></div>
    </div>
  <?php endforeach; ?>

  <h3 class="mt">Write a review</h3>
  <?php if ($myPending): ?><div class="flash ok">You have <?= count($myPending) ?> review(s) waiting for approval.</div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="review">
    <div class="grid2">
      <div class="field"><label for="rating">Rating</label>
        <select id="rating" name="rating" required>
          <option value="">Choose…</option>
          <option value="5">★★★★★ Excellent</option><option value="4">★★★★☆ Good</option>
          <option value="3">★★★☆☆ Average</option><option value="2">★★☆☆☆ Poor</option><option value="1">★☆☆☆☆ Terrible</option>
        </select></div>
    </div>
    <div class="field"><label for="comment">Your comment</label><textarea id="comment" name="comment" maxlength="1000" required></textarea></div>
    <button class="btn teal" type="submit">Submit review</button>
    <span class="hint">Reviews are checked by an administrator before they are published.</span>
  </form>
</div>
<?php app_footer();
