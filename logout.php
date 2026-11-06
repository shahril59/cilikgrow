<?php
// Terminate the session completely (TC39) and return to the login page.
require_once __DIR__ . '/includes/auth.php';
destroy_session();
header('Location: login.php');
exit();
