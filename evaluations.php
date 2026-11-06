<?php
require_once __DIR__ . '/includes/auth.php';
require_roles(['Management', 'Teacher']); // TC24: Parent is redirected

$error = '';

/** Read and validate the evaluation form. Returns [data, errorMessage]. */
function read_eval_form(): array
{
    $d = [
        'term'  => trim($_POST['academic_term'] ?? ''),
        'r'     => trim($_POST['reading_grade'] ?? ''),
        'w'     => trim($_POST['writing_grade'] ?? ''),
        'c'     => trim($_POST['counting_grade'] ?? ''),
        'b'     => trim($_POST['behavior_grade'] ?? ''),
        'notes' => trim($_POST['teacher_notes'] ?? ''),
    ];
    if (!in_array($d['term'], TERM_OPTIONS, true)) {
        return [$d, 'Please select a valid academic term.'];
    }
    foreach (['r', 'w', 'c', 'b'] as $k) {
        if (!in_array($d[$k], GRADE_OPTIONS, true)) {
            return [$d, 'Please select a grade for Reading, Writing, Counting and Behavior.'];
        }
    }
    if (mb_strlen($d['notes']) > 1000) {
        return [$d, 'Teacher remarks must not exceed 1000 characters.'];
    }
    return [$d, ''];
}

/** NFR4: one evaluation per student per term. */
function eval_exists(mysqli $conn, int $student_id, string $term, int $exclude_id = 0): bool
{
    $stmt = $conn->prepare('SELECT progress_id FROM progress_records WHERE student_id = ? AND academic_term = ? AND progress_id <> ?');
    $stmt->bind_param('isi', $student_id, $term, $exclude_id);
    $stmt->execute();
    $found = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $found;
}

// ---- CREATE (FR4) -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_create'])) {
    csrf_enforce();
    $student_id = (int)($_POST['student_id'] ?? 0);
    [$d, $error] = read_eval_form();
    if ($error === '' && $student_id <= 0) {
        $error = 'Please select a student and academic term.';
    }
    if ($error === '' && eval_exists($conn, $student_id, $d['term'])) {
        $error = 'An evaluation for this student and term already exists. Please edit the existing record instead.';
    }
    if ($error === '') {
        $stmt = $conn->prepare('INSERT INTO progress_records (student_id, academic_term, reading_grade, writing_grade, counting_grade, behavior_grade, teacher_notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('issssss', $student_id, $d['term'], $d['r'], $d['w'], $d['c'], $d['b'], $d['notes']);
        if ($stmt->execute()) {
            flash('success', 'Progress evaluation record added successfully!');
            redirect('evaluations.php');
        }
        error_log('Add evaluation failed: ' . $conn->error);
        $error = 'Failed to add record. Please try again.';
        $stmt->close();
    }
}

// ---- UPDATE (FR5) -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update'])) {
    csrf_enforce();
    $progress_id = (int)($_POST['progress_id'] ?? 0);
    [$d, $error] = read_eval_form();
    if ($error === '' && $progress_id <= 0) {
        $error = 'Invalid record ID submitted.';
    }
    if ($error === '') {
        $q = $conn->prepare('SELECT student_id FROM progress_records WHERE progress_id = ?');
        $q->bind_param('i', $progress_id);
        $q->execute();
        $row = $q->get_result()->fetch_assoc();
        $q->close();
        if (!$row) {
            $error = 'Invalid record ID submitted.';
        } elseif (eval_exists($conn, (int)$row['student_id'], $d['term'], $progress_id)) {
            $error = 'Another evaluation for this student and term already exists.';
        }
    }
    if ($error === '') {
        $stmt = $conn->prepare('UPDATE progress_records SET academic_term = ?, reading_grade = ?, writing_grade = ?, counting_grade = ?, behavior_grade = ?, teacher_notes = ? WHERE progress_id = ?');
        $stmt->bind_param('ssssssi', $d['term'], $d['r'], $d['w'], $d['c'], $d['b'], $d['notes'], $progress_id);
        if ($stmt->execute()) {
            flash('success', 'Progress evaluation record updated successfully!');
            redirect('evaluations.php');
        }
        error_log('Update evaluation failed: ' . $conn->error);
        $error = 'Failed to update record. Please try again.';
        $stmt->close();
    }
}

// ---- DELETE (FR5) -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete'])) {
    csrf_enforce();
    $progress_id = (int)($_POST['progress_id'] ?? 0);
    if ($progress_id > 0) {
        $stmt = $conn->prepare('DELETE FROM progress_records WHERE progress_id = ?');
        $stmt->bind_param('i', $progress_id);
        if ($stmt->execute()) {
            flash('success', 'Evaluation record deleted!');
        } else {
            error_log('Delete evaluation failed: ' . $conn->error);
            flash('danger', 'Failed to delete record. Please try again.');
        }
        $stmt->close();
    }
    redirect('evaluations.php' . (!empty($_POST['return_term']) ? '?term=' . urlencode($_POST['return_term']) : ''));
}

// ---- READ + term filter (TC25) -------------------------------------------------
$term_filter = trim($_GET['term'] ?? '');
if ($term_filter !== '' && !in_array($term_filter, TERM_OPTIONS, true)) {
    $term_filter = '';
}

$sql = 'SELECT e.*, s.student_name, s.class_name FROM progress_records e JOIN students s ON e.student_id = s.student_id';

if ($term_filter !== '') {
    $stmt = $conn->prepare($sql . ' WHERE e.academic_term = ? ORDER BY e.progress_id DESC');
    $stmt->bind_param('s', $term_filter);
} else {
    $stmt = $conn->prepare($sql . ' ORDER BY e.progress_id DESC');
}
$stmt->execute();
$evaluations_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$students_dropdown = $conn->query('SELECT student_id, student_name, class_name FROM students ORDER BY student_name ASC')->fetch_all(MYSQLI_ASSOC);

$failed_create = $error !== '' && isset($_POST['action_create']);
$p = fn($k, $default = '') => $failed_create ? ($_POST[$k] ?? $default) : $default;

$page_title = 'Progress Evaluations Management';
$active = 'evaluations';
include __DIR__ . '/includes/header.php';

/** Four grade selects. $vals = current values (edit) ; $required adds a blank first option (create). */
function grade_selects(array $vals, bool $blank): void
{
    foreach (['reading_grade' => 'Read', 'writing_grade' => 'Write', 'counting_grade' => 'Count', 'behavior_grade' => 'Behavior'] as $name => $label) {
        echo '<select name="' . $name . '" class="form-select" required aria-label="' . $label . '">';
        if ($blank) {
            echo '<option value="">' . $label . '</option>';
        }
        foreach (GRADE_OPTIONS as $g) {
            echo '<option value="' . $g . '"' . (($vals[$name] ?? '') === $g ? ' selected' : '') . '>' . $g . '</option>';
        }
        echo '</select>';
    }
}
?>
<?php if ($error !== ''): ?><?= alert_html('danger', $error); ?><?php endif; ?>

<div class="card shadow-sm p-3 mb-4 border-0">
    <h5 class="fw-bold mb-3"><i class="bi bi-plus-circle me-1"></i> Add New Progress Evaluation</h5>
    <form method="POST" action="evaluations.php">
        <?= csrf_field(); ?>
        <input type="hidden" name="action_create" value="1">
        <div class="row g-2">
            <div class="col-lg-4">
                <label class="form-label fw-semibold">Select Student</label>
                <select name="student_id" class="form-select" required>
                    <option value="">-- Select Student --</option>
                    <?php foreach ($students_dropdown as $st): ?>
                        <option value="<?= (int)$st['student_id']; ?>" <?= (int)$p('student_id', 0) === (int)$st['student_id'] ? 'selected' : ''; ?>><?= e($st['student_name']); ?> (<?= e($st['class_name']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label fw-semibold">Academic Term</label>
                <select name="academic_term" class="form-select" required>
                    <?php foreach (TERM_OPTIONS as $t): ?>
                        <option value="<?= e($t); ?>" <?= $p('academic_term', current_term()) === $t ? 'selected' : ''; ?>><?= e($t); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-5">
                <label class="form-label fw-semibold">Grades (Read / Write / Count / Behavior)</label>
                <div class="d-flex gap-1"><?php grade_selects($failed_create ? $_POST : [], true); ?></div>
            </div>
        </div>
        <div class="mt-3">
            <label class="form-label fw-semibold">Teacher Remarks</label>
            <textarea name="teacher_notes" class="form-control" rows="2" maxlength="1000" placeholder="Enter feedback notes..."><?= e($p('teacher_notes')); ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary mt-3 fw-semibold"><i class="bi bi-save me-1"></i> Save Progress</button>
    </form>
</div>

<form method="GET" action="evaluations.php" class="row g-2 mb-3">
    <div class="col-md-4">
        <select name="term" class="form-select">
            <option value="">All Terms</option>
            <?php foreach (TERM_OPTIONS as $t): ?>
                <option value="<?= e($t); ?>" <?= $term_filter === $t ? 'selected' : ''; ?>><?= e($t); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto"><button type="submit" class="btn btn-outline-primary"><i class="bi bi-funnel me-1"></i>Filter</button></div>
    <?php if ($term_filter !== ''): ?><div class="col-auto"><a href="evaluations.php" class="btn btn-link">Clear</a></div><?php endif; ?>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped table-hover mb-0 align-middle">
            <thead class="table-primary">
                <tr><th>ID</th><th>Student Name</th><th>Term</th><th>Read</th><th>Write</th><th>Count</th><th>Behavior</th><th>Remarks</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (!$evaluations_list): ?>
                <tr><td colspan="9" class="text-center py-3 text-muted">No evaluation records found.</td></tr>
            <?php else: foreach ($evaluations_list as $ev): ?>
                <tr>
                    <td><?= (int)$ev['progress_id']; ?></td>
                    <td><?= e($ev['student_name']); ?></td>
                    <td><?= e($ev['academic_term'] ?? '-'); ?></td>
                    <td><span class="badge bg-info text-dark"><?= e($ev['reading_grade'] ?? '-'); ?></span></td>
                    <td><span class="badge bg-info text-dark"><?= e($ev['writing_grade'] ?? '-'); ?></span></td>
                    <td><span class="badge bg-info text-dark"><?= e($ev['counting_grade'] ?? '-'); ?></span></td>
                    <td><span class="badge bg-success"><?= e($ev['behavior_grade'] ?? '-'); ?></span></td>
                    <td class="small text-muted" style="max-width:220px;"><?= e(mb_strimwidth((string)($ev['teacher_notes'] ?? ''), 0, 60, '...')); ?></td>
                    <td class="text-nowrap">
                        <button class="btn btn-sm btn-warning me-1" data-bs-toggle="modal" data-bs-target="#editModal<?= (int)$ev['progress_id']; ?>"><i class="bi bi-pencil-square"></i> Edit</button>
                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= (int)$ev['progress_id']; ?>"><i class="bi bi-trash"></i> Delete</button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modals are rendered OUTSIDE the table (fix for DEF-02) -->
<?php foreach ($evaluations_list as $ev): ?>
<div class="modal fade" id="editModal<?= (int)$ev['progress_id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="evaluations.php">
            <?= csrf_field(); ?>
            <input type="hidden" name="action_update" value="1">
            <input type="hidden" name="progress_id" value="<?= (int)$ev['progress_id']; ?>">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Edit Evaluation - <?= e($ev['student_name']); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Academic Term</label>
                        <select name="academic_term" class="form-select" required>
                            <?php foreach (TERM_OPTIONS as $t): ?>
                                <option value="<?= e($t); ?>" <?= ($ev['academic_term'] ?? '') === $t ? 'selected' : ''; ?>><?= e($t); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Grades (Read / Write / Count / Behavior)</label>
                        <div class="d-flex gap-1"><?php grade_selects($ev, false); ?></div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Teacher Remarks</label>
                        <textarea name="teacher_notes" class="form-control" rows="3" maxlength="1000"><?= e($ev['teacher_notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Evaluation</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="deleteModal<?= (int)$ev['progress_id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="evaluations.php">
            <?= csrf_field(); ?>
            <input type="hidden" name="action_delete" value="1">
            <input type="hidden" name="progress_id" value="<?= (int)$ev['progress_id']; ?>">
            <input type="hidden" name="return_term" value="<?= e($term_filter); ?>">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white"><h5 class="modal-title">Confirm Deletion</h5></div>
                <div class="modal-body">
                    <p class="mb-0">Are you sure you want to delete the progress evaluation record for <strong><?= e($ev['student_name']); ?></strong> (<?= e($ev['academic_term'] ?? ''); ?>)?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Record</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php
if ($error !== '' && isset($_POST['action_update'])) {
    $mid = 'editModal' . (int)($_POST['progress_id'] ?? 0);
    $extra_js = "<script>document.addEventListener('DOMContentLoaded',function(){var m=document.getElementById('" . e($mid) . "');if(m){new bootstrap.Modal(m).show();}});</script>";
}
include __DIR__ . '/includes/footer.php';