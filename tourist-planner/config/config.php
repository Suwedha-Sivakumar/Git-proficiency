<?php
// =====================================================================
//  Local Tourist Day-Visit Planner  -  core configuration and helpers
//  Every page loads this file first.
// =====================================================================

// ---- Settings you may need to change --------------------------------
const BASE_URL = '/tourist-planner';   // the folder name inside C:\xampp\htdocs
const DB_HOST  = 'localhost';
const DB_NAME  = 'tourist_planner';
const DB_USER  = 'root';               // XAMPP default
const DB_PASS  = '';                   // XAMPP default (blank)
const BASE_LAT = 6.8018;               // Piliyandala (trip starting point)
const BASE_LNG = 79.9227;
const CATEGORIES = ['Religious', 'Cultural', 'Nature', 'Heritage', 'Leisure'];

// ---- Session and database -------------------------------------------
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $ex) {
    http_response_code(500);
    die('Cannot connect to the database. Check that MySQL is running in XAMPP and that you imported database.sql.');
}

// Base query used wherever attractions are listed (adds first photo and rating)
const ATTR_SELECT = "SELECT a.*,
    (SELECT p.Path FROM PHOTO p WHERE p.AttractionID = a.AttractionID ORDER BY p.SortOrder, p.PhotoID LIMIT 1) AS Photo,
    (SELECT ROUND(AVG(r.Rating), 1) FROM REVIEW r WHERE r.AttractionID = a.AttractionID AND r.Status = 'Approved') AS AvgRating,
    (SELECT COUNT(*) FROM REVIEW r WHERE r.AttractionID = a.AttractionID AND r.Status = 'Approved') AS ReviewCount
    FROM ATTRACTION a";

// ---- Small helpers ---------------------------------------------------
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path)
{
    header('Location: ' . url($path));
    exit;
}

function flash(string $message, string $type = 'ok'): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function take_flash(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

// ---- Login and roles -------------------------------------------------
function is_logged_in(): bool
{
    return !empty($_SESSION['uid']);
}

function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'Admin';
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash('That area is for administrators only.', 'err');
        redirect('dashboard.php');
    }
}

// ---- CSRF protection (stops forged form submissions) ----------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(400);
        die('Invalid or expired form. Go back, refresh the page and try again.');
    }
}

// ---- Itinerary draft kept in the session ----------------------------
function ensure_plan(): void
{
    if (!isset($_SESSION['plan'])) {
        $_SESSION['plan'] = ['ids' => [], 'start' => '09:00', 'hours' => 8.0, 'title' => ''];
    }
}

function plan_count(): int
{
    return count($_SESSION['plan']['ids'] ?? []);
}

// ---- Display helpers -------------------------------------------------
function fee_text($fee): string
{
    if ($fee === null || $fee === '') {
        return 'Check official source';
    }
    return ((float)$fee == 0.0) ? 'Free' : 'LKR ' . number_format((float)$fee, 0);
}

function cat_icon(string $category): string
{
    $first = explode('/', $category)[0];
    $icons = ['Religious' => '🛕', 'Cultural' => '🏛️', 'Nature' => '🌿', 'Heritage' => '🏰', 'Leisure' => '🌅'];
    return $icons[$first] ?? '📍';
}

function photo_src(?string $path): string
{
    if (!$path) {
        return '';
    }
    return preg_match('#^https?://#i', $path) ? $path : url($path);
}

function stars(?float $avg): string
{
    if ($avg === null) {
        return '';
    }
    $full = (int)round($avg);
    return str_repeat('★', $full) . str_repeat('☆', 5 - $full);
}

function time_to_min(?string $time): ?int
{
    if ($time === null || $time === '') {
        return null;
    }
    $parts = explode(':', $time);
    return ((int)$parts[0]) * 60 + (int)($parts[1] ?? 0);
}

function fmt_clock(int $minutes): string
{
    $h = intdiv($minutes, 60) % 24;
    $m = $minutes % 60;
    $ampm = $h >= 12 ? 'PM' : 'AM';
    $h12 = $h % 12;
    if ($h12 === 0) {
        $h12 = 12;
    }
    return sprintf('%d:%02d %s', $h12, $m, $ampm);
}

function fmt_duration(int $minutes): string
{
    if ($minutes < 60) {
        return $minutes . ' min';
    }
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $m === 0 ? $h . ' h' : $h . ' h ' . $m . ' min';
}

// ---- Distance and travel-time estimates ------------------------------
function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $r = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return 2 * $r * asin(sqrt($a));
}

// Road distance is roughly 1.35 x the straight line; average city speed 25 km/h
function road_km(float $straightKm): float
{
    return $straightKm * 1.35;
}

function travel_minutes(float $roadKm): int
{
    return max(5, (int)ceil($roadKm / 25 * 60));
}

// Order stops by "nearest next stop", starting from Piliyandala
function optimize_order(PDO $pdo, array $ids): array
{
    if (count($ids) < 2) {
        return $ids;
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT AttractionID, Latitude, Longitude FROM ATTRACTION WHERE AttractionID IN ($in)");
    $st->execute($ids);
    $pts = [];
    foreach ($st->fetchAll() as $row) {
        if ($row['Latitude'] !== null && $row['Longitude'] !== null) {
            $pts[(int)$row['AttractionID']] = [(float)$row['Latitude'], (float)$row['Longitude']];
        }
    }
    $remaining = [];
    $noCoords = [];
    foreach ($ids as $id) {
        if (isset($pts[$id])) {
            $remaining[] = $id;
        } else {
            $noCoords[] = $id;
        }
    }
    $order = [];
    $curLat = BASE_LAT;
    $curLng = BASE_LNG;
    while ($remaining) {
        $bestKey = 0;
        $bestDist = INF;
        foreach ($remaining as $key => $id) {
            $d = haversine($curLat, $curLng, $pts[$id][0], $pts[$id][1]);
            if ($d < $bestDist) {
                $bestDist = $d;
                $bestKey = $key;
            }
        }
        $chosen = $remaining[$bestKey];
        $order[] = $chosen;
        $curLat = $pts[$chosen][0];
        $curLng = $pts[$chosen][1];
        array_splice($remaining, $bestKey, 1);
    }
    return array_merge($order, $noCoords);
}

// Build the timed itinerary: arrival, visit, departure, travel and warnings
function build_timeline(PDO $pdo, array $ids, string $start, float $hours): array
{
    $stops = [];
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare(ATTR_SELECT . " WHERE a.AttractionID IN ($in)");
        $st->execute($ids);
        $byId = [];
        foreach ($st->fetchAll() as $row) {
            $byId[(int)$row['AttractionID']] = $row;
        }
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $stops[] = $byId[$id];
            }
        }
    }

    $parts = explode(':', $start);
    $startMin = ((int)$parts[0]) * 60 + (int)($parts[1] ?? 0);
    $deadline = $startMin + (int)round($hours * 60);
    $t = $startMin;
    $totalVisit = 0;
    $totalTravel = 0;
    $prevLat = BASE_LAT;
    $prevLng = BASE_LNG;

    foreach ($stops as $i => $s) {
        $hasCoords = $s['Latitude'] !== null && $s['Longitude'] !== null;
        if ($hasCoords) {
            $roadKm = road_km(haversine($prevLat, $prevLng, (float)$s['Latitude'], (float)$s['Longitude']));
            $travel = travel_minutes($roadKm);
        } else {
            $roadKm = null;
            $travel = 20;
        }
        $t += $travel;
        $totalTravel += $travel;

        $warnings = [];
        $open = time_to_min($s['OpenTime']);
        $close = time_to_min($s['CloseTime']);
        $visitStart = $t;
        if ($open !== null && $visitStart < $open) {
            $warnings[] = 'Opens at ' . fmt_clock($open) . ' - you would wait about ' . fmt_duration($open - $visitStart) . '.';
            $visitStart = $open;
        }
        $visit = (int)$s['VisitMinutes'];
        $depart = $visitStart + $visit;
        if ($close !== null && $depart > $close) {
            $warnings[] = 'Closes at ' . fmt_clock($close) . ' - you may run out of time here.';
        }

        $stops[$i]['travel'] = $travel;
        $stops[$i]['roadKm'] = $roadKm;
        $stops[$i]['arrive'] = $visitStart;
        $stops[$i]['depart'] = $depart;
        $stops[$i]['warnings'] = $warnings;

        $totalVisit += $visit;
        $t = $depart;
        if ($hasCoords) {
            $prevLat = (float)$s['Latitude'];
            $prevLng = (float)$s['Longitude'];
        }
    }

    return [
        'stops'       => $stops,
        'startMin'    => $startMin,
        'endMin'      => $t,
        'deadline'    => $deadline,
        'over'        => $t > $deadline,
        'overBy'      => max(0, $t - $deadline),
        'totalVisit'  => $totalVisit,
        'totalTravel' => $totalTravel,
    ];
}

// ---- Photo upload (admin) --------------------------------------------
// Returns the saved relative path, or null if the file is not a valid image
function save_upload(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    if ($file['size'] > 4 * 1024 * 1024) {
        return null;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        return null;
    }
    $dir = __DIR__ . '/../uploads/';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
        return null;
    }
    return 'uploads/' . $name;
}

// Delete a stored upload file (only files inside /uploads)
function delete_upload_file(string $path): void
{
    if (strpos($path, 'uploads/') === 0 && strpos($path, '..') === false) {
        $full = __DIR__ . '/../' . $path;
        if (is_file($full)) {
            unlink($full);
        }
    }
}
