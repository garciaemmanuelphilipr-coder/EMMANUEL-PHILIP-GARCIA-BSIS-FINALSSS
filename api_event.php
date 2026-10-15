<?php
require __DIR__ . "/db.php";
header("Content-Type: application/json");

if (!isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(["error" => "Please log in."]);
    exit;
}

$result = run("SELECT id, title, event_date, event_time, venue, category, fine_amount, status
               FROM events WHERE status <> 'draft' ORDER BY event_date DESC");
$list = [];
while ($row = mysqli_fetch_assoc($result)) {
    $list[] = $row;
}
echo json_encode($list);