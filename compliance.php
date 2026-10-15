<?php
require "db.php";
require_login(["admin", "organizer"]);
$scans = "(SELECT event_id, student_id, COUNT(*) AS n FROM attendance GROUP BY event_id, student_id)";
$sql = "SELECT s.student_no, s.name, s.program,
          COUNT(e.id) AS required,
          COALESCE(SUM(a.n >= e.sessions), 0) AS attended,
          COALESCE(SUM(e.id IS NOT NULL AND COALESCE(a.n, 0) < e.sessions AND x.id IS NOT NULL), 0) AS excused,
          COALESCE(SUM(e.id IS NOT NULL AND COALESCE(a.n, 0) < e.sessions AND x.id IS NULL), 0) AS missed,
          COALESCE(SUM(CASE WHEN e.id IS NOT NULL AND x.id IS NULL
                       THEN (e.sessions - LEAST(COALESCE(a.n, 0), e.sessions)) * e.fine_amount / e.sessions
                       ELSE 0 END), 0) AS fines
        FROM students s
        LEFT JOIN events e ON e.status = 'closed' AND e.category = 'mandatory'
        LEFT JOIN $scans a ON a.event_id = e.id AND a.student_id = s.id
        LEFT JOIN excuses x ON x.event_id = e.id AND x.student_id = s.id
        GROUP BY s.id
        ORDER BY fines DESC, s.name";
$result = run($sql);
$compliance = [];
$grand_total = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $compliance[] = $row;
    $grand_total += $row['fines'];
}

include "header.php";
?>

<h2>Student Record</h2>
<table>
  <tr><th>Student no.</th><th>Name</th><th>Program</th><th>Required</th><th>Completed</th>
      <th>Excused</th><th>Missed</th><th>Total fines</th></tr>
  <?php foreach ($compliance as $r) { ?>
  <tr>
    <td><?php echo e($r['student_no']); ?></td>
    <td><?php echo e($r['name']); ?></td>
    <td><?php echo e($r['program']); ?></td>
    <td><?php echo (int) $r['required']; ?></td>
    <td><?php echo (int) $r['attended']; ?></td>
    <td><?php echo (int) $r['excused']; ?></td>
    <td><?php echo (int) $r['missed']; ?></td>
    <td>PHP <?php echo number_format($r['fines'], 2); ?></td>
  </tr>
  <?php } ?>
  <tr>
    <td colspan="7"><b>Total fines of all students</b></td>
    <td><b>PHP <?php echo number_format($grand_total, 2); ?></b></td>
  </tr>
</table>

<?php include "footer.php"; ?>