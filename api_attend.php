<?php
require "../db.php";
header("Content-Type: application/json");

if (!isset($_SESSION['role']) || $_SESSION['role'] == "organizer") {
    http_response_code(401);
    echo json_encode(["error" => "Not allowed."]);
    exit;
}

$event_id = $_GET['event_id'] ?? 0;
$result = run("SELECT s.student_no, s.name, s.program, a.scanned_at
               FROM attendance a JOIN students s ON s.id = a.student_id
               WHERE a.event_id = ? ORDER BY a.scanned_at", "i", [$event_id]);
$list = [];
while ($row = mysqli_fetch_assoc($result)) {
    $list[] = $row;
}
echo json_encode($list);