<?php
/**
 * CMS BDU - Database Connection Test
 * Chạy file này để kiểm tra kết nối database
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/helpers.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection Test - CMS BDU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-database-check me-2"></i>Database Connection Test</h4>
                    </div>
                    <div class="card-body">
                        <?php
                        $tests = [];
                        
                        // Test 1: Connection
                        try {
                            $tests['connection'] = ['status' => 'success', 'message' => 'Kết nối database thành công!'];
                        } catch (Exception $e) {
                            $tests['connection'] = ['status' => 'danger', 'message' => 'Lỗi kết nối: ' . $e->getMessage()];
                        }
                        
                        // Test 2: Tables
                        $requiredTables = [
                            'users', 'classes', 'subjects', 'class_subjects', 
                            'class_students', 'attendance_sessions', 'attendance_records',
                            'documents', 'feedbacks', 'assignments', 'grades'
                        ];
                        
                        foreach ($requiredTables as $table) {
                            try {
                                $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
                                $result = $stmt->fetch();
                                $tests['table_' . $table] = [
                                    'status' => 'success', 
                                    'message' => "Bảng '$table' tồn tại ({$result['count']} records)"
                                ];
                            } catch (Exception $e) {
                                $tests['table_' . $table] = [
                                    'status' => 'danger', 
                                    'message' => "Lỗi bảng '$table': " . $e->getMessage()
                                ];
                            }
                        }
                        
                        // Test 3: Sample data
                        try {
                            $stmt = $pdo->query("SELECT * FROM users LIMIT 5");
                            $users = $stmt->fetchAll();
                            $tests['sample_data'] = [
                                'status' => 'info', 
                                'message' => 'Tìm thấy ' . count($users) . ' users trong database'
                            ];
                        } catch (Exception $e) {
                            $tests['sample_data'] = ['status' => 'warning', 'message' => 'Không thể lấy sample data'];
                        }
                        ?>
                        
                        <?php foreach ($tests as $name => $test): ?>
                            <div class="alert alert-<?= $test['status'] ?> alert-dismissible fade show">
                                <?php if ($test['status'] === 'success'): ?>
                                    <i class="bi bi-check-circle-fill me-2"></i>
                                <?php elseif ($test['status'] === 'danger'): ?>
                                    <i class="bi bi-x-circle-fill me-2"></i>
                                <?php elseif ($test['status'] === 'warning'): ?>
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?php else: ?>
                                    <i class="bi bi-info-circle-fill me-2"></i>
                                <?php endif; ?>
                                <?= e($test['message']) ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <hr class="my-4">
                        
                        <h5>Tiếp theo:</h5>
                        <ul class="list-group">
                            <li class="list-group-item">
                                <i class="bi bi-1-circle me-2 text-primary"></i>
                                Cập nhật thông tin kết nối trong <code>config/config.php</code>
                            </li>
                            <li class="list-group-item">
                                <i class="bi bi-2-circle me-2 text-primary"></i>
                                Import file <code>db.sql</code> vào database nếu chưa có bảng
                            </li>
                            <li class="list-group-item">
                                <i class="bi bi-3-circle me-2 text-primary"></i>
                                Truy cập <a href="views/login.php">views/login.php</a> để đăng nhập
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
