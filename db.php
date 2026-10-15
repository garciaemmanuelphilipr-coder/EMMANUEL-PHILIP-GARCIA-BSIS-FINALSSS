<?php
session_start();

$conn = mysqli_connect("localhost", "root", "", "eventsched");
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
function run($sql, $types = "", $params = []) {
    global $conn;
    $stmt = mysqli_prepare($conn, $sql);
    if ($types != "") {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt); 
}
function e($text) {
    return htmlspecialchars($text);
}
function slots_for($sessions) {
    if ($sessions == 2) {
        return ["am_in" => "In", "am_out" => "Out"];
    }
    return ["am_in" => "AM In", "am_out" => "AM Out", "pm_in" => "PM In", "pm_out" => "PM Out"];
}
function require_login($allowed_roles) {
    if (!isset($_SESSION['role'])) {
        if ($allowed_roles == ["admin"]) {
            header("Location: admin_login.php");
        } else {
            header("Location: index.php");
        }
        exit;
    }
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        if ($_SESSION['role'] == "admin") {
            die("You do not have access to this page. <a href='publish.php'>Go back</a>");
        }
        die("You do not have access to this page. <a href='index.php'>Go back</a>");
    }
}