<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/helpers.php';

requireRole('bcs');
header('Content-Type: text/plain; charset=utf-8');

$userId = $_SESSION['user_id'];
$classInfo = getUserClassInfo($userId);
$className = $classInfo['class_name'];
$classId   = $classInfo['class_id'];

echo "BCS: userId=$userId, classId=$classId, className='$className'\n\n";

// 1. Kiểm tra class_id trong class_subjects của group 68
$g = db_fetch_one("
    SELECT csg.id as group_id, cs.id as cs_id, cs.class_id,
           c.class_name as cs_class_name, s.subject_name
    FROM class_subject_groups csg
    JOIN class_subjects cs ON csg.class_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.id
    LEFT JOIN classes c ON cs.class_id = c.id
    WHERE csg.id = 68
");
echo "=== class_subjects cho group 68 ===\n";
print_r($g);

// 2. Tất cả students trong group 68 (SSR)
echo "\n=== Students trong group 68 (SSR) ===\n";
$ssrGroup = db_fetch_all("
    SELECT ssr.id, ssr.student_id, ssr.mssv, ssr.full_name, ssr.class_name,
           u.id as user_id, u.username,
           (SELECT cs2.class_id FROM class_students cs2 WHERE cs2.student_id = u.id LIMIT 1) as in_class_id
    FROM student_subject_registration ssr
    LEFT JOIN users u ON ssr.student_id = u.id
    WHERE ssr.class_subject_group_id = 68
");
foreach ($ssrGroup as $r) {
    echo "  ssr.id={$r['id']} mssv={$r['mssv']} student_id=" . ($r['student_id'] ?? 'NULL') .
         " class_name={$r['class_name']} user_id=" . ($r['user_id'] ?? 'NULL') .
         " username=" . ($r['username'] ?? 'NULL') .
         " in_class_id=" . ($r['in_class_id'] ?? 'NULL') . "\n";
}

// 3. Thử filter bằng cs.class_id = $classId
echo "\n=== Thử filter: cs.class_id = $classId ===\n";
$byClassId = db_fetch_all("
    SELECT ar.id, ar.student_id, ar.registration_id,
           COALESCE(u.full_name, ssr.full_name) as ten
    FROM attendance_records ar
    JOIN attendance_sessions a_s ON ar.session_id = a_s.id
    JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
    JOIN class_subjects cs ON csg.class_subject_id = cs.id
    LEFT JOIN users u ON ar.student_id = u.id
    LEFT JOIN student_subject_registration ssr
        ON ssr.class_subject_group_id = csg.id
        AND (
            (ar.student_id IS NOT NULL AND ssr.student_id = ar.student_id)
            OR (ar.student_id IS NULL AND ssr.id = ar.registration_id)
        )
    WHERE ar.status = 3
      AND cs.class_id = ?
    LIMIT 10
", [$classId]);
if (empty($byClassId)) {
    echo "  RỖNG\n";
    echo "  cs.class_id của group 68 = " . ($g['class_id'] ?? 'NULL') . " (cần = $classId)\n";
} else {
    foreach ($byClassId as $r) echo "  ar.id={$r['id']} ten={$r['ten']}\n";
}

// 4. Thử filter không dùng class (xem có bản ghi nào không)
echo "\n=== Tất cả ar.status=3 không filter class ===\n";
$all = db_fetch_all("
    SELECT ar.id, ar.student_id, ar.registration_id,
           COALESCE(u.full_name, ssr.full_name) as ten, ssr.class_name as ssr_class, cs.class_id as cs_class_id
    FROM attendance_records ar
    JOIN attendance_sessions a_s ON ar.session_id = a_s.id
    JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
    JOIN class_subjects cs ON csg.class_subject_id = cs.id
    LEFT JOIN users u ON ar.student_id = u.id
    LEFT JOIN student_subject_registration ssr
        ON ssr.class_subject_group_id = csg.id
        AND (
            (ar.student_id IS NOT NULL AND ssr.student_id = ar.student_id)
            OR (ar.student_id IS NULL AND ssr.id = ar.registration_id)
        )
    WHERE ar.status = 3
    LIMIT 10
");
foreach ($all as $r) {
    echo "  ar.id={$r['id']} ten={$r['ten']} ssr_class={$r['ssr_class']} cs_class_id={$r['cs_class_id']}\n";
}
