<?php
/**
 * API: Get groups for BCS attendance
 * GET /api/bcs/groups
 */
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

// Get class_id of BCS user
$classRow = db_fetch_one(
    "SELECT cs.class_id FROM class_students cs WHERE cs.student_id = ?",
    [$userId]
);

if (!$classRow || !$classRow['class_id']) {
    echo json_encode([]);
    exit;
}

$classId = $classRow['class_id'];

$rows = db_fetch_all(
    "SELECT DISTINCT
        csg.id             AS group_id,
        csg.group_code,
        cs.id              AS class_subject_id,
        s.subject_name,
        c.class_name,
        CONCAT(COALESCE(u.academic_title, ''), ' ', u.full_name) AS teacher_name,
        cs.start_date,
        cs.end_date,
        csg.start_period,
        csg.end_period,
        csg.room,
        cs.study_session
     FROM class_subjects cs
     JOIN subjects s          ON cs.subject_id = s.id
     JOIN classes c           ON cs.class_id = c.id
     JOIN class_subject_groups csg ON csg.class_subject_id = cs.id
     LEFT JOIN users u        ON cs.teacher_id = u.id
     JOIN student_subject_registration ssr
          ON ssr.class_subject_group_id = csg.id
          AND ssr.student_id IN (
              SELECT student_id FROM class_students WHERE class_id = ?
          )
     WHERE ssr.status = 'Đang học'
     ORDER BY s.subject_name, csg.group_code",
    [$classId]
);

echo json_encode($rows, JSON_UNESCAPED_UNICODE);
