<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$editing = $id > 0;

// Fields stored in the ATTRACTION table (same order used for saving)
$cols = ['Name', 'Category', 'Location', 'DistanceKm', 'Latitude', 'Longitude', 'MapQuery', 'OpeningHours',
         'OpenTime', 'CloseTime', 'Fees', 'VisitMinutes', 'BestTime', 'Description', 'Parking', 'Facilities',
         'SafetyInfo', 'Accessibility', 'Rules'];
$a = array_fill_keys($cols, '');
$a['VisitMinutes'] = 60;

if ($editing) {
    $st = $pdo->prepare('SELECT * FROM ATTRACTION WHERE AttractionID = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        flash('Attraction not found.', 'err');
        redirect('admin/attractions.php');
    }
    $a = array_merge($a, $row);
    $a['OpenTime'] = $row['OpenTime'] ? substr($row['OpenTime'], 0, 5) : '';
    $a['CloseTime'] = $row['CloseTime'] ? substr($row['CloseTime'], 0, 5) : '';
}

$errors = [];
$selectedCats = $a['Category'] !== '' ? explode('/', $a['Category']) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($cols as $c) {
        if ($c !== 'Category') {
            $a[$c] = trim($_POST[$c] ?? '');
        }
    }
    $selectedCats = array_values(array_intersect(CATEGORIES, (array)($_POST['categories'] ?? [])));
    $a['Category'] = implode('/', $selectedCats);

    // ---- Validation (FR-011) ----
    if ($a['Name'] === '' || mb_strlen($a['Name']) > 150) {
        $errors[] = 'Name is required (up to 150 characters).';
    }
    if (!$selectedCats) {
        $errors[] = 'Choose at least one category.';
    }
    if ($a['DistanceKm'] !== '' && (!is_numeric($a['DistanceKm']) || $a['DistanceKm'] < 0 || $a['DistanceKm'] > 999)) {
        $errors[] = 'Distance must be a number of kilometres.';
    }
    if (($a['Latitude'] === '') !== ($a['Longitude'] === '')) {
        $errors[] = 'Enter both latitude and longitude, or leave both empty.';
    } elseif ($a['Latitude'] !== '' && (!is_numeric($a['Latitude']) || !is_numeric($a['Longitude'])
        || abs($a['Latitude']) > 90 || abs($a['Longitude']) > 180)) {
        $errors[] = 'Latitude must be between -90 and 90 and longitude between -180 and 180.';
    }
    foreach (['OpenTime', 'CloseTime'] as $t) {
        if ($a[$t] !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $a[$t])) {
            $errors[] = 'Opening and closing times must look like 08:30.';
            break;
        }
    }
    if ($a['Fees'] !== '' && (!is_numeric($a['Fees']) || $a['Fees'] < 0)) {
        $errors[] = 'Entrance fee must be a number (0 for free, empty if unknown).';
    }
    if (!ctype_digit((string)$a['VisitMinutes']) || $a['VisitMinutes'] < 5 || $a['VisitMinutes'] > 600) {
        $errors[] = 'Typical visit time must be between 5 and 600 minutes.';
    }

    if (!$errors) {
        $nullable = ['Location', 'DistanceKm', 'Latitude', 'Longitude', 'MapQuery', 'OpeningHours', 'OpenTime', 'CloseTime',
                     'Fees', 'BestTime', 'Description', 'Parking', 'Facilities', 'SafetyInfo', 'Accessibility', 'Rules'];
        $vals = [];
        foreach ($cols as $c) {
            $v = $a[$c];
            if (in_array($c, $nullable, true) && $v === '') {
                $v = null;
            }
            if (($c === 'OpenTime' || $c === 'CloseTime') && $v !== null) {
                $v .= ':00';
            }
            $vals[] = $v;
        }

        if ($editing) {
            $set = implode(', ', array_map(fn($c) => "$c = ?", $cols));
            $pdo->prepare("UPDATE ATTRACTION SET $set WHERE AttractionID = ?")->execute(array_merge($vals, [$id]));
        } else {
            $marks = implode(', ', array_fill(0, count($cols), '?'));
            $pdo->prepare('INSERT INTO ATTRACTION (' . implode(', ', $cols) . ") VALUES ($marks)")->execute($vals);
            $id = (int)$pdo->lastInsertId();
        }

        // ---- Remove ticked photos ----
        foreach ((array)($_POST['delete_photos'] ?? []) as $pid) {
            $q = $pdo->prepare('SELECT Path FROM PHOTO WHERE PhotoID = ? AND AttractionID = ?');
            $q->execute([(int)$pid, $id]);
            $path = $q->fetchColumn();
            if ($path) {
                delete_upload_file($path);
                $pdo->prepare('DELETE FROM PHOTO WHERE PhotoID = ?')->execute([(int)$pid]);
            }
        }

        // ---- Upload new photo files ----
        $next = (int)$pdo->query('SELECT COALESCE(MAX(SortOrder), 0) FROM PHOTO WHERE AttractionID = ' . $id)->fetchColumn();
        $addPhoto = $pdo->prepare('INSERT INTO PHOTO (AttractionID, Path, SortOrder) VALUES (?, ?, ?)');
        $failed = 0;
        $files = $_FILES['photos'] ?? null;
        if ($files && is_array($files['name'])) {
            foreach ($files['name'] as $i => $nm) {
                if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $one = ['tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
                $saved = save_upload($one);
                if ($saved) {
                    $addPhoto->execute([$id, $saved, ++$next]);
                } else {
                    $failed++;
                }
            }
        }
        // ---- Add photos from web links (one per line) ----
        foreach (preg_split('/\R/', (string)($_POST['photo_urls'] ?? '')) as $link) {
            $link = trim($link);
            if ($link === '') {
                continue;
            }
            if (filter_var($link, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $link)) {
                $addPhoto->execute([$id, $link, ++$next]);
            } else {
                $failed++;
            }
        }

        flash('Attraction saved.' . ($failed ? " $failed photo(s) were skipped - use JPG, PNG or WebP under 4 MB, or a full http(s) link." : ''), $failed ? 'err' : 'ok');
        redirect($editing ? 'admin/attraction_form.php?id=' . $id : 'admin/attractions.php');
    }
}

$photos = [];
if ($editing) {
    $st = $pdo->prepare('SELECT * FROM PHOTO WHERE AttractionID = ? ORDER BY SortOrder, PhotoID');
    $st->execute([$id]);
    $photos = $st->fetchAll();
}

app_header($editing ? 'Edit attraction' : 'Add attraction', 'admin-attractions');
?>
<p><a href="<?= e(url('admin/attractions.php')) ?>">← Back to attractions</a></p>
<?php foreach ($errors as $err): ?><div class="flash err"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="card mb">
    <h3>Basic information</h3>
    <div class="field"><label for="Name">Name *</label><input type="text" id="Name" name="Name" value="<?= e($a['Name']) ?>" required></div>
    <div class="field"><label>Categories * (choose one or more)</label>
      <div class="checks">
        <?php foreach (CATEGORIES as $c): ?>
          <label><input type="checkbox" name="categories[]" value="<?= e($c) ?>" <?= in_array($c, $selectedCats, true) ? 'checked' : '' ?>> <?= e($c) ?></label>
        <?php endforeach; ?>
      </div></div>
    <div class="grid3">
      <div class="field"><label for="Location">Area / town</label><input type="text" id="Location" name="Location" value="<?= e($a['Location']) ?>"></div>
      <div class="field"><label for="DistanceKm">Distance from Piliyandala (km)</label><input type="text" id="DistanceKm" name="DistanceKm" value="<?= e($a['DistanceKm']) ?>"></div>
      <div class="field"><label for="VisitMinutes">Typical visit (minutes) *</label><input type="text" id="VisitMinutes" name="VisitMinutes" value="<?= e($a['VisitMinutes']) ?>"></div>
    </div>
    <div class="field"><label for="Description">Description</label><textarea id="Description" name="Description"><?= e($a['Description']) ?></textarea></div>
  </div>

  <div class="card mb">
    <h3>Map location</h3>
    <div class="field"><label for="MapQuery">Map search text</label>
      <input type="text" id="MapQuery" name="MapQuery" value="<?= e($a['MapQuery']) ?>" placeholder="e.g. Gangaramaya Temple, Colombo, Sri Lanka">
      <div class="hint">This exact text is searched on Google Maps to show the pin. Check it on Google Maps to be sure it lands on the right place.</div></div>
    <div class="grid2">
      <div class="field"><label for="Latitude">Latitude (for weather and travel time)</label><input type="text" id="Latitude" name="Latitude" value="<?= e($a['Latitude']) ?>" placeholder="6.9167"></div>
      <div class="field"><label for="Longitude">Longitude</label><input type="text" id="Longitude" name="Longitude" value="<?= e($a['Longitude']) ?>" placeholder="79.8566"></div>
    </div>
    <div class="hint">To get exact coordinates: open Google Maps, right-click the place, and click the numbers shown at the top of the menu to copy them (latitude first).</div>
  </div>

  <div class="card mb">
    <h3>Visiting details</h3>
    <div class="grid3">
      <div class="field"><label for="OpeningHours">Opening hours (text shown to visitors)</label><input type="text" id="OpeningHours" name="OpeningHours" value="<?= e($a['OpeningHours']) ?>"></div>
      <div class="field"><label for="OpenTime">Opens at (optional)</label><input type="time" id="OpenTime" name="OpenTime" value="<?= e($a['OpenTime']) ?>"></div>
      <div class="field"><label for="CloseTime">Closes at (optional)</label><input type="time" id="CloseTime" name="CloseTime" value="<?= e($a['CloseTime']) ?>"></div>
    </div>
    <div class="grid2">
      <div class="field"><label for="Fees">Entrance fee in LKR</label><input type="text" id="Fees" name="Fees" value="<?= e($a['Fees']) ?>">
        <div class="hint">0 = free. Leave empty if unknown (visitors see "Check official source").</div></div>
      <div class="field"><label for="BestTime">Best time to visit</label><input type="text" id="BestTime" name="BestTime" value="<?= e($a['BestTime']) ?>"></div>
    </div>
    <div class="grid2">
      <div class="field"><label for="Parking">Parking</label><input type="text" id="Parking" name="Parking" value="<?= e($a['Parking']) ?>"></div>
      <div class="field"><label for="Facilities">Facilities</label><input type="text" id="Facilities" name="Facilities" value="<?= e($a['Facilities']) ?>"></div>
      <div class="field"><label for="Accessibility">Accessibility (wheelchair, family friendly)</label><input type="text" id="Accessibility" name="Accessibility" value="<?= e($a['Accessibility']) ?>"></div>
      <div class="field"><label for="SafetyInfo">Safety information</label><input type="text" id="SafetyInfo" name="SafetyInfo" value="<?= e($a['SafetyInfo']) ?>"></div>
    </div>
    <div class="field"><label for="Rules">Rules and etiquette (dress code, photography)</label><textarea id="Rules" name="Rules"><?= e($a['Rules']) ?></textarea></div>
  </div>

  <div class="card mb">
    <h3>Photos</h3>
    <?php if ($photos): ?>
      <div class="photo-grid mb">
        <?php foreach ($photos as $ph): ?>
          <label><img src="<?= e(photo_src($ph['Path'])) ?>" alt=""><input type="checkbox" name="delete_photos[]" value="<?= (int)$ph['PhotoID'] ?>"> Remove</label>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="field"><label for="photos">Upload photos (JPG, PNG or WebP, up to 4 MB each)</label>
      <input type="file" id="photos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple></div>
    <div class="field"><label for="photo_urls">Or paste photo links (one per line)</label>
      <textarea id="photo_urls" name="photo_urls" placeholder="https://..."></textarea>
      <div class="hint">Only use photos you own or that are free to use (for example from Wikimedia Commons), and credit them in your report.</div></div>
  </div>

  <button class="btn" type="submit">💾 Save attraction</button>
  <a class="btn ghost" href="<?= e(url('admin/attractions.php')) ?>">Cancel</a>
</form>
<?php app_footer();
