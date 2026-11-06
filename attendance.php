<?php
require_once __DIR__ . '/includes/auth.php';
require_roles(['Management', 'Teacher']); // Parents are blocked

$error = '';
$today = date('Y-m-d');

// Selected date / class come from the URL (reloaded by the date picker) or from the submitted form.
$att_date = trim($_POST['attendance_date'] ?? $_GET['date'] ?? $today);
if (!valid_date($att_date)) {
    $att_date = $today;
}
$class_filter = trim($_POST['class_filter'] ?? $_GET['class'] ?? '');
if ($class_filter !== '' && !in_array($class_filter, CLASS_OPTIONS, true)) {
    $class_filter = '';
}

// ---- SAVE attendance for the whole class (FR6) -----------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_enforce();
    if ($att_date > $today) {
        // TC32: future dates are rejected
        $error = 'Attendance cannot be recorded for future dates.';
    } elseif (!isset($_POST['status']) || !is_array($_POST['status']) || !$_POST['status']) {
        $error = 'No students to save.';
    } else {
        $valid_ids = array_column($conn->query('SELECT student_id FROM students')->fetch_all(MYSQLI_ASSOC), 'student_id');
        $valid_ids = array_map('intval', $valid_ids);

        $stmt = $conn->prepare('INSERT INTO attendance (student_id, attendance_date, status, remarks) VALUES (?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks)');
        $conn->begin_transaction(); // all-or-nothing save (NFR4)
        try {
            foreach ($_POST['status'] as $sid => $status) {
                $sid = (int)$sid;
                if (!in_array($sid, $valid_ids, true) || !in_array($status, ATTENDANCE_STATUSES, true)) {
                    throw new RuntimeException('Invalid attendance data submitted.');
                }
                $remarks = mb_substr(trim((string)($_POST['remarks'][$sid] ?? '')), 0, 255);
                $stmt->bind_param('isss', $sid, $att_date, $status, $remarks);
                $stmt->execute();
            }
            $conn->commit();
            $stmt->close();
            flash('success', 'Daily attendance recorded successfully!');
            redirect('attendance.php?date=' . urlencode($att_date) . ($class_filter !== '' ? '&class=' . urlencode($class_filter) : ''));
        } catch (Throwable $ex) {
            $conn->rollback();
            error_log('Attendance save failed: ' . $ex->getMessage());
            $error = 'Failed to save attendance. Please try again.';
        }
    }
}

// ---- Load students + any existing records for the selected date ---------------------
if ($class_filter !== '') {
    $stmt = $conn->prepare('SELECT s.student_id, s.student_name, s.class_name, a.status, a.remarks
                            FROM students s LEFT JOIN attendance a ON a.student_id = s.student_id AND a.attendance_date = ?
                            WHERE s.class_name = ? ORDER BY s.student_name ASC');
    $stmt->bind_param('ss', $att_date, $class_filter);
} else {
    $stmt = $conn->prepare('SELECT s.student_id, s.student_name, s.class_name, a.status, a.remarks
                            FROM students s LEFT JOIN attendance a ON a.student_id = s.student_id AND a.attendance_date = ?
                            ORDER BY s.student_name ASC');
    $stmt->bind_param('s', $att_date);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$already_saved = count(array_filter($rows, fn($r) => $r['status'] !== null));

$page_title = 'Class Attendance Tracking';
$active = 'attendance';
$extra_js = <<<'JS'
<script>
function reloadAttendance() {
    var d = document.getElementById('attendance_date').value;
    var c = document.getElementById('class_filter').value;
    if (!d) return;
    window.location.href = 'attendance.php?date=' + encodeURIComponent(d) + (c ? '&class=' + encodeURIComponent(c) : '');
}
function markAll(status) {
    document.querySelectorAll('input[type=radio][value="' + status + '"]').forEach(function (r) { r.checked = true; });
}
</script>
JS;
include __DIR__ . '/includes/header.php';
?>
<?php if ($error !== ''): ?><?= alert_html('danger', $error); ?><?php endif; ?>

<form method="POST" action="attendance.php">
    <?= csrf_field(); ?>
    <div class="row g-3 mb-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label fw-semibold" for="attendance_date">Select Date</label>
            <input type="date" name="attendance_date" id="attendance_date" class="form-control" value="<?= e($att_date); ?>" onchange="reloadAttendance()" required>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold" for="class_filter">Class</label>
            <select name="class_filter" id="class_filter" class="form-select" onchange="reloadAttendance()">
                <option value="">All Classes</option>
                <?php foreach (CLASS_OPTIONS as $c): ?>
                    <option value="<?= e($c); ?>" <?= $class_filter === $c ? 'selected' : ''; ?>><?= e($c); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button type="button" class="btn btn-outline-success btn-sm" onclick="markAll('Present')">All Present</button>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="markAll('Absent')">All Absent</button>
        </div>
    </div>

    <?php if ($already_saved > 0): ?>
        <div class="alert alert-info py-2 small mb-3"><i class="bi bi-info-circle me-1"></i> Attendance already recorded for <?= $already_saved; ?> of <?= count($rows); ?> students on this date. Saving will update the records.</div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-primary"><tr><th>Student Name</th><th>Class</th><th>Status</th><th>Remarks / Reason</th></tr></thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="4" class="text-center py-3 text-muted">No students found.</td></tr>
                <?php else: foreach ($rows as $s): $sid = (int)$s['student_id']; $cur = $s['status'] ?? 'Present'; ?>
                    <tr>
                        <td><?= e($s['student_name']); ?></td>
                        <td><?= e($s['class_name']); ?></td>
                        <td class="text-nowrap">
                            <?php foreach (ATTENDANCE_STATUSES as $st): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="status[<?= $sid; ?>]" id="st<?= $sid . $st; ?>" value="<?= $st; ?>" <?= $cur === $st ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="st<?= $sid . $st; ?>"><?= $st; ?></label>
                                </div>
                            <?php endforeach; ?>
                        </td>
                        <td><input type="text" name="remarks[<?= $sid; ?>]" class="form-control form-control-sm" maxlength="255" placeholder="Optional notes (e.g. MC)" value="<?= e($s['remarks'] ?? ''); ?>"></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <button type="submit" class="btn btn-primary fw-semibold" <?= $rows ? '' : 'disabled'; ?>><i class="bi bi-save me-1"></i> Save Attendance</button>
</form>
<?php include __DIR__ . '/includes/footer.php'; ?>
