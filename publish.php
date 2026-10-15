<?php
require "db.php";
require_login(["admin"]);
$message = "";
$message_class = "good";

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $id = $_POST['event_id'];

    if ($_POST['action'] == "publish") {
        run("UPDATE events SET status = 'published' WHERE id = ? AND status = 'draft'", "i", [$id]);
        $message = "Event published. Staff can now check in students.";
    }

    if ($_POST['action'] == "reject") {
        run("DELETE FROM events WHERE id = ? AND status = 'draft'", "i", [$id]);
        $message = "Event request rejected and removed.";
    }

    if ($_POST['action'] == "close") {
        run("UPDATE events SET status = 'closed' WHERE id = ? AND status = 'published'", "i", [$id]);
        $message = "Event closed. No more check ins will be accepted.";
    }

    if ($_POST['action'] == "delete") {
        $result = run("SELECT status FROM events WHERE id = ?", "i", [$id]);
        $ev = mysqli_fetch_assoc($result);
        if ($ev && $ev['status'] == "closed") {
            run("DELETE FROM attendance WHERE event_id = ?", "i", [$id]);
            run("DELETE FROM excuses WHERE event_id = ?", "i", [$id]);
            run("DELETE FROM events WHERE id = ?", "i", [$id]);
            $message = "Event and its attendance records were deleted.";
        } else {
            $message = "Only closed events can be deleted. Close the event first.";
            $message_class = "bad";
        }
    }
}

$pending = [];
$result = run("SELECT e.*, u.name AS creator FROM events e JOIN users u ON u.id = e.created_by
               WHERE e.status = 'draft' ORDER BY e.event_date DESC, e.event_time DESC");
while ($row = mysqli_fetch_assoc($result)) {
    $pending[] = $row;
}

$others = [];
$result = run("SELECT e.*, u.name AS creator FROM events e JOIN users u ON u.id = e.created_by
               WHERE e.status <> 'draft' ORDER BY e.event_date DESC, e.event_time DESC");
while ($row = mysqli_fetch_assoc($result)) {
    $others[] = $row;
}

include "header.php";
?>

<h2>Publish Events</h2>
<p class="<?php echo $message_class; ?>"><?php echo e($message); ?></p>

<h3>Waiting for approval (<?php echo count($pending); ?>)</h3>
<table>
  <tr><th>Event</th><th>When</th><th>Venue</th><th>Type</th><th>Fine</th><th>Length</th><th>Created by</th><th>Actions</th></tr>
  <?php foreach ($pending as $ev) { ?>
  <tr>
    <td><?php echo e($ev['title']); ?></td>
    <td><?php echo e($ev['event_date'] . " " . substr($ev['event_time'], 0, 5)); ?></td>
    <td><?php echo e($ev['venue']); ?></td>
    <td><?php echo e($ev['category']); ?></td>
    <td><?php echo $ev['category'] == "mandatory" ? "PHP " . number_format($ev['fine_amount'], 2) : "-"; ?></td>
    <td><?php echo $ev['sessions'] == 2 ? "Half day event" : "Whole day event"; ?></td>
    <td><?php echo e($ev['creator']); ?></td>
    <td style="white-space:nowrap;">
      <div style="display:flex; gap:8px;">
        <form method="post" style="margin:0;">
          <input type="hidden" name="action" value="publish">
          <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
          <button type="submit">Publish</button>
        </form>
        <form method="post" style="margin:0;" onsubmit="return confirm('Reject and remove this event request?');">
          <input type="hidden" name="action" value="reject">
          <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
          <button type="submit" style="background:#b3261e;">Reject</button>
        </form>
      </div>
    </td>
  </tr>
  <?php } ?>
  <?php if (count($pending) == 0) { ?>
  <tr><td colspan="8">No events are waiting for approval.</td></tr>
  <?php } ?>
</table>

<h3>Published and closed events</h3>
<table>
  <tr><th>Event</th><th>When</th><th>Type</th><th>Length</th><th>Created by</th><th>Status</th><th>Attendance</th><th>Actions</th></tr>
  <?php foreach ($others as $ev) { ?>
  <tr>
    <td><?php echo e($ev['title']); ?></td>
    <td><?php echo e($ev['event_date'] . " " . substr($ev['event_time'], 0, 5)); ?></td>
    <td><?php echo e($ev['category']); ?></td>
    <td><?php echo $ev['sessions'] == 2 ? "Half day event" : "Whole day event"; ?></td>
    <td><?php echo e($ev['creator']); ?></td>
    <td><?php echo e($ev['status']); ?></td>
    <td><a href="attendance.php?event_id=<?php echo $ev['id']; ?>">View</a></td>
    <td>
      <?php if ($ev['status'] == "published") { ?>
      <form method="post" style="margin:0;"
            onsubmit="return confirm('Close this event? No more check ins will be accepted.');">
        <input type="hidden" name="action" value="close">
        <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
        <button type="submit" style="background:#b3261e;">Close event</button>
      </form>
      <?php } else { ?>
      <form method="post" style="margin:0;"
            onsubmit="return confirm('Delete this event and ALL its attendance records? This cannot be undone.');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
        <button type="submit" style="background:#b3261e;">Delete</button>
      </form>
      <?php } ?>
    </td>
  </tr>
  <?php } ?>
</table>

<?php include "footer.php"; ?>