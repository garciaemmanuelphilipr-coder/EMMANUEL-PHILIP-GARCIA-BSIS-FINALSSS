<?php
require "db.php";
require_login(["organizer", "admin"]);
$role = $_SESSION['role'];
$message = "";
$message_class = "good";

if ($_SERVER['REQUEST_METHOD'] == "POST") {

    if ($_POST['action'] == "add" && $role == "organizer") {
        $fine = 0;
        if ($_POST['category'] == "mandatory") {
            $fine = (float) $_POST['fine_amount'];
        }
        $sessions = ($_POST['sessions'] == "2") ? 2 : 4;
        run("INSERT INTO events (title, event_date, event_time, venue, category, fine_amount, sessions, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            "sssssdii",
            [$_POST['title'], $_POST['event_date'], $_POST['event_time'], $_POST['venue'],
             $_POST['category'], $fine, $sessions, $_SESSION['user_id']]);
        $message = "Event sent for approval. The admin will publish it.";
    }
    if ($_POST['action'] == "close" && $role == "organizer") {
        $result = run("SELECT status, created_by FROM events WHERE id = ?", "i", [$_POST['event_id']]);
        $ev = mysqli_fetch_assoc($result);
        if ($ev && $ev['created_by'] == $_SESSION['user_id'] && $ev['status'] == "published") {
            run("UPDATE events SET status = 'closed' WHERE id = ?", "i", [$_POST['event_id']]);
            $message = "Event closed. No more check ins will be accepted.";
        } else {
            $message = "You can only close your own events that are open for check in.";
            $message_class = "bad";
        }
    }
    if ($_POST['action'] == "delete" && $role == "organizer") {
        $result = run("SELECT status, created_by FROM events WHERE id = ?", "i", [$_POST['event_id']]);
        $ev = mysqli_fetch_assoc($result);
        if ($ev && $ev['created_by'] == $_SESSION['user_id'] && $ev['status'] == "draft") {
            run("DELETE FROM events WHERE id = ?", "i", [$_POST['event_id']]);
            $message = "Event deleted.";
        } else {
            $message = "You can only delete your own events that are waiting for approval.";
            $message_class = "bad";
        }
    }
}
if ($role == "organizer") {
    $result = run("SELECT e.*, u.name AS creator FROM events e JOIN users u ON u.id = e.created_by
                   WHERE e.created_by = ? ORDER BY e.event_date DESC, e.event_time DESC", "i", [$_SESSION['user_id']]);
} else {
    $result = run("SELECT e.*, u.name AS creator FROM events e JOIN users u ON u.id = e.created_by
                   ORDER BY e.event_date DESC, e.event_time DESC");
}
$events = [];
while ($row = mysqli_fetch_assoc($result)) {
    $events[] = $row;
}
$forms = [
    4 => "Whole day event",
    2 => "Half day event",
];
include "header.php";
?>

<h2>Events</h2>
<p class="<?php echo $message_class; ?>"><?php echo e($message); ?></p>

<?php if ($role == "organizer") { ?>
<p>New events are sent to the admin for approval. Staff can only scan once the admin publishes the event.</p>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 16px; align-items: start;">
<?php foreach ($forms as $n => $title) { ?>
  <div class="card" style="margin: 0;">
    <h3 style="margin-top:0;"><?php echo $title; ?></h3>
    <form method="post">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="sessions" value="<?php echo $n; ?>">
      <label>Event name <input type="text" name="title" required></label>
      <label>Venue <input type="text" name="venue" required></label>
      <label>Date <input type="date" name="event_date" required></label>
      <label>Time <input type="time" name="event_time" required></label>
      <label>Type
        <select name="category" id="category_<?php echo $n; ?>" onchange="toggleFine(<?php echo $n; ?>)">
          <option value="optional">Optional event</option>
          <option value="mandatory">Mandatory event</option>
        </select>
      </label>
      <label id="fine_box_<?php echo $n; ?>" style="display:none;">Fine for absence (PHP)
        <input type="number" name="fine_amount" min="0" value="0">
      </label>
      <button type="submit">Send for approval</button>
    </form>
  </div>
<?php } ?>
</div>
<br>
<?php } ?>

<table>
  <tr>
    <th>Event</th><th>When</th><th>Venue</th><th>Type</th><th>Fine</th><th>Length</th>
    <?php if ($role == "admin") { ?><th>Created by</th><?php } ?>
    <th>Status</th><th>Attendance</th>
    <?php if ($role == "organizer") { ?><th>Actions</th><?php } ?>
  </tr>
  <?php foreach ($events as $ev) { ?>
  <tr>
    <td><?php echo e($ev['title']); ?></td>
    <td><?php echo e($ev['event_date'] . " " . substr($ev['event_time'], 0, 5)); ?></td>
    <td><?php echo e($ev['venue']); ?></td>
    <td><?php echo e($ev['category']); ?></td>
    <td><?php echo $ev['category'] == "mandatory" ? "PHP " . number_format($ev['fine_amount'], 2) : "-"; ?></td>
    <td><?php echo $ev['sessions'] == 2 ? "Half day event" : "Whole day event"; ?></td>
    <?php if ($role == "admin") { ?><td><?php echo e($ev['creator']); ?></td><?php } ?>
    <td><?php echo $ev['status'] == "draft" ? "Waiting for approval" : e($ev['status']); ?></td>
    <td>
      <?php if ($ev['status'] != "draft") { ?>
        <a href="attendance.php?event_id=<?php echo $ev['id']; ?>">View</a>
      <?php } else { echo "-"; } ?>
    </td>
    <?php if ($role == "organizer") { ?>
    <td style="white-space:nowrap;">
      <?php if ($ev['status'] == "draft") { ?>
      <form method="post" style="margin:0;" onsubmit="return confirm('Delete this event?');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
        <button type="submit" style="background:#b3261e;">Delete</button>
      </form>
      <?php } ?>

      <?php if ($ev['status'] == "published") { ?>
      <form method="post" style="margin:0;"
            onsubmit="return confirm('Close this event? No more check ins will be accepted.');">
        <input type="hidden" name="action" value="close">
        <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
        <button type="submit" style="background:#b3261e;">Close event</button>
      </form>
      <?php } ?>
    </td>
    <?php } ?>
  </tr>
  <?php } ?>
</table>

<script>
function toggleFine(n) {
  var box = document.getElementById("fine_box_" + n);
  var cat = document.getElementById("category_" + n);
  if (box && cat) {
    box.style.display = (cat.value == "mandatory") ? "block" : "none";
  }
}
toggleFine(4);
toggleFine(2);
</script>

<?php include "footer.php"; ?>