<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/layout.php';
require_login();
ensure_plan();
$uid = (int)$_SESSION['uid'];

$totalPlaces = (int)$pdo->query('SELECT COUNT(*) FROM ATTRACTION')->fetchColumn();

$st = $pdo->prepare('SELECT COUNT(*) FROM ITINERARY WHERE UserID = ?');
$st->execute([$uid]);
$savedPlans = (int)$st->fetchColumn();

$st = $pdo->prepare('SELECT COUNT(*) FROM REVIEW WHERE UserID = ?');
$st->execute([$uid]);
$myReviews = (int)$st->fetchColumn();

// Best-rated places first; places without ratings follow alphabetically
$top = $pdo->query(ATTR_SELECT . ' ORDER BY (AvgRating IS NULL), AvgRating DESC, a.Name LIMIT 4')->fetchAll();

$st = $pdo->prepare('SELECT i.*, (SELECT COUNT(*) FROM ITINERARY_ATTRACTION x WHERE x.ItineraryID = i.ItineraryID) AS Stops
                     FROM ITINERARY i WHERE i.UserID = ? ORDER BY i.DateCreated DESC LIMIT 3');
$st->execute([$uid]);
$plans = $st->fetchAll();

$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

app_header('Dashboard', 'dashboard');
?>
<div class="card mb">
  <div class="split">
    <div>
      <h2><?= e($greeting) ?>, <?= e(explode(' ', $_SESSION['fullname'])[0]) ?> 👋</h2>
      <p class="muted">Ready to plan a day out? Browse the attractions, add the ones you like to your itinerary and the app will build a timed route from Piliyandala.</p>
      <div class="row mt">
        <a class="btn" href="<?= e(url('browse.php')) ?>">🧭 Browse attractions</a>
        <a class="btn ghost" href="<?= e(url('itinerary.php')) ?>">🗓️ Open my itinerary</a>
      </div>
    </div>
    <div>
      <small>Today's weather in Colombo</small>
      <div class="weather" data-weather data-lat="6.9271" data-lng="79.8612"><span class="big">⏳</span><div><small>Loading weather…</small></div></div>
    </div>
  </div>
</div>

<div class="grid4 mb">
  <div class="stat"><b><?= $totalPlaces ?></b><span>Attractions to explore</span></div>
  <div class="stat"><b id="dashPlan"><?= plan_count() ?></b><span>Places in today's itinerary</span></div>
  <div class="stat"><b><?= $savedPlans ?></b><span>Saved itineraries</span></div>
  <div class="stat"><b><?= $myReviews ?></b><span>Reviews you wrote</span></div>
</div>

<div class="row between"><h2>Top picks</h2><a href="<?= e(url('browse.php')) ?>">See all →</a></div>
<div class="cards mb">
  <?php foreach ($top as $a): ?>
    <div class="acard">
      <?php if ($a['Photo']): ?>
        <div class="pic" style="background-image:url('<?= e(photo_src($a['Photo'])) ?>')"></div>
      <?php else: ?>
        <div class="pic"><?= cat_icon($a['Category']) ?></div>
      <?php endif; ?>
      <div class="body">
        <h3><a href="<?= e(url('attraction.php?id=' . $a['AttractionID'])) ?>"><?= e($a['Name']) ?></a></h3>
        <div><span class="tag"><?= e(explode('/', $a['Category'])[0]) ?></span>
          <?php if ($a['AvgRating'] !== null): ?><span class="stars"><?= e(stars((float)$a['AvgRating'])) ?></span> <small><?= e($a['AvgRating']) ?></small><?php endif; ?></div>
        <div class="meta"><span>📏 <?= e($a['DistanceKm']) ?> km</span><span>⏱️ <?= e(fmt_duration((int)$a['VisitMinutes'])) ?></span></div>
        <div class="actions">
          <a class="btn small teal" href="<?= e(url('attraction.php?id=' . $a['AttractionID'])) ?>">Details</a>
          <button class="btn small js-add" data-id="<?= (int)$a['AttractionID'] ?>">+ Itinerary</button>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <h2>My saved itineraries</h2>
  <?php if (!$plans): ?>
    <p class="muted">You have not saved an itinerary yet. Add a few places and press <b>Save</b> on the itinerary page.</p>
  <?php endif; ?>
  <?php foreach ($plans as $p): ?>
    <div class="plan-row">
      <div><b><?= e($p['Title']) ?></b><br><small><?= (int)$p['Stops'] ?> stops · starts <?= e(fmt_clock(time_to_min($p['StartTime']))) ?> · <?= e(date('d M Y', strtotime($p['DateCreated']))) ?></small></div>
      <form method="post" action="<?= e(url('itinerary.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="load"><input type="hidden" name="plan_id" value="<?= (int)$p['ItineraryID'] ?>">
        <button class="btn small ghost" type="submit">Open</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>
<?php app_footer();
