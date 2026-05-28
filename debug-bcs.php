<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/helpers.php';

requireRole('bcs');
header('Content-Type: text/plain; charset=utf-8');

$userId = $_SESSION['user_id'];
echo "=== BCS DEBUG ===\n";
echo "userId: $userId\n\n";

// 1. Lấy class info
$classInfo = getUserClassInfo($userId);
echo "classInfo:\n";
print_r($classInfo);
$className = $classInfo['class_name'];
$classId   = $classInfo['class_id'];
echo "\n";

if (!$className) {
    echo "PROBLEM: className rỗng → không tìm được lớp cho BCS này\n";
    exit;
}

// 2. Kiểm tra attendance_records tồn tại
$total = db_fetch_one("SELECT COUNT(*) as c FROM attendance_records WHERE status = 3");
echo "Tổng bản ghi vắng (status=3) trong DB: " . ($total['c'] ?? 0) . "\n";

// 3. Kiểm tra SSR có class_name khớp không
$ssrCount = db_fetch_one(
    "SELECT COUNT(*) as c FROM student_subject_registration WHERE class_name = ?",
    [$className]
);
echo "SSR rows có class_name='$className': " . ($ssrCount['c'] ?? 0) . "\n\n";

// 4. Thử query cơ bản: ar JOIN session JOIN group JOIN ssr
$basic = db_fetch_all("
    SELECT ar.id, ar.student_id, ar.registration_id, ar.status,
           a_s.attendance_date, csg.id as group_id
    FROM attendance_records ar
    JOIN attendance_sessions a_s ON ar.session_id = a_s.id
    JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
    WHERE ar.status = 3
    LIMIT 10
");
echo "=== AR JOIN session JOIN group (top 10, status=3) ===\n";
foreach ($basic as $r) {
    echo "  ar.id={$r['id']} student_id=" . ($r['student_id'] ?? 'NULL') .
         " reg_id=" . ($r['registration_id'] ?? 'NULL') .
         " date={$r['attendance_date']} group_id={$r['group_id']}\n";
}
echo "\n";

// 5. Thử SSR join đúng cách
$ssrTest = db_fetch_all("
    SELECT ar.id, ar.student_id, ar.registration_id,
           ssr.id as ssr_id, ssr.class_name, ssr.mssv, ssr.student_id as ssr_student_id
    FROM attendance_records ar
    JOIN attendance_sessions a_s ON ar.session_id = a_s.id
    JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
    JOIN student_subject_registration ssr
        ON ssr.class_subject_group_id = csg.id
        AND (
            (ar.student_id IS NOT NULL AND ssr.student_id = ar.student_id)
            OR (ar.student_id IS NULL AND ssr.id = ar.registration_id)
        )
    WHERE ar.status = 3
    LIMIT 10
");
echo "=== AR JOIN SSR (đúng cách, top 10) ===\n";
if (empty($ssrTest)) {
    echo "  RỖNG → SSR join không khớp\n";
    echo "\n  Kiểm tra: ar.registration_id có tồn tại trong SSR.id không?\n";

    $regCheck = db_fetch_all("
        SELECT ar.id, ar.student_id, ar.registration_id,
               (SELECT ssr2.id FROM student_subject_registration ssr2 WHERE ssr2.id = ar.registration_id LIMIT 1) as ssr_found,
               (SELECT ssr2.student_id FROM student_subject_registration ssr2 WHERE ssr2.student_id = ar.student_id LIMIT 1) as stu_found
        FROM attendance_records ar
        WHERE ar.status = 3
        LIMIT 5
    ");
    echo "  Chi tiết ar (status=3):\n";
    foreach ($regCheck as $r) {
        echo "    ar.id={$r['id']} student_id=" . ($r['student_id'] ?? 'NULL') .
             " reg_id=" . ($r['registration_id'] ?? 'NULL') .
             " ssr_by_id=" . ($r['ssr_found'] ?? 'NULL') .
             " ssr_by_stu=" . ($r['stu_found'] ?? 'NULL') . "\n";
    }
} else {
    foreach ($ssrTest as $r) {
        echo "  ar.id={$r['id']} student_id=" . ($r['student_id'] ?? 'NULL') .
             " reg_id=" . ($r['registration_id'] ?? 'NULL') .
             " ssr_id={$r['ssr_id']} class='{$r['class_name']}' mssv={$r['mssv']}\n";
    }
}
echo "\n";

// 6. Thử query cuối cùng có filter class_name
$finalTest = db_fetch_all("
    SELECT ar.id, COALESCE(u.full_name, ssr.full_name) as ten
    FROM attendance_records ar
    JOIN attendance_sessions a_s ON ar.session_id = a_s.id
    JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
    JOIN student_subject_registration ssr
        ON ssr.class_subject_group_id = csg.id
        AND (
            (ar.student_id IS NOT NULL AND ssr.student_id = ar.student_id)
            OR (ar.student_id IS NULL AND ssr.id = ar.registration_id)
        )
    LEFT JOIN users u ON ar.student_id = u.id
    WHERE ar.status = 3
      AND ssr.class_name = ?
    LIMIT 5
", [$className]);
echo "=== Query cuối có filter class_name='$className' ===\n";
if (empty($finalTest)) {
    echo "  RỖNG\n";

    // Xem class_name trong SSR của các ar này là gì
    $ssrClassNames = db_fetch_all("
        SELECT DISTINCT ssr.class_name, COUNT(*) as cnt
        FROM attendance_records ar
        JOIN attendance_sessions a_s ON ar.session_id = a_s.id
        JOIN class_subject_groups csg ON a_s.class_subject_group_id = csg.id
        JOIN student_subject_registration ssr
            ON ssr.class_subject_group_id = csg.id
            AND (
                (ar.student_id IS NOT NULL AND ssr.student_id = ar.student_id)
                OR (ar.student_id IS NULL AND ssr.id = ar.registration_id)
            )
        WHERE ar.status = 3
        GROUP BY ssr.class_name
        LIMIT 10
    ");
    echo "  class_name thực tế trong SSR của các bản ghi vắng:\n";
    if (empty($ssrClassNames)) {
        echo "    (không có) → SSR join vẫn rỗng\n";
    } else {
        foreach ($ssrClassNames as $r) {
            $match = ($r['class_name'] === $className) ? ' ← MATCH' : '';
            echo "    '{$r['class_name']}' ({$r['cnt']} bản ghi){$match}\n";
        }
        echo "  BCS className = '$className'\n";
    }
} else {
    foreach ($finalTest as $r) echo "  ar.id={$r['id']} tên={$r['ten']}\n";
}
