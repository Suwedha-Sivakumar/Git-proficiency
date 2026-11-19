<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/layout.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Simple brute-force brake: 5 wrong tries, then wait 60 seconds
    $locked = ($_SESSION['fail_count'] ?? 0) >= 5 && (time() - ($_SESSION['fail_time'] ?? 0)) < 60;
    if ($locked) {
        $error = 'Too many failed attempts. Please wait a minute and try again.';
    } else {
        $st = $pdo->prepare('SELECT * FROM USER WHERE Username = ?');
        $st->execute([$username]);
        $user = $st->fetch();

        if ($user && password_verify($password, $user['PasswordHash'])) {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$user['UserID'];
            $_SESSION['username'] = $user['Username'];
            $_SESSION['fullname'] = $user['FullName'];
            $_SESSION['role'] = $user['UserType'];
            unset($_SESSION['fail_count'], $_SESSION['fail_time']);
            redirect($user['UserType'] === 'Admin' ? 'admin/index.php' : 'dashboard.php');
        }
        $_SESSION['fail_count'] = ($_SESSION['fail_count'] ?? 0) + 1;
        $_SESSION['fail_time'] = time();
        $error = 'Incorrect username or password.';
    }
}

auth_header('Sign in');
?>
    <h2>Welcome back</h2>
    <p class="sub">Sign in with your username to continue.</p>
    <?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="on">
      <?= csrf_field() ?>
      <div class="field"><label for="username">Username</label>
        <input type="text" id="username" name="username" value="<?= e($username) ?>" required autofocus></div>
      <div class="field"><label for="password">Password</label>
        <input type="password" id="password" name="password" required></div>
      <button class="btn block" type="submit">Sign in</button>
    </form>
    <p class="alt">New here? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
<?php auth_footer();
