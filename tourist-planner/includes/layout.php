<?php
// Application shell: sidebar + top bar. Call app_header() at the top of a page
// and app_footer() at the bottom.

function app_header(string $title, string $active = ''): void
{
    $name = $_SESSION['fullname'] ?? 'User';
    $username = $_SESSION['username'] ?? '';
    $role = $_SESSION['role'] ?? '';
    $count = plan_count();
    $nav = [
        ['dashboard', 'dashboard.php', '🏠', 'Dashboard'],
        ['browse', 'browse.php', '🧭', 'Browse attractions'],
        ['itinerary', 'itinerary.php', '🗓️', 'My itinerary'],
    ];
    $adminNav = [
        ['admin', 'admin/index.php', '📊', 'Admin overview'],
        ['admin-attractions', 'admin/attractions.php', '📍', 'Manage attractions'],
        ['admin-reviews', 'admin/reviews.php', '💬', 'Moderate reviews'],
        ['admin-users', 'admin/users.php', '👥', 'Manage users'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<meta name="base-url" content="<?= e(BASE_URL) ?>">
<title><?= e($title) ?> - Day-Visit Planner</title>
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="brand"><span class="brand-mark">🗺️</span><div><b>Day-Visit Planner</b><small>Piliyandala</small></div></div>
    <nav class="side-nav">
      <?php foreach ($nav as $n): ?>
        <a href="<?= e(url($n[1])) ?>" class="<?= $active === $n[0] ? 'active' : '' ?>">
          <span class="ico"><?= $n[2] ?></span><?= e($n[3]) ?>
          <?php if ($n[0] === 'itinerary'): ?><span class="badge" id="planBadge" <?= $count ? '' : 'hidden' ?>><?= $count ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <?php if ($role === 'Admin'): ?>
        <div class="nav-label">Administration</div>
        <?php foreach ($adminNav as $n): ?>
          <a href="<?= e(url($n[1])) ?>" class="<?= $active === $n[0] ? 'active' : '' ?>"><span class="ico"><?= $n[2] ?></span><?= e($n[3]) ?></a>
        <?php endforeach; ?>
      <?php endif; ?>
    </nav>
    <div class="user-box">
      <div class="avatar"><?= e(strtoupper(substr($name, 0, 1))) ?></div>
      <div class="who"><b><?= e($name) ?></b><small>@<?= e($username) ?> · <?= e($role) ?></small></div>
      <a class="logout" href="<?= e(url('logout.php')) ?>" title="Sign out">⎋</a>
    </div>
  </aside>
  <div class="main">
    <header class="topbar">
      <button class="menu-btn" id="menuBtn" aria-label="Menu">☰</button>
      <h1><?= e($title) ?></h1>
      <a class="btn small ghost" href="<?= e(url('itinerary.php')) ?>">🗓️ Itinerary <span class="badge dark" id="planBadgeTop" <?= $count ? '' : 'hidden' ?>><?= $count ?></span></a>
    </header>
    <main class="content">
      <?php foreach (take_flash() as $f): ?>
        <div class="flash <?= $f[0] === 'ok' ? 'ok' : 'err' ?>"><?= e($f[1]) ?></div>
      <?php endforeach; ?>
<?php
}

function app_footer(): void
{
    ?>
    </main>
  </div>
</div>
<div class="toast" id="toast" hidden></div>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
<?php
}

// Layout for sign-in and register screens (no sidebar)
function auth_header(string $title): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> - Day-Visit Planner</title>
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body class="auth-body">
<div class="auth-wrap">
  <section class="auth-hero">
    <div class="hero-inner">
      <div class="brand light"><span class="brand-mark">🗺️</span><div><b>Day-Visit Planner</b><small>Piliyandala</small></div></div>
      <h2>Plan a perfect one-day trip</h2>
      <p>Explore temples, parks, beaches and heritage sites within 25 km of Piliyandala. Check opening hours, safety and accessibility, see them on the map, and build a timed itinerary for your day.</p>
      <ul>
        <li>12 attractions with maps and visitor tips</li>
        <li>Smart one-day itinerary with travel times</li>
        <li>Reviews from other visitors</li>
      </ul>
    </div>
  </section>
  <section class="auth-card">
<?php foreach (take_flash() as $f): ?>
    <div class="flash <?= $f[0] === 'ok' ? 'ok' : 'err' ?>"><?= e($f[1]) ?></div>
<?php endforeach; ?>
<?php
}

function auth_footer(): void
{
    ?>
  </section>
</div>
</body>
</html>
<?php
}
