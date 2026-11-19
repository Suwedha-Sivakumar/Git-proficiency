<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/layout.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$errors = [];
$old = ['fullname' => '', 'username' => '', 'type' => 'Tourist'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old['fullname'] = trim($_POST['fullname'] ?? '');
    $old['username'] = trim($_POST['username'] ?? '');
    $old['type'] = ($_POST['type'] ?? '') === 'Local' ? 'Local' : 'Tourist';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if ($old['fullname'] === '' || mb_strlen($old['fullname']) > 100) {
        $errors[] = 'Please enter your full name.';
    }
    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $old['username'])) {
        $errors[] = 'Username must be 3-30 characters: letters, numbers or underscore only.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }
    if (!$errors) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM USER WHERE Username = ?');
        $st->execute([$old['username']]);
        if ((int)$st->fetchColumn() > 0) {
            $errors[] = 'That username is already taken. Please choose another.';
        }
    }
    if (!$errors) {
        $ins = $pdo->prepare('INSERT INTO USER (Username, FullName, PasswordHash, UserType) VALUES (?, ?, ?, ?)');
        $ins->execute([$old['username'], $old['fullname'], password_hash($password, PASSWORD_DEFAULT), $old['type']]);
        flash('Account created. Please sign in.');
        redirect('login.php');
    }
}

auth_header('Create account');
?>
    <h2>Create your account</h2>
    <p class="sub">Choose a username and password. You will use these to sign in.</p>
    <?php foreach ($errors as $err): ?><div class="flash err"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="field"><label for="fullname">Full name</label>
        <input type="text" id="fullname" name="fullname" value="<?= e($old['fullname']) ?>" required></div>
      <div class="field"><label for="username">Username</label>
        <input type="text" id="username" name="username" value="<?= e($old['username']) ?>" required>
        <div class="hint">3-30 characters: letters, numbers, underscore.</div></div>
      <div class="field"><label for="type">I am a</label>
        <select id="type" name="type">
          <option value="Tourist" <?= $old['type'] === 'Tourist' ? 'selected' : '' ?>>Tourist (visiting the area)</option>
          <option value="Local" <?= $old['type'] === 'Local' ? 'selected' : '' ?>>Local visitor (live nearby)</option>
        </select></div>
      <div class="field"><label for="password">Password</label>
        <input type="password" id="password" name="password" required></div>
      <div class="field"><label for="confirm">Confirm password</label>
        <input type="password" id="confirm" name="confirm" required></div>
      <button class="btn block" type="submit">Create account</button>
    </form>
    <p class="alt">Already registered? <a href="<?= e(url('login.php')) ?>">Sign in</a></p>
<?php auth_footer();
