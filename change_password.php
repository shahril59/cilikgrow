<?php
require_once __DIR__ . '/includes/auth.php';
require_login(); // any signed-in role may change their OWN password

$error = '';
$uid = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_enforce();
    $current = trim($_POST['current_password'] ?? '');
    $new     = trim($_POST['new_password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    $stmt = $conn->prepare('SELECT password FROM users WHERE user_id = ?');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($current === '' || $new === '' || $confirm === '') {
        $error = 'Please fill in all fields.';
    } elseif (!$row || !password_verify($current, $row['password'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } elseif ($new !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } elseif ($new === $current) {
        $error = 'New password must be different from the current password.';
    } else {
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $upd = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?');
        $upd->bind_param('si', $hash, $uid);
        if ($upd->execute()) {
            session_regenerate_id(true);
            flash('success', 'Password changed successfully!');
            redirect('change_password.php');
        }
        error_log('Change password failed: ' . $conn->error);
        $error = 'Failed to change password. Please try again.';
        $upd->close();
    }
}

$page_title = 'Change Password';
$active = 'change_password';
include __DIR__ . '/includes/header.php';
?>
<?php if ($error !== ''): ?><?= alert_html('danger', $error); ?><?php endif; ?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card border-0 shadow-sm p-4">
            <form method="POST" action="change_password.php">
                <?= csrf_field(); ?>
                <div class="mb-3"><label class="form-label fw-semibold">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required autocomplete="current-password"></div>
                <div class="mb-3"><label class="form-label fw-semibold">New Password</label>
                    <input type="password" name="new_password" class="form-control" minlength="6" required autocomplete="new-password"></div>
                <div class="mb-3"><label class="form-label fw-semibold">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" minlength="6" required autocomplete="new-password"></div>
                <button type="submit" class="btn btn-primary fw-semibold"><i class="bi bi-key me-1"></i> Update Password</button>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
