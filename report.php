<?php
require "db.php";
require_login(["admin", "organizer"]);

$scans = "(SELECT event_id, student_id, COUNT(*) AS n,
             MAX(CASE WHEN slot = 'am_in'  THEN scanned_at END) AS am_in,
             MAX(CASE WHEN slot = 'am_out' THEN scanned_at END) AS am_out,
             MAX(CASE WHEN slot = 'pm_in'  THEN scanned_at END) AS pm_in,
             MAX(CASE WHEN slot = 'pm_out' THEN scanned_at END) AS pm_out
           FROM attendance GROUP BY event_id, student_id)";

function cell($time, $slot_fine, $excused) {
    if ($time) {
        return '<b style="color:#1b7a43;">In</b>';
    }
    return "-";
}

$event_list = [];
$result = run("SELECT id, title, event_date, sessions FROM events WHERE status <> 'draft' ORDER BY event_date DESC, event_time DESC");
while ($row = mysqli_fetch_assoc($result)) {
    $event_list[] = $row;
}

$event_id = $_GET['event_id'] ?? "";
if ($event_id == "" && count($event_list) > 0) {
    $event_id = $event_list[0]['id'];
}

$report = [];
$event_total = 0;
$labels = slots_for(4);
if ($event_id != "") {
    $result = run("SELECT * FROM events WHERE id = ?", "i", [$event_id]);
    $event = mysqli_fetch_assoc($result);
    $n = $event ? (int) $event['sessions'] : 4;
    $labels = slots_for($n);

    $sql = "SELECT s.student_no, s.name, s.program,
              a.am_in, a.am_out, a.pm_in, a.pm_out, COALESCE(a.n, 0) AS done,
              (x.id IS NOT NULL) AS excused, e.category, e.fine_amount
            FROM events e
            CROSS JOIN students s
            LEFT JOIN $scans a ON a.event_id = e.id AND a.student_id = s.id
            LEFT JOIN excuses x ON x.event_id = e.id AND x.student_id = s.id
            WHERE e.id = ?
            ORDER BY s.name";
    $result = run($sql, "i", [$event_id]);
    while ($row = mysqli_fetch_assoc($result)) {
        $row['slot_fine'] = ($row['category'] == "mandatory") ? $row['fine_amount'] / $n : 0;
        $row['missed'] = $n - $row['done'];
        $row['fine'] = $row['excused'] ? 0 : $row['missed'] * $row['slot_fine'];

        if ($row['done'] >= $n) $row['status'] = "Present";
        elseif ($row['excused']) $row['status'] = "Excused";
        elseif ($row['done'] > 0) $row['status'] = "Incomplete";
        else $row['status'] = "Absent";

        $event_total += $row['fine'];
        $report[] = $row;
    }
}

include "header.php";
?>

<h2>Attendance Report</h2>

<?php if (count($event_list) == 0) { ?>
  <p>There are no events yet. Publish an event first.</p>
<?php } else { ?>

<form method="get">
  <label>Event
    <select name="event_id">
      <?php foreach ($event_list as $ev) { ?>
        <option value="<?php echo $ev['id']; ?>" <?php if ($ev['id'] == $event_id) echo "selected"; ?>>
          <?php echo e($ev['title'] . " (" . $ev['event_date'] . ")"); ?>
          <?php echo $ev['sessions'] == 2 ? " - Half day" : " - Whole day"; ?>
        </option>
      <?php } ?>
    </select>
  </label>
  <button type="submit">Show report</button>
</form>
<br>

<table>
  <tr>
    <th>Student no.</th><th>Name</th><th>Program</th>
    <?php foreach ($labels as $label) { ?><th><?php echo $label; ?></th><?php } ?>
    <th>Status</th><th>Total fine</th>
  </tr>
  <?php foreach ($report as $r) { ?>
  <tr>
    <td><?php echo e($r['student_no']); ?></td>
    <td><?php echo e($r['name']); ?></td>
    <td><?php echo e($r['program']); ?></td>
    <?php foreach ($labels as $key => $label) { ?>
      <td><?php echo cell($r[$key], $r['slot_fine'], $r['excused']); ?></td>
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
    <td><b>PHP <?php echo number_format($event_total, 2); ?></b></td>
  </tr>
</table>

<?php } ?>

<?php include "footer.php"; ?>