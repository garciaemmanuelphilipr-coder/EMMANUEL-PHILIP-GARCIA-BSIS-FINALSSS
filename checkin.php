<?php
require "db.php";
require_login(["admin", "staff"]);
$message = "";
$message_class = "good";
$event_id = $_POST['event_id'] ?? "";
$slot = $_POST['slot'] ?? "am_in";
if (!in_array($slot, ["am_in", "am_out", "pm_in", "pm_out"])) {
    $slot = "am_in";
}
if ($_SERVER['REQUEST_METHOD'] == "POST") {
    if ($_POST['action'] == "scan") {
        $code = trim($_POST['code']);
        $parts = preg_split('/[|;,]/', $code);
        $code = trim($parts[0]);

        $result = run("SELECT * FROM students WHERE student_no = ?", "s", [$code]);
        $student = mysqli_fetch_assoc($result);

        $result = run("SELECT status, sessions FROM events WHERE id = ?", "i", [$event_id]);
        $event = mysqli_fetch_assoc($result);

        if ($code == "") {
            $message = "Nothing was scanned.";
            $message_class = "bad";
        } elseif (!$event || $event['status'] != "published") {
            $message = "This event is not open for check in.";
            $message_class = "bad";
        } elseif (!isset(slots_for($event['sessions'])[$slot])) {
            $message = "This half day event only has In and Out scans.";
            $message_class = "bad";
        } elseif (!$student) {
            $message = "No student matches this QR code: \"" . $code . "\"";
            $message_class = "bad";
        } else {
            $label = slots_for($event['sessions'])[$slot];
            $dup = run("SELECT id FROM attendance WHERE event_id = ? AND student_id = ? AND slot = ?",
                       "iis", [$event_id, $student['id'], $slot]);
            if (mysqli_num_rows($dup) > 0) {
                $message = $student['name'] . " already has " . $label . " recorded.";
                $message_class = "bad";
            } else {
                run("INSERT INTO attendance (event_id, student_id, slot, scanned_by) VALUES (?, ?, ?, ?)",
                    "iisi", [$event_id, $student['id'], $slot, $_SESSION['user_id']]);
                $message = $student['name'] . " (" . $student['program'] . ") - " . $label . " recorded.";
            }
        }
    }
}
$result = run("SELECT * FROM events WHERE status = 'published' ORDER BY event_date DESC, event_time DESC");
$events = [];
while ($row = mysqli_fetch_assoc($result)) {
    $events[] = $row;
}
if ($event_id == "" && count($events) > 0) {
    $event_id = $events[0]['id'];
}
$cur_sessions = 4;
foreach ($events as $ev) {
    if ($ev['id'] == $event_id) {
        $cur_sessions = $ev['sessions'];
    }
}
$cur_slots = slots_for($cur_sessions);
if (!isset($cur_slots[$slot])) {
    $slot = "am_in";
}
$recent = [];
$total = 0;
if ($event_id != "") {
    $result = run("SELECT s.name, s.program, a.slot, a.scanned_at FROM attendance a
                   JOIN students s ON s.id = a.student_id
                   WHERE a.event_id = ? ORDER BY a.id DESC LIMIT 10", "i", [$event_id]);
    while ($row = mysqli_fetch_assoc($result)) {
        $recent[] = $row;
    }
    $result = run("SELECT COUNT(*) AS n FROM attendance WHERE event_id = ? AND slot = ?",
                  "is", [$event_id, $slot]);
    $total = mysqli_fetch_assoc($result)['n'];
}
include "header.php";
?>
<h2>Staff check in</h2>
<?php if ($message != "") { ?>
  <div class="card <?php echo $message_class; ?>" style="font-size: 20px;">
    <?php echo e($message); ?>
  </div>
<?php } ?>
<?php if (count($events) == 0) { ?>
  <p>There is no event open for check in. Ask the organizer to publish an event.</p>
<?php } else { ?>
<form method="post" id="scanForm">
  <label>Event
    <select name="event_id" id="event_id" onchange="updateSlots()">
      <?php foreach ($events as $ev) { ?>
        <option value="<?php echo $ev['id']; ?>" data-sessions="<?php echo $ev['sessions']; ?>"
                <?php if ($ev['id'] == $event_id) echo "selected"; ?>>
          <?php echo e($ev['title'] . " (" . $ev['event_date'] . ")"); ?>
          <?php echo $ev['sessions'] == 2 ? " - Half day" : " - Whole day"; ?>
        </option>
      <?php } ?>
    </select>
  </label>
  <label>Session
    <select name="slot" id="slot">
      <?php foreach ($cur_slots as $key => $label) { ?>
        <option value="<?php echo $key; ?>" <?php if ($key == $slot) echo "selected"; ?>>
          <?php echo $label; ?>
        </option>
      <?php } ?>
    </select>
  </label>
  <label>Scan student ID (the scanner records the session automatically)
    <input type="text" name="code" id="code" autofocus autocomplete="off">
  </label>

  <button type="submit" name="action" value="scan" id="scanBtn">Check in</button>
  <button type="button" id="camBtn" onclick="toggleCamera()">Use camera</button>
</form>

<div id="reader" style="max-width: 320px; margin-top: 12px;"></div>

<h3><?php echo e($cur_slots[$slot]); ?> scanned so far: <?php echo $total; ?></h3>
<table>
  <tr><th>Student</th><th>Program</th><th>Session</th><th>Time</th></tr>
  <?php foreach ($recent as $r) { ?>
  <tr>
    <td><?php echo e($r['name']); ?></td>
    <td><?php echo e($r['program']); ?></td>
    <td><?php echo e($cur_slots[$r['slot']] ?? $r['slot']); ?></td>
    <td><?php echo e(substr($r['scanned_at'], 11, 8)); ?></td>
  </tr>
  <?php } ?>
</table>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script>
function updateSlots() {
  var sel = document.getElementById("event_id");
  var two = sel.options[sel.selectedIndex].getAttribute("data-sessions") == "2";
  var list = two
    ? [["am_in", "In"], ["am_out", "Out"]]
    : [["am_in", "AM In"], ["am_out", "AM Out"], ["pm_in", "PM In"], ["pm_out", "PM Out"]];
  var slot = document.getElementById("slot");
  var current = slot.value;
  slot.innerHTML = "";
  for (var i = 0; i < list.length; i++) {
    var opt = document.createElement("option");
    opt.value = list[i][0];
    opt.text = list[i][1];
    if (list[i][0] == current) opt.selected = true;
    slot.appendChild(opt);
  }
}

var scanner = null;
var done = false;

function toggleCamera() {
  if (scanner) {     
    sessionStorage.removeItem("cam");
    scanner.stop().then(function () {
      scanner.clear();
      scanner = null;
      document.getElementById("camBtn").textContent = "Use camera";
    });
    return;
  }
  startCamera();
}

function startCamera() {
  done = false;
  scanner = new Html5Qrcode("reader");
  scanner.start(
    { facingMode: "environment" },
    { fps: 10, qrbox: 220 },
    function (text) {
      if (done) return;  
      done = true;
      sessionStorage.setItem("cam", "1");  
      document.getElementById("code").value = text;
      document.getElementById("scanBtn").click();
    }
  ).then(function () {
    document.getElementById("camBtn").textContent = "Stop camera";
  }).catch(function () {
    scanner = null;
    sessionStorage.removeItem("cam");
    alert("Camera not available. Use a USB scanner or type the number.");
  });
}

if (sessionStorage.getItem("cam") == "1") {
  startCamera();
}
</script>

<?php } ?>

<?php include "footer.php"; ?>