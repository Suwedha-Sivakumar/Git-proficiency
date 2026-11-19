<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Please sign in first.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid request. Refresh the page.']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$st = $pdo->prepare('SELECT Name FROM ATTRACTION WHERE AttractionID = ?');
$st->execute([$id]);
$name = $st->fetchColumn();
if (!$name) {
    echo json_encode(['ok' => false, 'message' => 'Attraction not found.']);
    exit;
}

ensure_plan();
if (!in_array($id, $_SESSION['plan']['ids'], true)) {
    $_SESSION['plan']['ids'][] = $id;
    $message = $name . ' added to your itinerary.';
} else {
    $message = $name . ' is already in your itinerary.';
}
echo json_encode(['ok' => true, 'count' => count($_SESSION['plan']['ids']), 'message' => $message]);
