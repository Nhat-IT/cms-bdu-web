<?php
/**
 * Script sửa lỗi thiếu cột trong database
 */

require_once __DIR__ . '/config/config.php';

try {
    // Thêm cột created_at vào bảng class_subjects nếu chưa có
    $result = db_query("SHOW COLUMNS FROM class_subjects LIKE 'created_at'");
    if (mysqli_num_rows($result) == 0) {
        db_query("ALTER TABLE class_subjects ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER end_date");
        echo "Đã thêm cột created_at vào class_subjects\n";
    } else {
        echo "Cột created_at đã tồn tại trong class_subjects\n";
    }

    // Thêm các cột khác nếu thiếu
    $tables = [
        'users' => ['phone_number', 'address', 'birth_date', 'avatar', 'created_at'],
        'classes' => ['created_at'],
        'subjects' => ['created_at'],
    ];

    foreach ($tables as $table => $columns) {
        foreach ($columns as $col) {
            $result = db_query("SHOW COLUMNS FROM {$table} LIKE '{$col}'");
            if (mysqli_num_rows($result) == 0) {
                if ($col == 'created_at') {
                    db_query("ALTER TABLE {$table} ADD COLUMN {$col} TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
                } else {
                    db_query("ALTER TABLE {$table} ADD COLUMN {$col} VARCHAR(255) NULL");
                }
                echo "Đã thêm cột {$col} vào {$table}\n";
            }
        }
    }

    echo "Hoàn tất sửa lỗi database!\n";
} catch (Exception $e) {
    echo "Lỗi: " . $e->getMessage() . "\n";
}
