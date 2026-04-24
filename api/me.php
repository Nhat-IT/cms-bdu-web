<?php
/**
 * API: Current logged-in user profile
 * GET /api/me
 * PUT /api/me
 * POST /api/me
 */
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

function me_normalize_birth_date($value) {
    if ($value === null) {
        return null;
    }

    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $raw);
    if ($dt && $dt->format('Y-m-d') === $raw) {
        return $raw;
    }

    $dt = DateTime::createFromFormat('d/m/Y', $raw);
    if ($dt && $dt->format('d/m/Y') === $raw) {
        return $dt->format('Y-m-d');
    }

    return false;
}

function me_get_user(int $userId): ?array {
    $user = db_fetch_one(
        'SELECT id, username, full_name, email, role, secondary_role, position, avatar, phone_number, address, birth_date, created_at
         FROM users
         WHERE id = ?
         LIMIT 1',
        [$userId]
    );

    if (!$user) {
        return null;
    }

    $roles = [];
    if (!empty($user['role'])) {
        $roles[] = $user['role'];
    }
    if (!empty($user['secondary_role'])) {
        $roles[] = $user['secondary_role'];
    }
    $user['roles'] = array_values(array_unique($roles));

    if (!empty($user['birth_date'])) {
        $user['birth_date_display'] = formatDate($user['birth_date'], 'd/m/Y');
    } else {
        $user['birth_date_display'] = null;
    }

    if (($user['role'] ?? '') === 'teacher') {
        $row = db_fetch_one(
            "SELECT d.name as department_name
             FROM teachers t
             LEFT JOIN departments d ON t.department_id = d.id
             WHERE t.user_id = ?",
            [$userId]
        );
        if ($row) {
            $user['department_name'] = $row['department_name'] ?? null;
        }
    }

    if (in_array(($user['role'] ?? ''), ['student', 'bcs'], true)) {
        $row = db_fetch_one(
            "SELECT c.class_name
             FROM class_students cs
             JOIN classes c ON c.id = cs.class_id
             WHERE cs.student_id = ?
             LIMIT 1",
            [$userId]
        );
        if ($row) {
            $user['class_name'] = $row['class_name'] ?? null;
        }
    }

    return $user;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($method === 'GET') {
    $user = me_get_user($userId);
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    echo json_encode($user, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'PUT' || $method === 'POST') {
    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $fullName = trim((string) ($payload['fullName'] ?? $payload['full_name'] ?? ''));
    $email = trim((string) ($payload['email'] ?? ''));
    $position = trim((string) ($payload['position'] ?? ''));
    $phone = trim((string) ($payload['phoneNumber'] ?? $payload['phone_number'] ?? ''));
    $address = trim((string) ($payload['address'] ?? ''));
    $hasEmail = array_key_exists('email', $payload);
    $hasPosition = array_key_exists('position', $payload);
    $hasPhone = array_key_exists('phoneNumber', $payload) || array_key_exists('phone_number', $payload);
    $hasAddress = array_key_exists('address', $payload);
    $hasBirthDate = array_key_exists('birthDate', $payload) || array_key_exists('birth_date', $payload);
    $birthInput = $payload['birthDate'] ?? $payload['birth_date'] ?? null;
    $birthDate = me_normalize_birth_date($birthInput);

    if ($fullName === '') {
        http_response_code(422);
        echo json_encode(['error' => 'Họ tên không được để trống.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode(['error' => 'Email không hợp lệ.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($birthDate === false) {
        http_response_code(422);
        echo json_encode(['error' => 'Ngày sinh không hợp lệ. Dùng định dạng dd/mm/yyyy hoặc yyyy-mm-dd.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $existing = db_fetch_one('SELECT email, phone_number, address, birth_date, position FROM users WHERE id = ? LIMIT 1', [$userId]);
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    $finalEmail = $hasEmail ? ($email !== '' ? $email : null) : (string) ($existing['email'] ?? '');
    $finalPhone = $hasPhone ? ($phone !== '' ? $phone : null) : ($existing['phone_number'] ?? null);
    $finalAddress = $hasAddress ? ($address !== '' ? $address : null) : ($existing['address'] ?? null);
    $finalPosition = $hasPosition ? ($position !== '' ? $position : null) : ($existing['position'] ?? null);
    $finalBirthDate = $hasBirthDate ? $birthDate : ($existing['birth_date'] ?? null);

    db_query(
        'UPDATE users
         SET full_name = ?, email = ?, phone_number = ?, address = ?, birth_date = ?, position = ?
         WHERE id = ?',
        [$fullName, $finalEmail, $finalPhone, $finalAddress, $finalBirthDate, $finalPosition, $userId]
    );

    $_SESSION['full_name'] = $fullName;
    $_SESSION['email'] = $finalEmail;

    logSystem('Cập nhật hồ sơ cá nhân qua API', 'users', $userId);

    $user = me_get_user($userId);
    echo json_encode($user, JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
header('Allow: GET, PUT, POST');
echo json_encode(['error' => 'Method not allowed']);
