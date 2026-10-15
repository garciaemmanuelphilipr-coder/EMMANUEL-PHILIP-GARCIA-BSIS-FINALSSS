<?php
require "db.php";
require_login(["admin", "organizer"]);
$role = $_SESSION['role'];
$mine = ($role == "organizer") ? " AND e.created_by = " . (int) $_SESSION['user_id'] : "";
$scans = "(SELECT event_id, student_id, COUNT(*) AS n FROM attendance GROUP BY event_id, student_id)";

$total_students = (int) mysqli_fetch_assoc(run("SELECT COUNT(*) AS n FROM students"))['n'];

$status_counts = ["draft" => 0, "published" => 0, "closed" => 0];
$result = run("SELECT e.status, COUNT(*) AS n FROM events e WHERE 1=1 $mine GROUP BY e.status");
while ($row = mysqli_fetch_assoc($result)) {
    $status_counts[$row['status']] = (int) $row['n'];
}
$total_events = array_sum($status_counts);

$att_rows = [];
$result = run("SELECT e.id, e.title, e.event_date,
                 COALESCE(SUM(a.n >= e.sessions), 0) AS present,
                 COALESCE(SUM(a.n > 0 AND a.n < e.sessions), 0) AS incomplete
               FROM events e
               LEFT JOIN $scans a ON a.event_id = e.id
               WHERE e.status <> 'draft' $mine
               GROUP BY e.id
               ORDER BY e.event_date DESC, e.event_time DESC
               LIMIT 8");
while ($row = mysqli_fetch_assoc($result)) {
    $att_rows[] = $row;
}
$att_rows = array_reverse($att_rows);
$att_labels = [];
$att_present = [];
$att_incomplete = [];
$att_absent = [];
foreach ($att_rows as $r) {
    $att_labels[] = $r['title'] . " " . substr($r['event_date'], 5);
    $att_present[] = (int) $r['present'];
    $att_incomplete[] = (int) $r['incomplete'];
    $att_absent[] = max(0, $total_students - (int) $r['present'] - (int) $r['incomplete']);
}

$fine_rows = [];
$total_fines = 0;
$result = run("SELECT e.title, e.event_date,
                 COALESCE(SUM(CASE WHEN x.id IS NULL
                   THEN (e.sessions - LEAST(COALESCE(a.n, 0), e.sessions)) * e.fine_amount / e.sessions
                   ELSE 0 END), 0) AS fines
               FROM events e
               CROSS JOIN students s
               LEFT JOIN $scans a ON a.event_id = e.id AND a.student_id = s.id
               LEFT JOIN excuses x ON x.event_id = e.id AND x.student_id = s.id
               WHERE e.status = 'closed' AND e.category = 'mandatory' $mine
               GROUP BY e.id
               ORDER BY e.event_date DESC, e.event_time DESC");
while ($row = mysqli_fetch_assoc($result)) {
    $fine_rows[] = $row;
    $total_fines += $row['fines'];
}
$fine_chart = array_reverse(array_slice($fine_rows, 0, 8));
$fine_labels = [];
$fine_values = [];
foreach ($fine_chart as $r) {
    $fine_labels[] = $r['title'] . " " . substr($r['event_date'], 5);
    $fine_values[] = round((float) $r['fines'], 2);
}

$program_labels = [];
$program_values = [];
$result = run("SELECT program, COUNT(*) AS n FROM students GROUP BY program ORDER BY n DESC");
while ($row = mysqli_fetch_assoc($result)) {
    $program_labels[] = $row['program'];
    $program_values[] = (int) $row['n'];
}

$flags = JSON_HEX_TAG | JSON_HEX_AMP;

include "header.php";
?>

<h2>Dashboard</h2>

<div class="stats">
  <div class="stat">
    <div class="stat-number"><?php echo $total_students; ?></div>
    <div class="stat-label">Students</div>
  </div>
  <div class="stat">
    <div class="stat-number"><?php echo $total_events; ?></div>
    <div class="stat-label">Events</div>
  </div>
  <div class="stat">
    <div class="stat-number"><?php echo $status_counts['published']; ?></div>
    <div class="stat-label">Open for check in</div>
  </div>
  <div class="stat">
    <div class="stat-number"><?php echo $status_counts['draft']; ?></div>
    <div class="stat-label">Waiting for approval</div>
  </div>
  <div class="stat">
    <div class="stat-number">PHP <?php echo number_format($total_fines, 2); ?></div>
    <div class="stat-label">Total fines</div>
  </div>
</div>

<div class="charts">
  <div class="card chartbox">
    <h3>Attendance per event</h3>
    <?php if (count($att_rows) == 0) { ?>
      <p>No published events yet.</p>
    <?php } else { ?>
      <div class="chartwrap"><canvas id="attChart"></canvas></div>
    <?php } ?>
  </div>

  <div class="card chartbox">
    <h3>Fines per closed event (PHP)</h3>
    <?php if (count($fine_chart) == 0) { ?>
      <p>No closed mandatory events yet.</p>
    <?php } else { ?>
      <div class="chartwrap"><canvas id="fineChart"></canvas></div>
    <?php } ?>
  </div>

  <div class="card chartbox">
    <h3>Events by status</h3>
    <?php if ($total_events == 0) { ?>
      <p>No events yet.</p>
    <?php } else { ?>
      <div class="chartwrap"><canvas id="statusChart"></canvas></div>
    <?php } ?>
  </div>

  <div class="card chartbox">
    <h3>Students by program</h3>
    <?php if (count($program_values) == 0) { ?>
      <p>No students yet.</p>
    <?php } else { ?>
      <div class="chartwrap"><canvas id="programChart"></canvas></div>
    <?php } ?>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
var orange = "#d9480f";
var lightOrange = "#ffb27a";
var charcoal = "#2b2b2b";
var gray = "#8a8a8a";
var palette = [orange, charcoal, "#ff8a3d", gray, "#5f5f5f", lightOrange, "#bf3f0b", "#c9c9c9"];

var baseOptions = { responsive: true, maintainAspectRatio: false };

if (document.getElementById("attChart")) {
  new Chart(document.getElementById("attChart"), {
    type: "bar",
    data: {
      labels: <?php echo json_encode($att_labels, $flags); ?>,
      datasets: [
        { label: "Present", data: <?php echo json_encode($att_present); ?>, backgroundColor: orange },
        { label: "Incomplete", data: <?php echo json_encode($att_incomplete); ?>, backgroundColor: lightOrange },
        { label: "Absent", data: <?php echo json_encode($att_absent); ?>, backgroundColor: gray }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        x: { stacked: true },
        y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } }
      }
    }
  });
}

if (document.getElementById("fineChart")) {
  new Chart(document.getElementById("fineChart"), {
    type: "bar",
    data: {
      labels: <?php echo json_encode($fine_labels, $flags); ?>,
      datasets: [{ label: "Fines (PHP)", data: <?php echo json_encode($fine_values); ?>, backgroundColor: charcoal }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });
}

if (document.getElementById("statusChart")) {
  new Chart(document.getElementById("statusChart"), {
    type: "doughnut",
    data: {
      labels: ["Waiting for approval", "Open for check in", "Closed"],
      datasets: [{
        data: [<?php echo $status_counts['draft']; ?>, <?php echo $status_counts['published']; ?>, <?php echo $status_counts['closed']; ?>],
        backgroundColor: [lightOrange, orange, charcoal]
      }]
    },
    options: baseOptions
  });
}

if (document.getElementById("programChart")) {
  new Chart(document.getElementById("programChart"), {
    type: "doughnut",
    data: {
      labels: <?php echo json_encode($program_labels, $flags); ?>,
      datasets: [{ data: <?php echo json_encode($program_values); ?>, backgroundColor: palette }]
    },
    options: baseOptions
  });
}
</script>

<?php include "footer.php"; ?>