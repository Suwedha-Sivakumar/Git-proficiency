<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/layout.php';
require_login();
ensure_plan();
$uid = (int)$_SESSION['uid'];

// =====================================================================
//  Actions (every button on this page posts here, then we redirect back)
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $ids = array_map('intval', $_SESSION['plan']['ids']);

    switch ($action) {
        case 'remove':
            $ids = array_values(array_diff($ids, [(int)($_POST['id'] ?? 0)]));
            break;

        case 'up':
        case 'down':
            $pos = array_search((int)($_POST['id'] ?? 0), $ids, true);
            if ($pos !== false) {
                $swap = $action === 'up' ? $pos - 1 : $pos + 1;
                if (isset($ids[$swap])) {
                    [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
                }
            }
            break;

        case 'optimize':
            $ids = optimize_order($pdo, $ids);
            flash('Stops re-ordered to keep travel short, starting from Piliyandala.');
            break;

        case 'settings':
            $start = $_POST['start'] ?? '09:00';
            if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start)) {
                $_SESSION['plan']['start'] = $start;
            }
            $hours = (float)($_POST['hours'] ?? 8);
            $_SESSION['plan']['hours'] = max(1.0, min(14.0, $hours));
            $_SESSION['plan']['title'] = mb_substr(trim($_POST['title'] ?? ''), 0, 80);
            break;

        case 'clear':
            $ids = [];
            flash('Itinerary cleared.');
            break;

        case 'save':
            if (!$ids) {
                flash('Add at least one place before saving.', 'err');
                break;
            }
            $title = mb_substr(trim($_POST['title'] ?? ''), 0, 80);
            if ($title === '') {
                $title = 'Day trip ' . date('d M Y');
            }
            $pdo->beginTransaction();
            $ins = $pdo->prepare('INSERT INTO ITINERARY (UserID, Title, StartTime, HoursAvailable) VALUES (?, ?, ?, ?)');
            $ins->execute([$uid, $title, $_SESSION['plan']['start'] . ':00', $_SESSION['plan']['hours']]);
            $itId = (int)$pdo->lastInsertId();
            $link = $pdo->prepare('INSERT INTO ITINERARY_ATTRACTION (ItineraryID, AttractionID, VisitOrder) VALUES (?, ?, ?)');
            foreach ($ids as $i => $aid) {
                $link->execute([$itId, $aid, $i + 1]);
            }
            $pdo->commit();
            $_SESSION['plan']['title'] = $title;
            flash('Itinerary "' . $title . '" saved.');
            break;

        case 'load':
            $st = $pdo->prepare('SELECT * FROM ITINERARY WHERE ItineraryID = ? AND UserID = ?');
            $st->execute([(int)($_POST['plan_id'] ?? 0), $uid]);
            $plan = $st->fetch();
            if ($plan) {
                $st = $pdo->prepare('SELECT AttractionID FROM ITINERARY_ATTRACTION WHERE ItineraryID = ? ORDER BY VisitOrder');
                $st->execute([$plan['ItineraryID']]);
                $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
                $_SESSION['plan']['start'] = substr($plan['StartTime'], 0, 5);
                $_SESSION['plan']['hours'] = (float)$plan['HoursAvailable'];
                $_SESSION['plan']['title'] = $plan['Title'];
                flash('Loaded "' . $plan['Title'] . '".');
            }
            break;

        case 'delete_plan':
            $st = $pdo->prepare('DELETE FROM ITINERARY WHERE ItineraryID = ? AND UserID = ?');
            $st->execute([(int)($_POST['plan_id'] ?? 0), $uid]);
            flash('Saved itinerary deleted.');
            break;
    }

    $_SESSION['plan']['ids'] = $ids;
    redirect('itinerary.php');
}

// =====================================================================
//  Build the page
// =====================================================================
$plan = $_SESSION['plan'];
$tl = build_timeline($pdo, array_map('intval', $plan['ids']), $plan['start'], (float)$plan['hours']);
$stops = $tl['stops'];

$st = $pdo->prepare('SELECT i.*, (SELECT COUNT(*) FROM ITINERARY_ATTRACTION x WHERE x.ItineraryID = i.ItineraryID) AS Stops
                     FROM ITINERARY i WHERE i.UserID = ? ORDER BY i.DateCreated DESC');
$st->execute([$uid]);
$saved = $st->fetchAll();

// Google Maps route links built from the place names
$names = array_map(fn($s) => $s['MapQuery'] ?: ($s['Name'] . ', Sri Lanka'), $stops);
$routeEmbed = '';
$routeOpen = '';
if (count($names) === 1) {
    $routeEmbed = 'https://www.google.com/maps?q=' . rawurlencode($names[0]) . '&output=embed';
    $routeOpen = 'https://www.google.com/maps/dir/?api=1&origin=Piliyandala%2C%20Sri%20Lanka&destination=' . rawurlencode($names[0]);
} elseif (count($names) > 1) {
    $dest = array_pop($names);
    $origin = 'Piliyandala, Sri Lanka';
    $routeEmbed = 'https://www.google.com/maps?saddr=' . rawurlencode($origin) . '&daddr='
        . implode('+to:', array_map('rawurlencode', array_merge($names, [$dest]))) . '&output=embed';
    $routeOpen = 'https://www.google.com/maps/dir/?api=1&origin=' . rawurlencode($origin)
        . '&destination=' . rawurlencode($dest)
        . ($names ? '&waypoints=' . rawurlencode(implode('|', $names)) : '')
        . '&travelmode=driving';
}

app_header('My itinerary', 'itinerary');
?>
<div class="steps">
  <div class="step"><b>1. Pick places</b>Add attractions from Browse or from a place's page.</div>
  <div class="step"><b>2. Set your day</b>Choose when you start and how many hours you have. The app adds visit time and estimated travel time and tells you if the day is too long.</div>
  <div class="step"><b>3. Order and save</b>Use "Smart order" to shorten travelling, then save the plan to open it again later.</div>
</div>

<div class="split">
  <div>
    <form method="post" class="card mb">
      <?= csrf_field() ?><input type="hidden" name="action" value="settings">
      <div class="grid3">
        <div class="field"><label for="start">Start time</label><input type="time" id="start" name="start" value="<?= e($plan['start']) ?>"></div>
        <div class="field"><label for="hours">Hours available</label><input type="number" id="hours" name="hours" min="1" max="14" step="0.5" value="<?= e($plan['hours']) ?>"></div>
        <div class="field"><label for="title">Plan name</label><input type="text" id="title" name="title" maxlength="80" placeholder="e.g. Temples and sunset" value="<?= e($plan['title']) ?>"></div>
      </div>
      <button class="btn teal small" type="submit">Update plan</button>
      <span class="hint">Trip starts from Piliyandala.</span>
    </form>

    <?php if (!$stops): ?>
      <div class="card empty">
        <div class="big">🗓️</div>
        <h3>Your itinerary is empty</h3>
        <p>Add the places you would like to visit today and this page will build a timed route for you.</p>
        <a class="btn" href="<?= e(url('browse.php')) ?>">🧭 Browse attractions</a>
      </div>
    <?php else: ?>
      <div class="summary">
        <div class="stat"><b><?= e(fmt_clock($tl['startMin'])) ?></b><span>Leave Piliyandala</span></div>
        <div class="stat"><b><?= e(fmt_clock($tl['endMin'])) ?></b><span>Day finishes</span></div>
        <div class="stat"><b><?= e(fmt_duration($tl['totalVisit'])) ?></b><span>Visiting time</span></div>
        <div class="stat"><b><?= e(fmt_duration($tl['totalTravel'])) ?></b><span>Estimated travel</span></div>
      </div>

      <?php if ($tl['over']): ?>
        <div class="flash err">⚠️ This plan runs about <b><?= e(fmt_duration($tl['overBy'])) ?></b> past your available <?= e($plan['hours']) ?> hours. Remove a stop or increase the hours.</div>
      <?php else: ?>
        <div class="flash ok">✓ This plan fits your day with about <?= e(fmt_duration($tl['deadline'] - $tl['endMin'])) ?> to spare.</div>
      <?php endif; ?>

      <?php foreach ($stops as $i => $s): ?>
        <div class="travel">🚗 About <?= e(fmt_duration((int)$s['travel'])) ?><?= $s['roadKm'] !== null ? ' · ' . e(number_format($s['roadKm'], 1)) . ' km' : '' ?>
          <?= $i === 0 ? 'from Piliyandala' : 'from ' . e($stops[$i - 1]['Name']) ?></div>
        <div class="stop">
          <div class="num"><?= $i + 1 ?></div>
          <div>
            <h3><a href="<?= e(url('attraction.php?id=' . $s['AttractionID'])) ?>"><?= e($s['Name']) ?></a></h3>
            <div class="when"><?= e(fmt_clock($s['arrive'])) ?> → <?= e(fmt_clock($s['depart'])) ?> <small>(<?= e(fmt_duration((int)$s['VisitMinutes'])) ?>)</small></div>
            <small>🎟️ <?= e(fee_text($s['Fees'])) ?> · 🕒 <?= e($s['OpeningHours'] ?: 'Hours not listed') ?></small>
            <?php foreach ($s['warnings'] as $w): ?><div class="warn">⚠️ <?= e($w) ?></div><?php endforeach; ?>
          </div>
          <div class="btns">
            <?php foreach ([['up', '↑', $i > 0], ['down', '↓', $i < count($stops) - 1]] as $b): ?>
              <?php if ($b[2]): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $b[0] ?>"><input type="hidden" name="id" value="<?= (int)$s['AttractionID'] ?>">
                  <button class="btn small ghost" type="submit" title="Move <?= $b[0] ?>"><?= $b[1] ?></button></form>
              <?php endif; ?>
            <?php endforeach; ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= (int)$s['AttractionID'] ?>">
              <button class="btn small danger" type="submit">Remove</button></form>
          </div>
        </div>
      <?php endforeach; ?>

      <div class="row mt">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="optimize"><button class="btn teal" type="submit">✨ Smart order</button></form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="title" value="<?= e($plan['title']) ?>"><button class="btn" type="submit">💾 Save itinerary</button></form>
        <form method="post" data-confirm="Remove all places from this itinerary?"><?= csrf_field() ?><input type="hidden" name="action" value="clear"><button class="btn ghost" type="submit">Clear all</button></form>
      </div>
      <p class="hint">Travel times are estimates (straight-line distance × 1.35, about 25 km/h city speed). Use the map for live road conditions.</p>
    <?php endif; ?>
  </div>

  <div>
    <div class="card mb">
      <div class="row between"><h3>Route map</h3>
        <?php if ($routeOpen): ?><a target="_blank" rel="noopener" href="<?= e($routeOpen) ?>">Open in Google Maps ↗</a><?php endif; ?></div>
      <?php if ($routeEmbed): ?>
        <iframe class="map-frame" src="<?= e($routeEmbed) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Route map"></iframe>
        <p class="hint">If the route does not appear here, use "Open in Google Maps" for the full route.</p>
      <?php else: ?>
        <p class="muted">Add places to see your route on the map.</p>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3>Saved itineraries</h3>
      <?php if (!$saved): ?><p class="muted">Nothing saved yet.</p><?php endif; ?>
      <?php foreach ($saved as $p): ?>
        <div class="plan-row">
          <div><b><?= e($p['Title']) ?></b><br><small><?= (int)$p['Stops'] ?> stops · <?= e(date('d M Y', strtotime($p['DateCreated']))) ?></small></div>
          <div class="row">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="load"><input type="hidden" name="plan_id" value="<?= (int)$p['ItineraryID'] ?>"><button class="btn small ghost" type="submit">Open</button></form>
            <form method="post" data-confirm="Delete this saved itinerary?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_plan"><input type="hidden" name="plan_id" value="<?= (int)$p['ItineraryID'] ?>"><button class="btn small danger" type="submit">✕</button></form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php app_footer();
