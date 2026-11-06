<?php
require_once __DIR__ . '/includes/auth.php';

// Already signed in -> go straight to the dashboard
if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$error = '';
if (isset($_GET['deactivated'])) {
    $error = 'Your account has been deactivated. Please contact the administrator.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_enforce();
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = trim($_POST['role'] ?? '');

    if ($username === '' || $password === '' || $role === '') {
        $error = 'Please fill in all required fields.';
    } else {
        $stmt = $conn->prepare('SELECT user_id, full_name, username, password, role, student_id, status FROM users WHERE username = ? AND role = ?');
        $stmt->bind_param('ss', $username, $role);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $error = 'User not found or role mismatched!';
        } elseif (!password_verify($password, $user['password'])) {
            $error = 'Invalid password!';
        } elseif (($user['status'] ?? 'Active') !== 'Active') {
            $error = 'Your account has been deactivated. Please contact the administrator.';
        } else {
            session_regenerate_id(true); // prevent session fixation
            $_SESSION['user_id']    = (int)$user['user_id'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['full_name']  = $user['full_name'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['student_id'] = $user['student_id'] !== null ? (int)$user['student_id'] : null;
            redirect('dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CilikGrow - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; min-height: 100vh; display: flex; align-items: center; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .login-card { border-radius: 12px; border: none; box-shadow: 0 8px 24px rgba(0,0,0,.08); background: #fff; }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card login-card p-4">
                <div class="text-center mb-4">
                    <img src="img/logo.png" alt="CilikGrow Logo" class="img-fluid mb-2" style="max-height:60px;" onerror="this.style.display='none'">
                    <h3 class="fw-bold text-primary">CilikGrow</h3>
                    <p class="text-muted small mb-0">Kindergarten Student Progress Management System</p>
                    <p class="text-muted small">Tadika Perpaduan Pedas</p>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger py-2 fs-6"><?= e($error); ?></div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <?= csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="role">Select User Role</label>
                        <select name="role" id="role" class="form-select" required>
                            <option value="">-- Select Role --</option>
                            <?php foreach (['Management' => 'Management (Headmaster)', 'Teacher' => 'Teacher', 'Parent' => 'Parent / Guardian'] as $val => $label): ?>
                                <option value="<?= e($val); ?>" <?= (($_POST['role'] ?? '') === $val) ? 'selected' : ''; ?>><?= e($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="username">Username</label>
                        <input type="text" name="username" id="username" class="form-control" placeholder="Enter username" value="<?= e($_POST['username'] ?? ''); ?>" required autocomplete="username">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="password">Password</label>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 mb-3 fw-semibold">Log In</button>
                    <div class="text-center">
                        <span class="text-muted small">New parent?</span>
                        <a href="register.php" class="small text-decoration-none fw-semibold">Sign Up Here</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
