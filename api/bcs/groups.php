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

try {
    $rows = db_fetch_all(
        "SELECT DISTINCT
            csg.id                    AS group_id,
            csg.group_code,
            cs.id                     AS class_subject_id,
            s.subject_name,
            s.subject_code,
            c.class_name,
            TRIM(CONCAT(COALESCE(u.academic_title, ''), ' ', COALESCE(u.full_name, ''))) AS teacher_name,
            sm.semester_name,
            sm.academic_year,
            csg.day_of_week,
            cs.start_date,
            cs.end_date,
            csg.start_period,
            csg.end_period,
            csg.room,
            COALESCE(r.room_name, csg.room) AS room_name,
            CASE
                WHEN csg.start_period BETWEEN 1 AND 5 THEN 'Sáng'
                WHEN csg.start_period BETWEEN 6 AND 10 THEN 'Chiều'
                WHEN csg.start_period BETWEEN 11 AND 14 THEN 'Tối'
                ELSE ''
            END AS study_session
         FROM class_subject_groups csg
         JOIN class_subjects cs ON csg.class_subject_id = cs.id
         JOIN subjects s        ON cs.subject_id = s.id
         JOIN classes c         ON cs.class_id = c.id
         LEFT JOIN semesters sm ON cs.semester_id = sm.id
         LEFT JOIN users u      ON cs.teacher_id = u.id
         LEFT JOIN rooms r      ON r.room_code = csg.room
         WHERE cs.class_id = ?
         ORDER BY sm.academic_year DESC,
                  FIELD(UPPER(sm.semester_name), 'HK1', '1', 'HK2', '2', 'HK3', '3'),
                  s.subject_name,
                  csg.group_code",
        [$classId]
    );
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
