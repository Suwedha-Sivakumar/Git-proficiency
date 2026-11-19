<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $rid = (int)($_POST['id'] ?? 0);
    $do = $_POST['action'] ?? '';
    if ($do === 'approve' || $do === 'reject') {
        $status = $do === 'approve' ? 'Approved' : 'Rejected';
        $pdo->prepare('UPDATE REVIEW SET Status = ? WHERE ReviewID = ?')->execute([$status, $rid]);
        flash('Review ' . strtolower($status) . '.');
    } elseif ($do === 'delete') {
        $pdo->prepare('DELETE FROM REVIEW WHERE ReviewID = ?')->execute([$rid]);
        flash('Review deleted.');
    }
    redirect('admin/reviews.php?status=' . urlencode($_POST['back'] ?? 'Pending'));
}

$status = $_GET['status'] ?? 'Pending';
if (!in_array($status, ['Pending', 'Approved', 'Rejected'], true)) {
    $status = 'Pending';
}
$st = $pdo->prepare('SELECT r.*, u.Username, u.FullName, a.Name AS AttractionName FROM REVIEW r
                     JOIN USER u ON u.UserID = r.UserID JOIN ATTRACTION a ON a.AttractionID = r.AttractionID
                     WHERE r.Status = ? ORDER BY r.CreatedAt DESC');
$st->execute([$status]);
$rows = $st->fetchAll();

app_header('Moderate reviews', 'admin-reviews');
?>
<div class="tabs">
  <?php foreach (['Pending', 'Approved', 'Rejected'] as $t): ?>
    <a class="btn small <?= $status === $t ? 'teal' : 'ghost' ?>" href="<?= e(url('admin/reviews.php?status=' . $t)) ?>"><?= $t ?></a>
  <?php endforeach; ?>
</div>
<div class="card">
  <?php if (!$rows): ?><p class="muted">No <?= e(strtolower($status)) ?> reviews.</p><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <div class="review">
      <b><?= e($r['AttractionName']) ?></b> · <small><?= e($r['FullName']) ?> (@<?= e($r['Username']) ?>) · <?= e(date('d M Y H:i', strtotime($r['CreatedAt']))) ?></small><br>
      <span class="stars"><?= e(stars((float)$r['Rating'])) ?></span>
      <div><?= nl2br(e($r['Comment'])) ?></div>
      <div class="row mt">
        <?php foreach ([['approve', 'Approve', 'btn small teal'], ['reject', 'Reject', 'btn small ghost'], ['delete', 'Delete', 'btn small danger']] as $b): ?>
          <?php if (($b[0] === 'approve' && $status === 'Approved') || ($b[0] === 'reject' && $status === 'Rejected')) { continue; } ?>
          <form method="post" <?= $b[0] === 'delete' ? 'data-confirm="Delete this review permanently?"' : '' ?>>
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['ReviewID'] ?>"><input type="hidden" name="back" value="<?= e($status) ?>">
            <button class="<?= $b[2] ?>" name="action" value="<?= $b[0] ?>" type="submit"><?= $b[1] ?></button>
          </form>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php app_footer();
