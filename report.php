<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$role = $_SESSION['role'];
$all_students = [];
$student_id = 0;

if ($role === 'Parent') {
    // TC07 / TC36: a parent can ONLY see the child linked to the account.
    // Any ?student_id= in the URL is ignored. No fallback to another student.
    $student_id = (int)($_SESSION['student_id'] ?? 0);
} else {
    $all_students = $conn->query('SELECT student_id, student_name, class_name FROM students ORDER BY student_name ASC')->fetch_all(MYSQLI_ASSOC);
    $student_id = (int)($_GET['student_id'] ?? 0);
    if ($student_id <= 0 && $all_students) {
        $student_id = (int)$all_students[0]['student_id'];
    }
}

$student = null;
$evaluations = [];
$att = ['total' => 0, 'attended' => 0, 'absent' => 0, 'pct' => 0.0];
if ($student_id > 0) {
    $stmt = $conn->prepare('SELECT * FROM students WHERE student_id = ?');
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($student) {
        $stmt = $conn->prepare('SELECT * FROM progress_records WHERE student_id = ? ORDER BY progress_id DESC');
        $stmt->bind_param('i', $student_id);
        $stmt->execute();
        $evaluations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $att = attendance_summary($conn, $student_id);
    }
}

// Signature block names come from the active staff accounts (fallback to defaults)
function staff_name(mysqli $conn, string $role, string $fallback): string
{
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE role = ? AND status = 'Active' ORDER BY user_id ASC LIMIT 1");
    $stmt->bind_param('s', $role);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row['full_name'] ?? $fallback;
}
$teacher_name = staff_name($conn, 'Teacher', 'Class Teacher');
$head_name    = staff_name($conn, 'Management', 'Headmaster');

$page_title = $role === 'Parent' ? 'Child Progress Report' : 'Student Progress Report';
$active = 'report';
$safe_file = preg_replace('/[^a-zA-Z0-9]/', '_', $student['student_name'] ?? 'Report');
$extra_js = '<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadPDF() {
    var element = document.getElementById("pdfContent");
    html2pdf().set({
        margin: [10, 10, 10, 10],
        filename: "Student_Progress_Report_' . e($safe_file) . '.pdf",
        image: { type: "jpeg", quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: "mm", format: "a4", orientation: "portrait" }
    }).from(element).save();
}
</script>';
include __DIR__ . '/includes/header.php';
?>
<style>
    .report-card { max-width: 800px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,.05); }
    .header-title { border-bottom: 2px solid #0d6efd; padding-bottom: 12px; margin-bottom: 24px; }
    .info-box { background: #f8f9fa; border-radius: 6px; padding: 15px; margin-bottom: 24px; border: 1px solid #dee2e6; }
    .table-custom th { background-color: #e7f1ff !important; color: #0c63e4; }
    @media print { .report-card { box-shadow: none; padding: 0; max-width: 100%; } }
</style>

<div class="no-print d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 mx-auto" style="max-width:800px;">
    <?php if ($role !== 'Parent'): ?>
        <form method="GET" action="report.php" class="d-flex align-items-center gap-2">
            <select name="student_id" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Select student">
                <?php foreach ($all_students as $ast): ?>
                    <option value="<?= (int)$ast['student_id']; ?>" <?= (int)$ast['student_id'] === $student_id ? 'selected' : ''; ?>><?= e($ast['student_name']); ?> (<?= e($ast['class_name']); ?>)</option>
                <?php endforeach; ?>
            </select>
        </form>
    <?php else: ?>
        <span></span>
    <?php endif; ?>
    <?php if ($student): ?>
        <button onclick="downloadPDF()" class="btn btn-primary btn-sm fw-semibold"><i class="bi bi-download"></i> Download PDF Report</button>
    <?php endif; ?>
</div>

<?php if (!$student): ?>
    <div class="alert alert-warning mx-auto" style="max-width:800px;">No student record is available for this report. <?= $role === 'Parent' ? 'Please contact the kindergarten office to link your child to your account.' : ''; ?></div>
<?php else: ?>
<div class="report-card" id="pdfContent">
    <div class="header-title text-center">
        <h4 class="fw-bold text-uppercase mb-1">Tadika Perpaduan Pedas</h4>
        <p class="text-muted small mb-1">Kampus Pedas, Negeri Sembilan</p>
        <h5 class="fw-bold text-primary mt-3 mb-0">STUDENT PROGRESS EVALUATION REPORT</h5>
    </div>

    <div class="info-box">
        <div class="row">
            <div class="col-6">
                <p class="mb-1"><strong>Student Name:</strong> <?= e($student['student_name']); ?></p>
                <p class="mb-0"><strong>MyKid Number:</strong> <?= e($student['mykid'] ?? 'N/A'); ?></p>
            </div>
            <div class="col-6 text-end">
                <p class="mb-1"><strong>Class Category:</strong> <?= e($student['class_name']); ?></p>
                <p class="mb-0"><strong>Report Date:</strong> <?= date('d F Y'); ?></p>
            </div>
        </div>
    </div>

    <h6 class="fw-bold text-dark mb-2">Academic &amp; Development Progress</h6>
    <table class="table table-bordered table-custom align-middle mb-4">
        <thead>
            <tr><th style="width:25%;">Academic Term</th><th class="text-center">Reading</th><th class="text-center">Writing</th><th class="text-center">Counting</th><th class="text-center">Behavioral</th></tr>
        </thead>
        <tbody>
        <?php if (!$evaluations): ?>
            <tr><td colspan="5" class="text-center py-3 text-muted">No progress evaluation records available for this student.</td></tr>
        <?php else: foreach ($evaluations as $ev): ?>
            <tr>
                <td class="fw-semibold"><?= e($ev['academic_term'] ?? 'N/A'); ?></td>
                <td class="text-center"><span class="badge bg-info text-dark"><?= e($ev['reading_grade'] ?? '-'); ?></span></td>
                <td class="text-center"><span class="badge bg-info text-dark"><?= e($ev['writing_grade'] ?? '-'); ?></span></td>
                <td class="text-center"><span class="badge bg-info text-dark"><?= e($ev['counting_grade'] ?? '-'); ?></span></td>
                <td class="text-center"><span class="badge bg-success"><?= e($ev['behavior_grade'] ?? '-'); ?></span></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>

    <h6 class="fw-bold text-dark mb-2">Attendance Summary</h6>
    <table class="table table-bordered table-custom text-center mb-4">
        <thead><tr><th>School Days</th><th>Attended</th><th>Absent</th><th>Attendance Rate</th></tr></thead>
        <tbody><tr><td><?= $att['total']; ?></td><td><?= $att['attended']; ?></td><td><?= $att['absent']; ?></td><td class="fw-bold"><?= e($att['pct']); ?>%</td></tr></tbody>
    </table>

    <?php if (!empty($evaluations[0]['teacher_notes'])): ?>
    <div class="mb-4">
        <h6 class="fw-bold text-dark mb-1">Teacher Remarks (<?= e($evaluations[0]['academic_term'] ?? ''); ?>):</h6>
        <div class="p-3 bg-light border rounded text-secondary small"><?= nl2br(e($evaluations[0]['teacher_notes'])); ?></div>
    </div>
    <?php endif; ?>

    <div class="mt-5 pt-3">
        <div class="row text-center">
            <div class="col-6">
                <p class="mb-4">_______________________</p>
                <p class="fw-bold mb-0"><?= e($teacher_name); ?></p>
                <small class="text-muted">Class Teacher</small>
            </div>
            <div class="col-6">
                <p class="mb-4">_______________________</p>
                <p class="fw-bold mb-0"><?= e($head_name); ?></p>
                <small class="text-muted">Headmaster / Administrator</small>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
