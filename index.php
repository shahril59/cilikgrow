<?php
// Entry point: http://localhost/cilikgrow/
require_once __DIR__ . '/includes/auth.php';
redirect(empty($_SESSION['user_id']) ? 'login.php' : 'dashboard.php');
