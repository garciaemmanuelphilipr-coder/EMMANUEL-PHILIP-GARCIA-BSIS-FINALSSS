<?php
require "db.php";
require_login(["admin", "organizer"]);

$event_id = $_GET['event_id'] ?? "";
$result = run("SELECT * FROM events WHERE id = ?", "i", [$event_id]);
$event = mysqli_fetch_assoc($result);
if (!$event) {
    die("Event not found.");
}
$n = (int) $event['sessions'];         
$labels = slots_for($n);
$sql = "SELECT s.student_no, s.name, s.program,
          a.am_in, a.am_out, a.pm_in, a.pm_out, COALESCE(a.n, 0) AS done,
          (x.id IS NOT NULL) AS excused
        FROM students s
        LEFT JOIN (SELECT student_id, COUNT(*) AS n,
                     MAX(CASE WHEN slot = 'am_in'  THEN scanned_at END) AS am_in,
                     MAX(CASE WHEN slot = 'am_out' THEN scanned_at END) AS am_out,
                     MAX(CASE WHEN slot = 'pm_in'  THEN scanned_at END) AS pm_in,
                     MAX(CASE WHEN slot = 'pm_out' THEN scanned_at END) AS pm_out
                   FROM attendance WHERE event_id = ? GROUP BY student_id) a
               ON a.student_id = s.id
        LEFT JOIN excuses x ON x.event_id = ? AND x.student_id = s.id
        ORDER BY COALESCE(a.n, 0) DESC, s.name";
$result = run($sql, "ii", [$event_id, $event_id]);
$slot_fine = ($event['category'] == "mandatory") ? $event['fine_amount'] / $n : 0;
$rows = [];
$present = 0;
$incomplete = 0;
$excused = 0;
$absent = 0;
$total_fines = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $row['missed'] = $n - $row['done'];
    $row['fine'] = $row['excused'] ? 0 : $row['missed'] * $slot_fine;

    if ($row['done'] >= $n) { $row['status'] = "Present"; $present++; }
    elseif ($row['excused']) { $row['status'] = "Excused"; $excused++; }
    elseif ($row['done'] > 0) { $row['status'] = "Incomplete"; $incomplete++; }
    else { $row['status'] = "Absent"; $absent++; }
    $total_fines += $row['fine'];
    $rows[] = $row;
}
function cell($time, $slot_fine, $excused) {
    if ($time) {
        return '<b style="color:#1b7a43;">In</b>';
    }
    return "-";
}
if (isset($_GET['download'])) {
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=attendance_event_" . $event_id . ".csv");
    $out = fopen("php://output", "w");
    fputcsv($out, ["Event", $event['title'], $event['event_date']]);
    fputcsv($out, array_merge(["Student no.", "Name", "Program"], array_values($labels),
                              ["Sessions missed", "Status", "Total fine"]));
    foreach ($rows as $r) {
        $line = [$r['student_no'], $r['name'], $r['program']];
        foreach ($labels as $key => $label) {
            $line[] = $r[$key] ? substr($r[$key], 11, 5) : "-";
        }
        $line[] = $r['missed'];
        $line[] = $r['status'];
        $line[] = number_format($r['fine'], 2, ".", "");
        fputcsv($out, $line);
    }
    fputcsv($out, array_merge(array_fill(0, count($labels) + 4, ""),
                              ["TOTAL FINES", number_format($total_fines, 2, ".", "")]));
    fclose($out);
    exit;
}
include "header.php";
?>
<h2><?php echo e($event['title']); ?></h2>
<p>
  <?php echo e($event['event_date'] . " " . substr($event['event_time'], 0, 5) . " - " . $event['venue']); ?>
  (<?php echo e($event['category']); ?>, <?php echo e($event['status']); ?>,
  <?php echo $n == 2 ? "Half day event" : "Whole day event"; ?>)
</p>
<?php if ($slot_fine > 0) { ?>
  <p>Event fine: PHP <?php echo number_format($event['fine_amount'], 2); ?>
     = PHP <?php echo number_format($slot_fine, 2); ?> for each scan
     (<?php echo e(implode(", ", $labels)); ?>).</p>
<?php } ?>
<div class="card">
  <b class="good">Present (all <?php echo $n; ?>): <?php echo $present; ?></b> &nbsp;
  <b style="color:#b26a00;">Incomplete: <?php echo $incomplete; ?></b> &nbsp;
  <b>Excused: <?php echo $excused; ?></b> &nbsp;
  <b class="bad">Absent: <?php echo $absent; ?></b>
</div>
<p>
  <a href="attendance.php?event_id=<?php echo $event['id']; ?>&download=1">Download CSV</a> &nbsp;|&nbsp;
  <a href="events.php">Back to events</a>
</p>
<table>
  <tr>
    <th>Student no.</th><th>Name</th><th>Program</th>
    <?php foreach ($labels as $label) { ?><th><?php echo $label; ?></th><?php } ?>
    <th>Status</th><th>Total fine</th>
  </tr>
  <?php foreach ($rows as $r) { ?>
  <tr>
    <td><?php echo e($r['student_no']); ?></td>
    <td><?php echo e($r['name']); ?></td>
    <td><?php echo e($r['program']); ?></td>
    <?php foreach ($labels as $key => $label) { ?>
      <td><?php echo cell($r[$key], $slot_fine, $r['excused']); ?></td>
    <?php } ?>
    <?php
      $color = "";
      if ($r['status'] == "Present") $color = "color:#1b7a43;font-weight:bold;";
      if ($r['status'] == "Incomplete") $color = "color:#b26a00;font-weight:bold;";
      if ($r['status'] == "Absent") $color = "color:#b3261e;font-weight:bold;";
    ?>
    <td style="<?php echo $color; ?>"><?php echo e($r['status']); ?></td>
    <td><?php echo $r['fine'] > 0 ? "<b>PHP " . number_format($r['fine'], 2) . "</b>" : "-"; ?></td>
  </tr>
  <?php } ?>
  <tr>
    <td colspan="<?php echo count($labels) + 4; ?>"><b>Total fines for this event</b></td>
    <td><b>PHP <?php echo number_format($total_fines, 2); ?></b></td>
  </tr>
</table>

<?php include "footer.php"; ?>