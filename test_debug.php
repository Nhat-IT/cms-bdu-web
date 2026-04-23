<?php
require_once __DIR__ . '/config/config.php';

echo "=== class_subjects hiện tại ===\n";
$rows = db_fetch_all("
    SELECT cs.id, s.subject_code, s.subject_name, c.class_name, sm.semester_name, sm.academic_year, cs.teacher_id
    FROM class_subjects cs
    JOIN subjects s ON cs.subject_id = s.id
    JOIN classes c ON cs.class_id = c.id
    LEFT JOIN semesters sm ON cs.semester_id = sm.id
    ORDER BY s.subject_code, c.class_name
");
foreach ($rows as $r) {
    echo "ID={$r['id']} | [{$r['subject_code']}] {$r['subject_name']} | Lớp: {$r['class_name']} | HK: {$r['semester_name']} {$r['academic_year']} | teacher_id: {$r['teacher_id']}\n";
}

echo "\n=== Phát hiện trùng lặp ===\n";
$dupes = db_fetch_all("
    SELECT class_id, subject_id, semester_id, COUNT(*) as cnt
    FROM class_subjects
    GROUP BY class_id, subject_id, semester_id
    HAVING cnt > 1
");
if (empty($dupes)) {
    echo "Không có bản ghi trùng lặp.\n";
} else {
    foreach ($dupes as $d) {
        echo "TRÙNG: class_id={$d['class_id']} subject_id={$d['subject_id']} semester_id={$d['semester_id']} (x{$d['cnt']})\n";
    }
}

echo "\n=== Subjects catalogue (subjects table) ===\n";
$subjects = db_fetch_all("SELECT id, subject_code, subject_name, is_active FROM subjects ORDER BY subject_code");
foreach ($subjects as $s) {
    echo "ID={$s['id']} | [{$s['subject_code']}] {$s['subject_name']} | active={$s['is_active']}\n";
}
