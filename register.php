<?php
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$error = '';
$success = '';
$old = ['full_name' => '', 'username' => '', 'mykid' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_enforce();
    foreach ($old as $k => $_) {
        $old[$k] = trim($_POST[$k] ?? '');
    }
    $password = trim($_POST['password'] ?? '');

    if ($old['full_name'] === '' || $old['username'] === '' || $password === '' || $old['mykid'] === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $old['username'])) {
        $error = 'Username must be 3-50 characters (letters, numbers or underscore only).';
    } elseif (!valid_mykid($old['mykid'])) {
        $error = 'Invalid MyKid format. Numeric digits expected (e.g. 210415-10-0123).';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($old['phone'] !== '' && !valid_phone($old['phone'])) {
        $error = 'Please enter a valid phone number.';
    } else {
        // Step 1: the child's MyKid must exist in the kindergarten records
        $stmt = $conn->prepare('SELECT student_id FROM students WHERE mykid = ?');
        $stmt->bind_param('s', $old['mykid']);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$student) {
            $error = 'MyKid number is not recognized in kindergarten records.';
        } else {
            // Step 2: username must be unique
            $chk = $conn->prepare('SELECT user_id FROM users WHERE username = ?');
            $chk->bind_param('s', $old['username']);
            $chk->execute();
            $taken = $chk->get_result()->num_rows > 0;
            $chk->close();

            if ($taken) {
                $error = 'Username already taken. Please choose another.';
            } else {
                // Step 3: create the Parent account with a bcrypt hash
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $sid  = (int)$student['student_id'];
                $ins  = $conn->prepare("INSERT INTO users (full_name, username, email, phone, password, role, student_id, status) VALUES (?, ?, ?, ?, ?, 'Parent', ?, 'Active')");
                $ins->bind_param('sssssi', $old['full_name'], $old['username'], $old['email'], $old['phone'], $hash, $sid);
                if ($ins->execute()) {
                    $success = 'Account registered successfully! Redirecting you to the login page...';
                    header('Refresh: 3; url=login.php');
                } else {
                    error_log('Register failed: ' . $conn->error);
                    $error = 'Registration failed. Please try again later.';
                }
                $ins->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CilikGrow - Parent Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style> body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; } </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 p-4">
                <div class="text-center mb-3">
                    <img src="img/logo.png" alt="Logo" style="max-height:50px;" onerror="this.style.display='none'">
                    <h4 class="fw-bold text-primary mt-2 mb-0">Parent Account Sign Up</h4>
                    <small class="text-muted">Your account is linked to your child using the MyKid number.</small>
                </div>
                <?php if ($error !== ''): ?><div class="alert alert-danger py-2"><?= e($error); ?></div><?php endif; ?>
                <?php if ($success !== ''): ?><div class="alert alert-success py-2"><?= e($success); ?></div><?php endif; ?>

                <form method="POST" action="register.php">
                    <?= csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full Name</label>
                        <input type="text" name="full_name" class="form-control" placeholder="Parent / Guardian name" value="<?= e($old['full_name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username (Child's Student Name)</label>
                        <input type="text" name="username" class="form-control" placeholder="e.g. adamrayyan" value="<?= e($old['username']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Child's MyKid Number</label>
                        <input type="text" name="mykid" class="form-control" placeholder="e.g. 210415-10-0123" value="<?= e($old['mykid']); ?>" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="parent@email.com" value="<?= e($old['email']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Phone Number</label>
                            <input type="text" name="phone" class="form-control" placeholder="0123456789" value="<?= e($old['phone']); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password</label>
                        <input type="password" name="password" class="form-control" minlength="6" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mb-2 fw-semibold">Register Account</button>
                    <a href="login.php" class="btn btn-link text-decoration-none w-100 text-center">Already have an account? Log In</a>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
