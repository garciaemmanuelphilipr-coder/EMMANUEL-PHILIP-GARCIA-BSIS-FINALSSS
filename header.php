<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>eventSched&Attendance</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="nav">
  <b>eventSched&amp;Attendance</b>
  <?php if ($_SESSION['role'] != "staff") { ?>
    <a href="dashboard.php">Dashboard</a>
    <a href="events.php">Events</a>
  <?php } ?>
  <?php if ($_SESSION['role'] == "admin") { ?>
    <a href="publish.php">Publish Events</a>
  <?php } ?>
  <?php if ($_SESSION['role'] != "staff") { ?>
    <a href="students.php">Students</a>
  <?php } ?>
  <?php if ($_SESSION['role'] != "organizer") { ?>
    <a href="checkin.php">Staff Check In</a>
  <?php } ?>
  <?php if ($_SESSION['role'] != "staff") { ?>
    <a href="compliance.php">Student Record</a>
    <a href="report.php">Attendance Report</a>
  <?php } ?>
  <?php if ($_SESSION['role'] == "admin") { ?>
    <a href="admin_logout.php">Admin log out (<?php echo e($_SESSION['name']); ?>)</a>
  <?php } else { ?>
    <a href="logout.php">Log out (<?php echo e($_SESSION['name']); ?>)</a>
  <?php } ?>
</div>
<div class="main">