<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$role = $_SESSION['role'];
$name = $_SESSION['full_name'] ?? $_SESSION['username'];

/** Run a single-value COUNT query. */
function scalar(mysqli $conn, string $sql, string $types = '', array $params = []): int
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    return (int)($row[0] ?? 0);
}

$page_title = 'Dashboard Overview';
$active = 'dashboard';

// ---------------------------------------------------------------- PARENT VIEW (FR9)
if ($role === 'Parent') {
    $student = null; $latest = null; $att = ['total' => 0, 'attended' => 0, 'absent' => 0, 'pct' => 0.0];
    $sid = (int)($_SESSION['student_id'] ?? 0);
    if ($sid > 0) {
        $st = $conn->prepare('SELECT * FROM students WHERE student_id = ?');
        $st->bind_param('i', $sid);
        $st->execute();
        $student = $st->get_result()->fetch_assoc();
        $st->close();

        if ($student) {
            $ev = $conn->prepare('SELECT * FROM progress_records WHERE student_id = ? ORDER BY progress_id DESC LIMIT 1');
            $ev->bind_param('i', $sid);
            $ev->execute();
            $latest = $ev->get_result()->fetch_assoc();
            $ev->close();
            $att = attendance_summary($conn, $sid);
        }
    }
    include __DIR__ . '/includes/header.php';
    ?>
    <div class="alert alert-primary border-0 shadow-sm bg-white border-start border-4 border-primary d-flex align-items-center p-4 mb-4">
        <img src="img/logo.png" alt="Logo" style="width:55px;height:55px;object-fit:contain;" class="me-3" onerror="this.style.display='none'">
        <div>
            <h4 class="fw-bold text-primary mb-1">Welcome, <?= e($name); ?>!</h4>
            <p class="text-muted mb-0">Follow your child's learning progress, attendance and teacher feedback at Tadika Perpaduan Pedas.</p>
        </div>
    </div>

    <?php if (!$student): ?>
        <div class="alert alert-warning">No student record is linked to your account yet. Please contact the kindergarten office.</div>
    <?php else: ?>
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card stat-card p-3 bg-white h-100">
                    <p class="text-muted small mb-1 text-uppercase fw-semibold">My Child</p>
                    <h4 class="fw-bold text-primary mb-1"><?= e($student['student_name']); ?></h4>
                    <div class="text-muted small">Class: <span class="badge bg-secondary"><?= e($student['class_name']); ?></span></div>
                    <div class="text-muted small mt-1">MyKid: <?= e($student['mykid'] ?? '-'); ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-3 bg-white h-100">
                    <p class="text-muted small mb-1 text-uppercase fw-semibold">Attendance Rate</p>
                    <h2 class="fw-bold text-success mb-1"><?= e($att['pct']); ?>%</h2>
                    <div class="progress mb-2" style="height:8px;"><div class="progress-bar bg-success" style="width:<?= (float)$att['pct']; ?>%"></div></div>
                    <div class="text-muted small"><?= $att['attended']; ?> of <?= $att['total']; ?> school days attended (<?= $att['absent']; ?> absent)</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-3 bg-white h-100">
                    <p class="text-muted small mb-2 text-uppercase fw-semibold">Quick Actions</p>
                    <a href="report.php" class="btn btn-primary btn-sm mb-2"><i class="bi bi-file-earmark-pdf me-1"></i> View / Download Report</a>
                    <a href="my_attendance.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-calendar2-week me-1"></i> Attendance History</a>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-graph-up-arrow text-primary me-2"></i>Latest Progress Evaluation</h5>
            </div>
            <div class="card-body">
                <?php if (!$latest): ?>
                    <p class="text-muted mb-0">No progress evaluation has been recorded for your child yet.</p>
                <?php else: ?>
                    <p class="text-muted small mb-3"><?= e($latest['academic_term']); ?></p>
                    <div class="row g-3 text-center mb-3">
                        <?php foreach (['Reading' => 'reading_grade', 'Writing' => 'writing_grade', 'Counting' => 'counting_grade', 'Behavior' => 'behavior_grade'] as $label => $col): ?>
                            <div class="col-6 col-md-3">
                                <div class="border rounded-3 p-3 bg-light">
                                    <div class="text-muted small"><?= e($label); ?></div>
                                    <div class="fs-2 fw-bold text-primary"><?= e($latest[$col] ?? '-'); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <h6 class="fw-bold">Teacher Remarks</h6>
                    <div class="p-3 bg-light border rounded text-secondary small"><?= e($latest['teacher_notes'] ?: 'No remarks provided.'); ?></div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit();
}

// ---------------------------------------------------------------- STAFF VIEW (Management / Teacher)
$total_students    = scalar($conn, 'SELECT COUNT(*) FROM students');
$total_evaluations = scalar($conn, 'SELECT COUNT(*) FROM progress_records');
$term              = current_term();
$pending_evals     = scalar($conn, 'SELECT COUNT(*) FROM students s WHERE NOT EXISTS (SELECT 1 FROM progress_records p WHERE p.student_id = s.student_id AND p.academic_term = ?)', 's', [$term]);

$today = ['Present' => 0, 'Absent' => 0, 'Late' => 0];
$res = $conn->query("SELECT status, COUNT(*) AS c FROM attendance WHERE attendance_date = CURDATE() GROUP BY status");
while ($res && ($r = $res->fetch_assoc())) {
    $today[$r['status']] = (int)$r['c'];
}
$recorded_today = array_sum($today);
$not_recorded   = max(0, $total_students - $recorded_today);

$teacher_count = $parent_count = 0;
if ($role === 'Management') {
    $teacher_count = scalar($conn, "SELECT COUNT(*) FROM users WHERE role = 'Teacher' AND status = 'Active'");
    $parent_count  = scalar($conn, "SELECT COUNT(*) FROM users WHERE role = 'Parent' AND status = 'Active'");
}

$recent = [];
$res = $conn->query('SELECT * FROM students ORDER BY student_id DESC LIMIT 5');
while ($res && ($r = $res->fetch_assoc())) {
    $recent[] = $r;
}

include __DIR__ . '/includes/header.php';
?>
<div class="alert alert-primary border-0 shadow-sm bg-white border-start border-4 border-primary d-flex align-items-center p-4 mb-4">
    <img src="img/logo.png" alt="Logo" style="width:55px;height:55px;object-fit:contain;" class="me-3" onerror="this.style.display='none'">
    <div>
        <h4 class="fw-bold text-primary mb-1">Welcome back, <?= e($name); ?>!</h4>
        <p class="text-muted mb-0">Kindergarten Student Progress Management System - Tadika Perpaduan Pedas. Use the menu or the shortcuts below to manage student records.</p>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card p-3 bg-white h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div><p class="text-muted small mb-1 text-uppercase fw-semibold">Enrolled Students</p><h2 class="fw-bold text-primary mb-0"><?= $total_students; ?></h2></div>
                <div class="fs-1 text-primary opacity-50"><i class="bi bi-people"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card p-3 bg-white h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div><p class="text-muted small mb-1 text-uppercase fw-semibold">Progress Records</p><h2 class="fw-bold text-success mb-0"><?= $total_evaluations; ?></h2></div>
                <div class="fs-1 text-success opacity-50"><i class="bi bi-journal-text"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card p-3 bg-white h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div><p class="text-muted small mb-1 text-uppercase fw-semibold">Pending Evaluations</p><h2 class="fw-bold text-warning mb-0"><?= $pending_evals; ?></h2><div class="text-muted small"><?= e($term); ?></div></div>
                <div class="fs-1 text-warning opacity-50"><i class="bi bi-hourglass-split"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card stat-card p-3 bg-white h-100">
            <p class="text-muted small mb-1 text-uppercase fw-semibold">Today's Attendance</p>
            <div class="d-flex gap-2 flex-wrap">
                <span class="badge bg-success">Present <?= $today['Present']; ?></span>
                <span class="badge bg-warning text-dark">Late <?= $today['Late']; ?></span>
                <span class="badge bg-danger">Absent <?= $today['Absent']; ?></span>
                <span class="badge bg-secondary">Not recorded <?= $not_recorded; ?></span>
            </div>
        </div>
    </div>
</div>

<?php if ($role === 'Management'): ?>
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card stat-card p-3 bg-white"><div class="d-flex justify-content-between align-items-center">
            <div><p class="text-muted small mb-1 text-uppercase fw-semibold">Active Teachers</p><h3 class="fw-bold mb-0"><?= $teacher_count; ?></h3></div>
            <a href="users.php?role=Teacher" class="btn btn-sm btn-outline-primary">Manage</a></div></div>
    </div>
    <div class="col-md-6">
        <div class="card stat-card p-3 bg-white"><div class="d-flex justify-content-between align-items-center">
            <div><p class="text-muted small mb-1 text-uppercase fw-semibold">Registered Parents</p><h3 class="fw-bold mb-0"><?= $parent_count; ?></h3></div>
            <a href="users.php?role=Parent" class="btn btn-sm btn-outline-primary">Manage</a></div></div>
    </div>
</div>
<?php endif; ?>

<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="evaluations.php" class="btn btn-primary"><i class="bi bi-journal-check me-1"></i> Input Progress</a>
    <a href="attendance.php" class="btn btn-primary"><i class="bi bi-calendar-check me-1"></i> Mark Attendance</a>
    <a href="students.php" class="btn btn-outline-primary"><i class="bi bi-people me-1"></i> Student Profiles</a>
    <a href="report.php" class="btn btn-outline-primary"><i class="bi bi-file-earmark-pdf me-1"></i> Student Reports</a>
</div>

<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Recently Registered Students</h5>
        <a href="students.php" class="btn btn-sm btn-outline-primary">View All Students</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light"><tr><th class="ps-4">ID</th><th>Student Name</th><th>MyKid Number</th><th>Class</th><th>Guardian Phone</th></tr></thead>
            <tbody>
            <?php if (!$recent): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">No student records found.</td></tr>
            <?php else: foreach ($recent as $rs): ?>
                <tr>
                    <td class="ps-4 fw-semibold">#<?= (int)$rs['student_id']; ?></td>
                    <td><?= e($rs['student_name']); ?></td>
                    <td><?= e($rs['mykid'] ?? '-'); ?></td>
                    <td><span class="badge bg-secondary"><?= e($rs['class_name']); ?></span></td>
                    <td><?= e($rs['guardian_phone'] ?? '-'); ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
