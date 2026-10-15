<?php
require "db.php";
require_login(["admin", "organizer"]);
$message = "";
$message_class = "good";

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    if ($_POST['action'] == "add") {
        $no = trim($_POST['student_no']);
        $name = trim($_POST['name']);
        $program = trim($_POST['program']);

        $check = run("SELECT id FROM students WHERE student_no = ?", "s", [$no]);
        if (mysqli_num_rows($check) > 0) {
            $message = "That student number already exists.";
            $message_class = "bad";
        } else {
            run("INSERT INTO students (student_no, name, program) VALUES (?, ?, ?)", "sss", [$no, $name, $program]);
            $message = $name . " was added.";
        }
    }
    if ($_POST['action'] == "delete") {
        $id = $_POST['student_id'];
        try {
            mysqli_begin_transaction($conn);
            run("DELETE FROM attendance WHERE student_id = ?", "i", [$id]);
            run("DELETE FROM excuses WHERE student_id = ?", "i", [$id]);
            run("DELETE FROM students WHERE id = ?", "i", [$id]);
            mysqli_commit($conn);
            $message = "Student and their attendance and excuse records were deleted.";
        } catch (mysqli_sql_exception $exception) {
            mysqli_rollback($conn);
            $message = "Could not delete this student and their records.";
            $message_class = "bad";
        }
    }
}
$q = trim($_GET['q'] ?? "");
$like = "%" . $q . "%";
$result = run("SELECT * FROM students
               WHERE student_no LIKE ? OR name LIKE ? OR program LIKE ?
               ORDER BY name", "sss", [$like, $like, $like]);
$students = [];
while ($row = mysqli_fetch_assoc($result)) {
    $students[] = $row;
}
include "header.php";
?>
<h2>Students</h2>
<p class="<?php echo $message_class; ?>"><?php echo e($message); ?></p>

<div class="card">
  <h3 style="margin-top:0;">Add a student</h3>
  <form method="post">
    <input type="hidden" name="action" value="add">
    <label>Student number
      <input type="text" name="student_no" placeholder="2024-0005" required>
    </label>
    <label>Full name <input type="text" name="name" required></label>
    <label>Program <input type="text" name="program" placeholder="BSIS" required></label>
    <button type="submit">Add student</button>
  </form>
</div>

<h3>Student list (<?php echo count($students); ?>)</h3>
<form method="get">
  <label>Search
    <input type="text" name="q" value="<?php echo e($q); ?>" placeholder="Name, number or program">
  </label>
  <button type="submit">Search</button>
</form>
<br>

<table>
  <tr><th>Student no.</th><th>Name</th><th>Program</th><th></th></tr>
  <?php foreach ($students as $s) { ?>
  <tr>
    <td><?php echo e($s['student_no']); ?></td>
    <td><?php echo e($s['name']); ?></td>
    <td><?php echo e($s['program']); ?></td>
    <td>
      <form method="post" style="margin:0;" onsubmit="return confirm('Delete this student and all of their attendance and excuse records? This cannot be undone.');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="student_id" value="<?php echo $s['id']; ?>">
        <button type="submit" style="background:#b3261e;">Delete</button>
      </form>
    </td>
  </tr>
  <?php } ?>
</table>

<?php include "footer.php"; ?>