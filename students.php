<?php
require_once __DIR__ . '/includes/auth.php';
require_roles(['Management', 'Teacher']); // Parents are redirected to the dashboard (TC18)

$error = '';

/** Validate the shared add/edit student form. Returns [data, errorMessage]. */
function read_student_form(): array
{
    $d = [
        'name'   => trim($_POST['student_name'] ?? ''),
        'mykid'  => trim($_POST['mykid'] ?? ''),
        'class'  => trim($_POST['class_name'] ?? ''),
        'dob'    => trim($_POST['date_of_birth'] ?? ''),
        'gender' => trim($_POST['gender'] ?? ''),
        'phone'  => trim($_POST['guardian_phone'] ?? ''),
        'addr'   => trim($_POST['address'] ?? ''),
    ];
    if ($d['name'] === '' || $d['mykid'] === '' || $d['class'] === '') {
        return [$d, 'Student Name, MyKid, and Class are required fields.'];
    }
    if (!valid_mykid($d['mykid'])) {
        return [$d, 'Invalid MyKid format. Numeric digits expected.'];
    }
    if (!in_array($d['class'], CLASS_OPTIONS, true)) {
        return [$d, 'Please select a valid class.'];
    }
    if ($d['dob'] !== '' && (!valid_date($d['dob']) || $d['dob'] > date('Y-m-d'))) {
        return [$d, 'Please enter a valid date of birth.'];
    }
    if (!in_array($d['gender'], ['', 'Male', 'Female'], true)) {
        return [$d, 'Please select a valid gender.'];
    }
    if ($d['phone'] !== '' && !valid_phone($d['phone'])) {
        return [$d, 'Please enter a valid guardian phone number.'];
    }
    return [$d, ''];
}

// ---- CREATE (FR3) -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add'])) {
    csrf_enforce();
    [$d, $error] = read_student_form();
    if ($error === '') {
        $chk = $conn->prepare('SELECT student_id FROM students WHERE mykid = ?');
        $chk->bind_param('s', $d['mykid']);
        $chk->execute();
        $exists = $chk->get_result()->num_rows > 0;
        $chk->close();
        if ($exists) {
            $error = 'Student with this MyKid already exists.';
        } else {
            $dob = $d['dob'] !== '' ? $d['dob'] : null;
            $gen = $d['gender'] !== '' ? $d['gender'] : null;
            $ins = $conn->prepare('INSERT INTO students (student_name, mykid, class_name, date_of_birth, gender, address, guardian_phone) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $ins->bind_param('sssssss', $d['name'], $d['mykid'], $d['class'], $dob, $gen, $d['addr'], $d['phone']);
            if ($ins->execute()) {
                flash('success', 'Student profile saved successfully!');
                redirect('students.php');
            }
            error_log('Add student failed: ' . $conn->error);
            $error = 'Error adding student. Please try again.';
            $ins->close();
        }
    }
}

// ---- UPDATE (FR3, TC17) -----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update'])) {
    csrf_enforce();
    $sid = (int)($_POST['student_id'] ?? 0);
    [$d, $error] = read_student_form();
    if ($error === '' && $sid <= 0) {
        $error = 'Invalid student record.';
    }
    if ($error === '') {
        $chk = $conn->prepare('SELECT student_id FROM students WHERE mykid = ? AND student_id <> ?');
        $chk->bind_param('si', $d['mykid'], $sid);
        $chk->execute();
        $dup = $chk->get_result()->num_rows > 0;
        $chk->close();
        if ($dup) {
            $error = 'Student with this MyKid already exists.';
        } else {
            $dob = $d['dob'] !== '' ? $d['dob'] : null;
            $gen = $d['gender'] !== '' ? $d['gender'] : null;
            $upd = $conn->prepare('UPDATE students SET student_name = ?, mykid = ?, class_name = ?, date_of_birth = ?, gender = ?, address = ?, guardian_phone = ? WHERE student_id = ?');
            $upd->bind_param('sssssssi', $d['name'], $d['mykid'], $d['class'], $dob, $gen, $d['addr'], $d['phone'], $sid);
            if ($upd->execute()) {
                flash('success', 'Student profile updated successfully!');
                redirect('students.php');
            }
            error_log('Update student failed: ' . $conn->error);
            $error = 'Error updating student. Please try again.';
            $upd->close();
        }
    }
}

// ---- READ / SEARCH / FILTER (FR7) ---------------------------------------------
$search       = trim($_GET['search'] ?? '');
$class_filter = trim($_GET['class_filter'] ?? '');

$query  = 'SELECT * FROM students WHERE 1=1';
$params = [];
$types  = '';
if ($search !== '') {
    $query   .= ' AND (student_name LIKE ? OR mykid LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}
if ($class_filter !== '') {
    $query   .= ' AND class_name = ?';
    $params[] = $class_filter;
    $types   .= 's';
}
$query .= ' ORDER BY student_id DESC';
$stmt = $conn->prepare($query);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$students_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Keep what the user typed (and re-open the modal) when validation fails.
$posted = [
    'student_name'   => $_POST['student_name'] ?? '',
    'mykid'          => $_POST['mykid'] ?? '',
    'class_name'     => $_POST['class_name'] ?? '',
    'gender'         => $_POST['gender'] ?? '',
    'date_of_birth'  => $_POST['date_of_birth'] ?? '',
    'guardian_phone' => $_POST['guardian_phone'] ?? '',
    'address'        => $_POST['address'] ?? '',
];
$failed_add = $error !== '' && isset($_POST['action_add']);
$failed_upd = $error !== '' && isset($_POST['action_update']);
$failed_sid = (int)($_POST['student_id'] ?? 0);

$page_title = 'Student Profiles Management';
$active = 'students';
include __DIR__ . '/includes/header.php';

/** Shared field set for the add and edit modals. $s = existing row or []. */
function student_fields(array $s = []): void
{
    $v = fn($k) => e($s[$k] ?? '');
    ?>
    <div class="mb-2"><label class="form-label fw-semibold">Student Name</label>
        <input type="text" name="student_name" class="form-control" value="<?= $v('student_name'); ?>" required></div>
    <div class="mb-2"><label class="form-label fw-semibold">MyKid Number</label>
        <input type="text" name="mykid" class="form-control" placeholder="e.g. 210415-10-0123" value="<?= $v('mykid'); ?>" required></div>
    <div class="row g-2">
        <div class="col-md-6 mb-2"><label class="form-label fw-semibold">Class</label>
            <select name="class_name" class="form-select" required>
                <?php foreach (CLASS_OPTIONS as $c): ?>
                    <option value="<?= e($c); ?>" <?= ($s['class_name'] ?? '') === $c ? 'selected' : ''; ?>><?= e($c); ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="col-md-6 mb-2"><label class="form-label fw-semibold">Gender</label>
            <select name="gender" class="form-select">
                <option value="">-- Select --</option>
                <?php foreach (['Male', 'Female'] as $g): ?>
                    <option value="<?= $g; ?>" <?= ($s['gender'] ?? '') === $g ? 'selected' : ''; ?>><?= $g; ?></option>
                <?php endforeach; ?>
            </select></div>
    </div>
    <div class="row g-2">
        <div class="col-md-6 mb-2"><label class="form-label fw-semibold">Date of Birth</label>
            <input type="date" name="date_of_birth" class="form-control" value="<?= $v('date_of_birth'); ?>"></div>
        <div class="col-md-6 mb-2"><label class="form-label fw-semibold">Guardian Phone</label>
            <input type="text" name="guardian_phone" class="form-control" placeholder="0123456789" value="<?= $v('guardian_phone'); ?>"></div>
    </div>
    <div class="mb-2"><label class="form-label fw-semibold">Address</label>
        <textarea name="address" class="form-control" rows="2"><?= $v('address'); ?></textarea></div>
    <?php
}
?>
<?php if ($error !== ''): ?><?= alert_html('danger', $error); ?><?php endif; ?>

<div class="row mb-3 g-2">
    <div class="col-lg-8">
        <form method="GET" action="students.php" class="row g-2">
            <div class="col-md-5"><input type="text" name="search" class="form-control" placeholder="Search by name or MyKid" value="<?= e($search); ?>"></div>
            <div class="col-md-4">
                <select name="class_filter" class="form-select">
                    <option value="">All Classes</option>
                    <?php foreach (CLASS_OPTIONS as $c): ?>
                        <option value="<?= e($c); ?>" <?= $class_filter === $c ? 'selected' : ''; ?>><?= e($c); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search me-1"></i>Search</button></div>
        </form>
    </div>
    <div class="col-lg-4 text-lg-end">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal"><i class="bi bi-plus-lg me-1"></i>Add Student</button>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped table-hover mb-0 align-middle">
            <thead class="table-primary">
                <tr><th>ID</th><th>Student Name</th><th>MyKid</th><th>Gender</th><th>Class</th><th>Guardian Phone</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (!$students_list): ?>
                <tr><td colspan="7" class="text-center py-3 text-muted">No student profiles found.</td></tr>
            <?php else: foreach ($students_list as $s): ?>
                <tr>
                    <td><?= (int)$s['student_id']; ?></td>
                    <td><?= e($s['student_name']); ?></td>
                    <td><?= e($s['mykid'] ?? '-'); ?></td>
                    <td><?= e($s['gender'] ?? '-'); ?></td>
                    <td><?= e($s['class_name']); ?></td>
                    <td><?= e($s['guardian_phone'] ?? '-'); ?></td>
                    <td class="text-nowrap">
                        <button class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#editStudentModal<?= (int)$s['student_id']; ?>"><i class="bi bi-pencil-square"></i> Edit</button>
                        <a href="report.php?student_id=<?= (int)$s['student_id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-text"></i> Report</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Student modal (rendered outside the table) -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="students.php">
            <?= csrf_field(); ?>
            <input type="hidden" name="action_add" value="1">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Add New Student Profile</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"><?php student_fields($failed_add ? $posted : []); ?></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Student</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Student modals (outside <table>/<tbody>, see DEF-02) -->
<?php foreach ($students_list as $s): ?>
<div class="modal fade" id="editStudentModal<?= (int)$s['student_id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="students.php">
            <?= csrf_field(); ?>
            <input type="hidden" name="action_update" value="1">
            <input type="hidden" name="student_id" value="<?= (int)$s['student_id']; ?>">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Edit Student - <?= e($s['student_name']); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"><?php student_fields(($failed_upd && $failed_sid === (int)$s['student_id']) ? $posted : $s); ?></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php
if ($failed_add || $failed_upd) {
    $modal_id = $failed_add ? 'addStudentModal' : 'editStudentModal' . $failed_sid;
    $extra_js = "<script>document.addEventListener('DOMContentLoaded',function(){var m=document.getElementById('" . e($modal_id) . "');if(m){new bootstrap.Modal(m).show();}});</script>";
}
include __DIR__ . '/includes/footer.php';
