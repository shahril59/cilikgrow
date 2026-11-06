<?php
require_once __DIR__ . '/includes/auth.php';
require_roles(['Parent']); // TC12 / TC31: parents only, own child only

$student_id = (int)($_SESSION['student_id'] ?? 0);
$history = [];
$student_name = '';
$summary = ['total' => 0, 'attended' => 0, 'absent' => 0, 'pct' => 0.0];

if ($student_id > 0) {
    // Always filtered by the child linked to the logged-in parent - never by a URL value.
    $stmt = $conn->prepare('SELECT a.attendance_date, a.status, a.remarks, s.student_name
                            FROM attendance a JOIN students s ON a.student_id = s.student_id
                            WHERE a.student_id = ? ORDER BY a.attendance_date DESC');
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $summary = attendance_summary($conn, $student_id);

    $q = $conn->prepare('SELECT student_name FROM students WHERE student_id = ?');
    $q->bind_param('i', $student_id);
    $q->execute();
    $student_name = $q->get_result()->fetch_assoc()['student_name'] ?? '';
    $q->close();
}

$page_title = 'Child Attendance History';
$active = 'my_attendance';
include __DIR__ . '/includes/header.php';
?>
<?php if ($student_id <= 0): ?>
    <div class="alert alert-warning">No student record is linked to your account yet. Please contact the kindergarten office.</div>
<?php else: ?>
    <h5 class="fw-bold text-primary mb-3"><?= e($student_name); ?></h5>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="card stat-card p-3 text-center"><div class="text-muted small">School Days</div><div class="fs-3 fw-bold"><?= $summary['total']; ?></div></div></div>
        <div class="col-6 col-md-3"><div class="card stat-card p-3 text-center"><div class="text-muted small">Attended</div><div class="fs-3 fw-bold text-success"><?= $summary['attended']; ?></div></div></div>
        <div class="col-6 col-md-3"><div class="card stat-card p-3 text-center"><div class="text-muted small">Absent</div><div class="fs-3 fw-bold text-danger"><?= $summary['absent']; ?></div></div></div>
        <div class="col-6 col-md-3"><div class="card stat-card p-3 text-center"><div class="text-muted small">Attendance Rate</div><div class="fs-3 fw-bold text-primary"><?= e($summary['pct']); ?>%</div></div></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-primary"><tr><th>Date</th><th>Attendance Status</th><th>Remarks</th></tr></thead>
                <tbody>
                <?php if (!$history): ?>
                    <tr><td colspan="3" class="text-center py-3 text-muted">No attendance history records found.</td></tr>
                <?php else: foreach ($history as $row):
                    $badge = ['Present' => 'success', 'Late' => 'warning text-dark', 'Absent' => 'danger'][$row['status']] ?? 'secondary'; ?>
                    <tr>
                        <td><?= e(date('d M Y (D)', strtotime($row['attendance_date']))); ?></td>
                        <td><span class="badge bg-<?= $badge; ?>"><?= e($row['status']); ?></span></td>
                        <td><?= e($row['remarks'] !== null && $row['remarks'] !== '' ? $row['remarks'] : '-'); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
