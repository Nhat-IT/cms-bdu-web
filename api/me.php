<?php
/**
 * API: Get current logged-in user
 * GET /api/me
 */
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = getCurrentUser();

// Enrich with department if user is a teacher
if ($user['role'] === 'teacher') {
    $conn = getDBConnection();
    $row = db_fetch_one(
        "SELECT d.name as department_name
         FROM teachers t
         LEFT JOIN departments d ON t.department_id = d.id
         WHERE t.user_id = ?",
        [$user['id']]
    );
    if ($row) {
        $user['department_name'] = $row['department_name'] ?? null;
    }
}

echo json_encode($user, JSON_UNESCAPED_UNICODE);
