<?php
/**
 * API: Get groups for BCS attendance
 * GET /api/bcs/groups
 */
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

// Lấy thông tin lớp của BCS (hỗ trợ cả class_students và group_students)
$classInfo = getUserClassInfo($userId);
$classId = $classInfo['class_id'];
$className = $classInfo['class_name'];
$sourceType = $classInfo['source'];

if (!$classId && !$className) {
    echo json_encode([]);
    exit;
}

try {
    if ($sourceType === 'class_students' && $classId) {
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
    } else {
        // Từ class_students + student_subject_registration - lấy nhóm dựa trên class_id
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
             JOIN student_subject_registration ssr ON ssr.class_subject_group_id = csg.id
             JOIN class_students cs2 ON cs2.student_id = ssr.student_id AND cs2.class_id = ?
             LEFT JOIN semesters sm ON cs.semester_id = sm.id
             LEFT JOIN users u      ON cs.teacher_id = u.id
             LEFT JOIN rooms r      ON r.room_code = csg.room
             ORDER BY sm.academic_year DESC,
                      FIELD(UPPER(sm.semester_name), 'HK1', '1', 'HK2', '2', 'HK3', '3'),
                      s.subject_name,
                      csg.group_code",
            [$classId]
        );
    }
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
