<?php
require_once __DIR__ . '/includes/auth.php';
require_roles(['Management']); // FR10: only Management manages user access

$error = '';
$me = (int)$_SESSION['user_id'];
const ALL_ROLES = ['Management', 'Teacher', 'Parent'];

function fetch_user(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare('SELECT user_id, role, student_id, status FROM users WHERE user_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// ---- Add a staff account (Teacher / Management) -------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_user'])) {
    csrf_enforce();
    $full  = trim($_POST['full_name'] ?? '');
    $uname = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = trim($_POST['password'] ?? '');
    $newrole = trim($_POST['role'] ?? '');

    if ($full === '' || $uname === '' || $pass === '') {
        $error = 'Full name, username and password are required.';
    } elseif (!in_array($newrole, STAFF_ROLES, true)) {
        $error = 'Staff accounts must be Teacher or Management. Parents register themselves.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $uname)) {
        $error = 'Username must be 3-50 characters (letters, numbers or underscore only).';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $chk = $conn->prepare('SELECT user_id FROM users WHERE username = ?');
        $chk->bind_param('s', $uname);
        $chk->execute();
        $taken = $chk->get_result()->num_rows > 0;
        $chk->close();
        if ($taken) {
            $error = 'Username already taken. Please choose another.';
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $ins = $conn->prepare("INSERT INTO users (full_name, username, email, password, role, status) VALUES (?, ?, ?, ?, ?, 'Active')");
            $ins->bind_param('sssss', $full, $uname, $email, $hash, $newrole);
            if ($ins->execute()) {
                flash('success', 'User account created successfully!');
                redirect('users.php');
            }
            error_log('Add user failed: ' . $conn->error);
            $error = 'Failed to create account. Please try again.';
            $ins->close();
        }
    }
}

// ---- Change role / activate / deactivate (TC38) ---------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_user'])) {
    csrf_enforce();
    $uid     = (int)($_POST['user_id'] ?? 0);
    $newrole = trim($_POST['role'] ?? '');
    $status  = trim($_POST['status'] ?? '');
    $target  = fetch_user($conn, $uid);

    if (!$target) {
        $error = 'User not found.';
    } elseif ($uid === $me) {
        $error = 'You cannot change your own role or deactivate your own account.';
    } elseif (!in_array($newrole, ALL_ROLES, true) || !in_array($status, ['Active', 'Inactive'], true)) {
        $error = 'Invalid role or status.';
    } elseif ($newrole === 'Parent' && empty($target['student_id'])) {
        $error = 'A Parent account must be linked to a student. Ask the parent to sign up with the child\'s MyKid number.';
    } else {
        // Non-parent accounts do not keep a child link
        $sid = $newrole === 'Parent' ? (int)$target['student_id'] : null;
        $upd = $conn->prepare('UPDATE users SET role = ?, status = ?, student_id = ? WHERE user_id = ?');
        $upd->bind_param('ssii', $newrole, $status, $sid, $uid);
        if ($upd->execute()) {
            flash('success', 'User access updated successfully!');
            redirect('users.php');
        }
        error_log('Update user failed: ' . $conn->error);
        $error = 'Failed to update user. Please try again.';
        $upd->close();
    }
}

// ---- Reset password -------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_reset_password'])) {
    csrf_enforce();
    $uid  = (int)($_POST['user_id'] ?? 0);
    $pass = trim($_POST['new_password'] ?? '');
    if (!fetch_user($conn, $uid)) {
        $error = 'User not found.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $upd = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?');
        $upd->bind_param('si', $hash, $uid);
        if ($upd->execute()) {
            flash('success', 'Password reset successfully!');
            redirect('users.php');
        }
        error_log('Reset password failed: ' . $conn->error);
        $error = 'Failed to reset password. Please try again.';
        $upd->close();
    }
}

// ---- List + role filter ------------------------------------------------------------------
$role_filter = trim($_GET['role'] ?? '');
if ($role_filter !== '' && !in_array($role_filter, ALL_ROLES, true)) {
    $role_filter = '';
}
$sql = 'SELECT u.user_id, u.full_name, u.username, u.email, u.role, u.status, s.student_name
        FROM users u LEFT JOIN students s ON u.student_id = s.student_id';
if ($role_filter !== '') {
    $stmt = $conn->prepare($sql . ' WHERE u.role = ? ORDER BY u.role, u.full_name');
    $stmt->bind_param('s', $role_filter);
} else {
    $stmt = $conn->prepare($sql . ' ORDER BY u.role, u.full_name');
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'User Management & Access Control';
$active = 'users';
include __DIR__ . '/includes/header.php';
?>
<?php if ($error !== ''): ?><?= alert_html('danger', $error); ?><?php endif; ?>

<div class="row g-2 mb-3">
    <div class="col-md-8">
        <form method="GET" action="users.php" class="row g-2">
            <div class="col-md-5">
                <select name="role" class="form-select" aria-label="Filter by role">
                    <option value="">All Roles</option>
                    <?php foreach (ALL_ROLES as $r): ?>
                        <option value="<?= $r; ?>" <?= $role_filter === $r ? 'selected' : ''; ?>><?= $r; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto"><button type="submit" class="btn btn-outline-primary"><i class="bi bi-funnel me-1"></i>Filter</button></div>
        </form>
    </div>
    <div class="col-md-4 text-md-end">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-person-plus me-1"></i>Add Staff Account</button>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped table-hover mb-0 align-middle">
            <thead class="table-primary"><tr><th>Name</th><th>Username</th><th>Role</th><th>Linked Child</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (!$users): ?>
                <tr><td colspan="6" class="text-center py-3 text-muted">No users found.</td></tr>
            <?php else: foreach ($users as $u): ?>
                <tr>
                    <td><?= e($u['full_name']); ?><div class="text-muted small"><?= e($u['email'] ?? ''); ?></div></td>
                    <td><?= e($u['username']); ?></td>
                    <td><span class="badge bg-<?= $u['role'] === 'Management' ? 'dark' : ($u['role'] === 'Teacher' ? 'primary' : 'secondary'); ?>"><?= e($u['role']); ?></span></td>
                    <td><?= e($u['student_name'] ?? '-'); ?></td>
                    <td><span class="badge bg-<?= ($u['status'] ?? 'Active') === 'Active' ? 'success' : 'danger'; ?>"><?= e($u['status'] ?? 'Active'); ?></span></td>
                    <td class="text-nowrap">
                        <?php if ((int)$u['user_id'] === $me): ?>
                            <span class="text-muted small">Your account</span>
                        <?php else: ?>
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editUser<?= (int)$u['user_id']; ?>"><i class="bi bi-pencil-square"></i> Edit Access</button>
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#resetUser<?= (int)$u['user_id']; ?>"><i class="bi bi-key"></i> Reset Password</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add staff modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="users.php">
            <?= csrf_field(); ?>
            <input type="hidden" name="action_add_user" value="1">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Add Staff Account</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label fw-semibold">Full Name</label><input type="text" name="full_name" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label fw-semibold">Username</label><input type="text" name="username" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label fw-semibold">Email</label><input type="email" name="email" class="form-control"></div>
                    <div class="mb-2"><label class="form-label fw-semibold">Role</label>
                        <select name="role" class="form-select" required><option value="Teacher">Teacher</option><option value="Management">Management</option></select></div>
                    <div class="mb-2"><label class="form-label fw-semibold">Password</label><input type="password" name="password" class="form-control" minlength="6" required autocomplete="new-password"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Create Account</button></div>
            </div>
        </form>
    </div>
</div>

<!-- Edit access + reset password modals (outside the table, see DEF-02) -->
<?php foreach ($users as $u): if ((int)$u['user_id'] === $me) continue; ?>
<div class="modal fade" id="editUser<?= (int)$u['user_id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="users.php">
            <?= csrf_field(); ?>
            <input type="hidden" name="action_update_user" value="1">
            <input type="hidden" name="user_id" value="<?= (int)$u['user_id']; ?>">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Edit Access - <?= e($u['full_name']); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label fw-semibold">Role</label>
                        <select name="role" class="form-select">
                            <?php foreach (ALL_ROLES as $r): ?><option value="<?= $r; ?>" <?= $u['role'] === $r ? 'selected' : ''; ?>><?= $r; ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="mb-2"><label class="form-label fw-semibold">Account Status</label>
                        <select name="status" class="form-select">
                            <option value="Active" <?= ($u['status'] ?? 'Active') === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?= ($u['status'] ?? 'Active') === 'Inactive' ? 'selected' : ''; ?>>Inactive (deactivated - cannot log in)</option>
                        </select></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Update Access</button></div>
            </div>
        </form>
    </div>
</div>
<div class="modal fade" id="resetUser<?= (int)$u['user_id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="users.php">
            <?= csrf_field(); ?>
            <input type="hidden" name="action_reset_password" value="1">
            <input type="hidden" name="user_id" value="<?= (int)$u['user_id']; ?>">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Reset Password - <?= e($u['full_name']); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"><label class="form-label fw-semibold">New Password</label><input type="password" name="new_password" class="form-control" minlength="6" required autocomplete="new-password"></div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Reset Password</button></div>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
