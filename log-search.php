<?php
// AT-HM Checker search logger
// Receives: { term: string, matched: bool }
// Appends one JSON line per search to searches.json (which is blocked from direct web access)

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!isset($data['term']) || !is_string($data['term'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing term']);
    exit;
}

$term    = mb_strtolower(trim($data['term']));
$matched = isset($data['matched']) ? (bool)$data['matched'] : false;

// Ignore very short or very long terms
if (mb_strlen($term) < 2 || mb_strlen($term) > 100) {
    echo json_encode(['ok' => true, 'skipped' => true]);
    exit;
}

$logFile = __DIR__ . '/searches.json';
$entry   = json_encode(['t' => $term, 'm' => $matched ? 1 : 0]) . "\n";

// Append with an exclusive lock to avoid corruption under concurrent requests
$fh = fopen($logFile, 'a');
if ($fh) {
    flock($fh, LOCK_EX);
    fwrite($fh, $entry);
    flock($fh, LOCK_UN);
    fclose($fh);
}

echo json_encode(['ok' => true]);
