<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/layout.php';
require_admin();
$me = (int)$_SESSION['uid'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $uid = (int)($_POST['id'] ?? 0);
    $do = $_POST['action'] ?? '';
    if ($uid === $me) {
        flash('You cannot change or delete your own account here.', 'err');
    } elseif ($do === 'role') {
        $role = $_POST['role'] ?? '';
        if (in_array($role, ['Tourist', 'Local', 'Admin'], true)) {
            $pdo->prepare('UPDATE USER SET UserType = ? WHERE UserID = ?')->execute([$role, $uid]);
            flash('Role updated.');
        }
    } elseif ($do === 'password') {
        $new = $_POST['new_password'] ?? '';
        if (strlen($new) < 6) {
            flash('New password must be at least 6 characters.', 'err');
        } else {
            $pdo->prepare('UPDATE USER SET PasswordHash = ? WHERE UserID = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
            flash('Password reset.');
        }
    } elseif ($do === 'delete') {
        $pdo->prepare('DELETE FROM USER WHERE UserID = ?')->execute([$uid]);
        flash('User deleted.');
    }
    redirect('admin/users.php');
}

$rows = $pdo->query('SELECT u.*, (SELECT COUNT(*) FROM REVIEW r WHERE r.UserID = u.UserID) AS Reviews,
                     (SELECT COUNT(*) FROM ITINERARY i WHERE i.UserID = u.UserID) AS Plans
                     FROM USER u ORDER BY u.CreatedAt DESC')->fetchAll();
app_header('Manage users', 'admin-users');
?>
<div class="card table-wrap">
  <table>
    <tr><th>User</th><th>Role</th><th>Reviews</th><th>Plans</th><th>Joined</th><th>Actions</th></tr>
    <?php foreach ($rows as $u): ?>
      <tr>
        <td><b><?= e($u['FullName']) ?></b><br><small>@<?= e($u['Username']) ?></small></td>
        <td><span class="pill"><?= e($u['UserType']) ?></span></td>
        <td><?= (int)$u['Reviews'] ?></td><td><?= (int)$u['Plans'] ?></td>
        <td><small><?= e(date('d M Y', strtotime($u['CreatedAt']))) ?></small></td>
        <td>
          <?php if ((int)$u['UserID'] === $me): ?><small>This is you</small><?php else: ?>
            <div class="row">
              <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['UserID'] ?>"><input type="hidden" name="action" value="role">
                <select name="role" style="width:auto">
                  <?php foreach (['Tourist', 'Local', 'Admin'] as $r): ?><option <?= $u['UserType'] === $r ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?>
                </select><button class="btn small ghost" type="submit">Set role</button></form>
              <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['UserID'] ?>"><input type="hidden" name="action" value="password">
                <input type="password" name="new_password" placeholder="New password" style="width:130px"><button class="btn small ghost" type="submit">Reset</button></form>
              <form method="post" data-confirm="Delete user @<?= e($u['Username']) ?> and their reviews and plans?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['UserID'] ?>"><input type="hidden" name="action" value="delete">
                <button class="btn small danger" type="submit">Delete</button></form>
            </div>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php app_footer();
