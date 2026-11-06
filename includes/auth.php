<?php
/**
 * CilikGrow - shared helpers (session, RBAC, CSRF, validation, flash messages)
 * Included by every page. Expects config/db.php to provide the $conn (mysqli) object.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';

// ---- Application constants -------------------------------------------------
const ACADEMIC_YEAR       = 2026;
const CLASS_OPTIONS       = ['Kasih (5 years)', 'Sayang (6 years)'];
const TERM_OPTIONS        = ['Term 1 - 2026', 'Term 2 - 2026'];
const GRADE_OPTIONS       = ['A', 'B', 'C'];
const ATTENDANCE_STATUSES = ['Present', 'Absent', 'Late'];
const STAFF_ROLES         = ['Management', 'Teacher'];

// ---- Output escaping (XSS protection, NFR2) ---------------------------------
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// ---- Session & Role-Based Access Control (FR1, FR10) -------------------------
function destroy_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Redirect to login when there is no valid, active session. */
function require_login(): void
{
    global $conn;
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
    // Re-check account status so a deactivated user is cut off immediately.
    $stmt = $conn->prepare('SELECT status FROM users WHERE user_id = ?');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || ($row['status'] ?? 'Active') !== 'Active') {
        destroy_session();
        header('Location: login.php?deactivated=1');
        exit();
    }
}

/** Allow only the listed roles; everyone else is sent back to the dashboard. */
function require_roles(array $roles): void
{
    require_login();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        header('Location: dashboard.php');
        exit();
    }
}

// ---- CSRF protection --------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Call at the start of every POST handler. */
function csrf_enforce(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(419);
        exit('Your form session has expired. Please go back, reload the page and try again.');
    }
}

// ---- Flash messages (used with Post/Redirect/Get) ----------------------------
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

function alert_html(string $type, string $message): string
{
    $icon = $type === 'success' ? 'check-circle-fill' : ($type === 'danger' ? 'exclamation-triangle-fill' : 'info-circle-fill');
    return '<div class="alert alert-' . e($type) . ' py-2 d-flex align-items-center" role="alert">'
         . '<i class="bi bi-' . $icon . ' me-2"></i><div>' . e($message) . '</div></div>';
}

function flash_render(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $html .= alert_html($f['type'], $f['msg']);
    }
    unset($_SESSION['flash']);
    return $html;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit();
}

// ---- Validation helpers ------------------------------------------------------
/** MyKid format: 6 digits - 2 digits - 4 digits, e.g. 210415-10-0123 (TC16) */
function valid_mykid(string $v): bool
{
    return (bool)preg_match('/^\d{6}-\d{2}-\d{4}$/', $v);
}

function valid_date(string $d): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

function valid_phone(string $p): bool
{
    return (bool)preg_match('/^[0-9+\- ]{9,15}$/', $p);
}

// ---- Business rules -----------------------------------------------------------
/** Attendance percentage with zero-division guard (DEF-03, TC34). */
function attendance_pct(int $attended, int $total): float
{
    return $total > 0 ? round(($attended / $total) * 100, 1) : 0.0;
}

/** Totals for one student. "Attended" = Present + Late. */
function attendance_summary(mysqli $conn, int $student_id): array
{
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total_days,
                COALESCE(SUM(status IN ('Present','Late')), 0) AS attended,
                COALESCE(SUM(status = 'Absent'), 0) AS absent
         FROM attendance WHERE student_id = ?"
    );
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $total    = (int)($r['total_days'] ?? 0);
    $attended = (int)($r['attended'] ?? 0);
    return [
        'total'    => $total,
        'attended' => $attended,
        'absent'   => (int)($r['absent'] ?? 0),
        'pct'      => attendance_pct($attended, $total),
    ];
}

/** Term in progress: Term 1 (Jan-Jun) / Term 2 (Jul-Dec). */
function current_term(): string
{
    return ((int)date('n') <= 6) ? TERM_OPTIONS[0] : TERM_OPTIONS[1];
}
