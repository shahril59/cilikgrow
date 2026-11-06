<?php
require_once __DIR__ . '/includes/auth.php';
require_roles(['Management', 'Teacher']); // Parents are blocked

/** Run a prepared SELECT with optional bound params and return all rows. */
function q(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// ---- Filters ---------------------------------------------------------------------
$term = trim($_GET['term'] ?? '');
if (!in_array($term, TERM_OPTIONS, true)) {
    $term = current_term();
}
$class_filter = trim($_GET['class'] ?? '');
if ($class_filter !== '' && !in_array($class_filter, CLASS_OPTIONS, true)) {
    $class_filter = '';
}
$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to'] ?? '');
if ($from !== '' && !valid_date($from)) { $from = ''; }
if ($to !== '' && !valid_date($to)) { $to = ''; }

$classes = $class_filter !== '' ? [$class_filter] : CLASS_OPTIONS;
$domains = ['reading_grade' => 'Reading', 'writing_grade' => 'Writing', 'counting_grade' => 'Counting', 'behavior_grade' => 'Behavior'];

// ---- Data ------------------------------------------------------------------------
$student_counts = array_column(q($conn, 'SELECT class_name, COUNT(*) AS c FROM students GROUP BY class_name'), 'c', 'class_name');

$evaluated = array_column(q($conn,
    'SELECT s.class_name, COUNT(DISTINCT p.student_id) AS c
     FROM progress_records p JOIN students s ON s.student_id = p.student_id
     WHERE p.academic_term = ? GROUP BY s.class_name', 's', [$term]), 'c', 'class_name');

// Grade distribution per class + domain (column names come from the fixed whitelist above)
$dist = [];
foreach ($domains as $col => $label) {
    $rows = q($conn,
        "SELECT s.class_name, p.$col AS g, COUNT(*) AS c
         FROM progress_records p JOIN students s ON s.student_id = p.student_id
         WHERE p.academic_term = ? GROUP BY s.class_name, p.$col", 's', [$term]);
    foreach ($rows as $r) {
        $dist[$r['class_name']][$col][$r['g']] = (int)$r['c'];
    }
}

// Attendance per class (optional date range). Attended = Present + Late.
$where = '1=1'; $types = ''; $params = [];
if ($from !== '') { $where .= ' AND a.attendance_date >= ?'; $types .= 's'; $params[] = $from; }
if ($to !== '')   { $where .= ' AND a.attendance_date <= ?'; $types .= 's'; $params[] = $to; }
$att = [];
foreach (q($conn,
    "SELECT s.class_name,
            SUM(a.status IN ('Present','Late')) AS attended,
            SUM(a.status = 'Absent') AS absent,
            COUNT(*) AS total
     FROM attendance a JOIN students s ON s.student_id = a.student_id
     WHERE $where GROUP BY s.class_name", $types, $params) as $r) {
    $att[$r['class_name']] = $r;
}

// Students with no evaluation for the selected term
$pending = [];
foreach (q($conn,
    'SELECT s.class_name, s.student_name FROM students s
     WHERE NOT EXISTS (SELECT 1 FROM progress_records p WHERE p.student_id = s.student_id AND p.academic_term = ?)
     ORDER BY s.student_name', 's', [$term]) as $r) {
    $pending[$r['class_name']][] = $r['student_name'];
}

$page_title = 'Class Summary Report';
$active = 'class_summary';
$extra_js = '<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadSummary() {
    html2pdf().set({
        margin: [10, 10, 10, 10],
        filename: "Class_Summary_Report.pdf",
        image: { type: "jpeg", quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: "mm", format: "a4", orientation: "portrait" }
    }).from(document.getElementById("summaryContent")).save();
}
</script>';
include __DIR__ . '/includes/header.php';
?>
<form method="GET" action="class_summary.php" class="row g-2 mb-3 align-items-end no-print">
    <div class="col-md-3">
        <label class="form-label fw-semibold small">Academic Term</label>
        <select name="term" class="form-select">
            <?php foreach (TERM_OPTIONS as $t): ?>
                <option value="<?= e($t); ?>" <?= $term === $t ? 'selected' : ''; ?>><?= e($t); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold small">Class</label>
        <select name="class" class="form-select">
            <option value="">All Classes</option>
            <?php foreach (CLASS_OPTIONS as $c): ?>
                <option value="<?= e($c); ?>" <?= $class_filter === $c ? 'selected' : ''; ?>><?= e($c); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2"><label class="form-label fw-semibold small">Attendance from</label><input type="date" name="from" class="form-control" value="<?= e($from); ?>"></div>
    <div class="col-md-2"><label class="form-label fw-semibold small">to</label><input type="date" name="to" class="form-control" value="<?= e($to); ?>"></div>
    <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
        <button type="button" onclick="downloadSummary()" class="btn btn-primary"><i class="bi bi-download me-1"></i>PDF</button>
    </div>
</form>

<div id="summaryContent" class="bg-white p-3 rounded">
    <div class="text-center mb-3">
        <h5 class="fw-bold text-uppercase mb-0">Tadika Perpaduan Pedas</h5>
        <div class="fw-bold text-primary">CLASS SUMMARY REPORT - <?= e($term); ?></div>
        <div class="text-muted small">Generated <?= date('d F Y'); ?><?= ($from !== '' || $to !== '') ? ' | Attendance period: ' . e($from !== '' ? $from : 'start') . ' to ' . e($to !== '' ? $to : 'today') : ''; ?></div>
    </div>

    <?php foreach ($classes as $cls):
        $n   = (int)($student_counts[$cls] ?? 0);
        $ev  = (int)($evaluated[$cls] ?? 0);
        $a   = $att[$cls] ?? ['attended' => 0, 'absent' => 0, 'total' => 0];
        $pct = attendance_pct((int)$a['attended'], (int)$a['total']); // zero-division safe helper from auth.php (DEF-03)
    ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold"><?= e($cls); ?></div>
        <div class="card-body">
            <div class="row g-3 text-center mb-3">
                <div class="col-4"><div class="border rounded p-2"><div class="text-muted small">Students</div><div class="fs-4 fw-bold"><?= $n; ?></div></div></div>
                <div class="col-4"><div class="border rounded p-2"><div class="text-muted small">Evaluated (<?= e($term); ?>)</div><div class="fs-4 fw-bold text-success"><?= $ev; ?> / <?= $n; ?></div></div></div>
                <div class="col-4"><div class="border rounded p-2"><div class="text-muted small">Attendance Rate</div><div class="fs-4 fw-bold text-primary"><?= e($pct); ?>%</div><div class="text-muted small"><?= (int)$a['absent']; ?> absent of <?= (int)$a['total']; ?> records</div></div></div>
            </div>

            <h6 class="fw-bold">Grade Distribution (number of students)</h6>
            <div class="table-responsive">
            <table class="table table-bordered text-center align-middle mb-3">
                <thead class="table-primary"><tr><th class="text-start">Domain</th><?php foreach (GRADE_OPTIONS as $g): ?><th><?= e($g); ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach ($domains as $col => $label): ?>
                    <tr><td class="text-start fw-semibold"><?= e($label); ?></td>
                        <?php foreach (GRADE_OPTIONS as $g): ?><td><?= (int)($dist[$cls][$col][$g] ?? 0); ?></td><?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <?php if (!empty($pending[$cls])): ?>
                <div class="small"><span class="fw-semibold text-warning">Pending evaluation:</span> <?= e(implode(', ', $pending[$cls])); ?></div>
            <?php else: ?>
                <div class="small text-success">All students in this class have an evaluation for <?= e($term); ?>.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
