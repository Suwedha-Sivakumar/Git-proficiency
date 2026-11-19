<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

// Delete an attraction (POST + CSRF token)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $st = $pdo->prepare('SELECT Path FROM PHOTO WHERE AttractionID = ?');
    $st->execute([$id]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $path) {
        delete_upload_file($path);
    }
    $pdo->prepare('DELETE FROM ATTRACTION WHERE AttractionID = ?')->execute([$id]);
    flash('Attraction deleted.');
    redirect('admin/attractions.php');
}

$rows = $pdo->query(ATTR_SELECT . ' ORDER BY a.Name')->fetchAll();
app_header('Manage attractions', 'admin-attractions');
?>
<div class="row between mb">
  <p class="muted"><?= count($rows) ?> attractions</p>
  <a class="btn" href="<?= e(url('admin/attraction_form.php')) ?>">+ Add attraction</a>
</div>
<div class="card table-wrap">
  <table>
    <tr><th></th><th>Name</th><th>Category</th><th>Hours</th><th>Entrance</th><th>Visit</th><th></th></tr>
    <?php foreach ($rows as $a): ?>
      <tr>
        <td><?php if ($a['Photo']): ?><img class="thumb-sm" src="<?= e(photo_src($a['Photo'])) ?>" alt=""><?php else: ?><div class="thumb-sm"><?= cat_icon($a['Category']) ?></div><?php endif; ?></td>
        <td><a href="<?= e(url('attraction.php?id=' . $a['AttractionID'])) ?>"><b><?= e($a['Name']) ?></b></a><br><small><?= e($a['Location']) ?></small></td>
        <td><?= e($a['Category']) ?></td>
        <td><small><?= e($a['OpeningHours']) ?></small></td>
        <td><?= e(fee_text($a['Fees'])) ?></td>
        <td><?= e(fmt_duration((int)$a['VisitMinutes'])) ?></td>
        <td>
          <div class="row">
            <a class="btn small ghost" href="<?= e(url('admin/attraction_form.php?id=' . $a['AttractionID'])) ?>">Edit</a>
            <form method="post" data-confirm="Delete <?= e($a['Name']) ?> and its photos and reviews?">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$a['AttractionID'] ?>">
              <button class="btn small danger" type="submit">Delete</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php app_footer();
