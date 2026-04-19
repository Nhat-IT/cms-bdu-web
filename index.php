<?php
/**
 * CMS BDU - Trang chủ / Xem trước
 * Trang này liệt kê tất cả các trang đã chuyển đổi
 */

require_once __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS BDU - Trang Xem Trước</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .preview-card { transition: transform 0.3s, box-shadow 0.3s; }
        .preview-card:hover { transform: translateY(-5px); box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .role-badge { font-size: 0.75rem; }
        .card-icon { font-size: 2rem; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="text-center mb-5 text-white">
            <h1 class="display-4 fw-bold mb-3">
                <i class="bi bi-mortarboard-fill"></i> CMS BDU
            </h1>
            <p class="lead">Hệ thống Quản lý Lớp học Thông minh</p>
            <p class="small">Đã chuyển đổi HTML sang PHP với kết nối Database</p>
        </div>

        <div class="row g-4">
            
            <!-- Trang Đăng nhập -->
            <div class="col-md-6 col-lg-4">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-primary">
                            <i class="bi bi-box-arrow-in-right"></i>
                        </div>
                        <h5 class="card-title">Đăng nhập</h5>
                        <p class="card-text text-muted small">Trang đăng nhập hệ thống</p>
                        <a href="views/login.php" target="_blank" class="btn btn-primary">
                            <i class="bi bi-eye me-1"></i> Xem trước
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quên mật khẩu -->
            <div class="col-md-6 col-lg-4">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-warning">
                            <i class="bi bi-key"></i>
                        </div>
                        <h5 class="card-title">Quên mật khẩu</h5>
                        <p class="card-text text-muted small">Khôi phục mật khẩu qua OTP</p>
                        <a href="views/forgot-password.php" target="_blank" class="btn btn-warning">
                            <i class="bi bi-eye me-1"></i> Xem trước
                        </a>
                    </div>
                </div>
            </div>

            <!-- Test Database -->
            <div class="col-md-6 col-lg-4">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-success">
                            <i class="bi bi-database-check"></i>
                        </div>
                        <h5 class="card-title">Test Database</h5>
                        <p class="card-text text-muted small">Kiểm tra kết nối MySQL</p>
                        <a href="test_db.php" target="_blank" class="btn btn-success">
                            <i class="bi bi-eye me-1"></i> Xem trước
                        </a>
                    </div>
                </div>
            </div>

        </div>

        <hr class="my-5 border-white border-opacity-25">

        <h3 class="text-white text-center mb-4">
            <i class="bi bi-people-fill me-2"></i>Các trang theo vai trò
        </h3>

        <div class="row g-4">
            
            <!-- SINH VIÊN -->
            <div class="col-12">
                <h4 class="text-white mb-3">
                    <span class="badge bg-primary role-badge me-2">SINH VIÊN</span>
                </h4>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-primary"><i class="bi bi-grid-1x2"></i></div>
                        <h6 class="card-title">Tổng quan</h6>
                        <a href="views/student/home.php" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-info"><i class="bi bi-person-lines-fill"></i></div>
                        <h6 class="card-title">Điểm danh</h6>
                        <a href="views/student/my-attendance.php" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-secondary"><i class="bi bi-calendar-week"></i></div>
                        <h6 class="card-title">Lịch học</h6>
                        <a href="views/student/schedule.php" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-warning"><i class="bi bi-journal-text"></i></div>
                        <h6 class="card-title">Bài tập</h6>
                        <a href="views/student/assignments.php" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-success"><i class="bi bi-bar-chart"></i></div>
                        <h6 class="card-title">Kết quả</h6>
                        <a href="views/student/grades.php" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-danger"><i class="bi bi-folder2-open"></i></div>
                        <h6 class="card-title">Tài liệu</h6>
                        <a href="views/student/documents.php" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-info"><i class="bi bi-bell"></i></div>
                        <h6 class="card-title">Thông báo</h6>
                        <a href="views/student/notifications-all.php" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-dark"><i class="bi bi-envelope-paper"></i></div>
                        <h6 class="card-title">Phản hồi</h6>
                        <a href="views/student/my-feedback.php" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-primary"><i class="bi bi-person-circle"></i></div>
                        <h6 class="card-title">Hồ sơ</h6>
                        <a href="views/student/student-profile.php" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- GIẢNG VIÊN -->
            <div class="col-12 mt-4">
                <h4 class="text-white mb-3">
                    <span class="badge bg-success role-badge me-2">GIẢNG VIÊN</span>
                </h4>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-success"><i class="bi bi-grid-1x2"></i></div>
                        <h6 class="card-title">Tổng quan</h6>
                        <a href="views/teacher/home.php" target="_blank" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-info"><i class="bi bi-person-lines-fill"></i></div>
                        <h6 class="card-title">Điểm danh</h6>
                        <a href="views/teacher/attendance.php" target="_blank" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-warning"><i class="bi bi-table"></i></div>
                        <h6 class="card-title">Bảng điểm</h6>
                        <a href="views/teacher/class-grades.php" target="_blank" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-primary"><i class="bi bi-journal-text"></i></div>
                        <h6 class="card-title">Bài tập</h6>
                        <a href="views/teacher/class-assignments.php" target="_blank" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-danger"><i class="bi bi-folder2-open"></i></div>
                        <h6 class="card-title">Tài liệu</h6>
                        <a href="views/teacher/documents.php" target="_blank" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-info"><i class="bi bi-megaphone"></i></div>
                        <h6 class="card-title">Thông báo</h6>
                        <a href="views/teacher/announcements.php" target="_blank" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-success"><i class="bi bi-check-circle"></i></div>
                        <h6 class="card-title">Duyệt chứng</h6>
                        <a href="views/teacher/approve-evidences.php" target="_blank" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-dark"><i class="bi bi-person-circle"></i></div>
                        <h6 class="card-title">Hồ sơ</h6>
                        <a href="views/teacher/teacher-profile.php" target="_blank" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- BAN CÁN SỰ -->
            <div class="col-12 mt-4">
                <h4 class="text-white mb-3">
                    <span class="badge bg-warning text-dark role-badge me-2">BAN CÁN SỰ</span>
                </h4>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-warning"><i class="bi bi-grid-1x2"></i></div>
                        <h6 class="card-title">Tổng quan</h6>
                        <a href="views/bcs/home.php" target="_blank" class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-danger"><i class="bi bi-clipboard-data"></i></div>
                        <h6 class="card-title">Báo cáo</h6>
                        <a href="views/bcs/dashboard-detail.php" target="_blank" class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-info"><i class="bi bi-person-lines-fill"></i></div>
                        <h6 class="card-title">Điểm danh</h6>
                        <a href="views/bcs/attendance.php" target="_blank" class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-primary"><i class="bi bi-folder2-open"></i></div>
                        <h6 class="card-title">Tài liệu</h6>
                        <a href="views/bcs/documents.php" target="_blank" class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-success"><i class="bi bi-megaphone"></i></div>
                        <h6 class="card-title">Thông báo</h6>
                        <a href="views/bcs/announcements.php" target="_blank" class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-dark"><i class="bi bi-chat-left-text"></i></div>
                        <h6 class="card-title">Phản hồi</h6>
                        <a href="views/bcs/feedback.php" target="_blank" class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-warning"><i class="bi bi-person-circle"></i></div>
                        <h6 class="card-title">Hồ sơ</h6>
                        <a href="views/bcs/profile.php" target="_blank" class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- ADMIN -->
            <div class="col-12 mt-4">
                <h4 class="text-white mb-3">
                    <span class="badge bg-danger role-badge me-2">QUẢN TRỊ</span>
                </h4>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-danger"><i class="bi bi-grid-1x2"></i></div>
                        <h6 class="card-title">Tổng quan</h6>
                        <a href="views/admin/home.php" target="_blank" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-primary"><i class="bi bi-people"></i></div>
                        <h6 class="card-title">Tài khoản</h6>
                        <a href="views/admin/accounts.php" target="_blank" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-success"><i class="bi bi-building"></i></div>
                        <h6 class="card-title">Lớp & Môn</h6>
                        <a href="views/admin/classes-subjects.php" target="_blank" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-warning"><i class="bi bi-journal-text"></i></div>
                        <h6 class="card-title">Bài tập</h6>
                        <a href="views/admin/assignments.php" target="_blank" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-info"><i class="bi bi-gear"></i></div>
                        <h6 class="card-title">Cài đặt</h6>
                        <a href="views/admin/org-settings.php" target="_blank" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-secondary"><i class="bi bi-clock-history"></i></div>
                        <h6 class="card-title">Nhật ký</h6>
                        <a href="views/admin/system-logs.php" target="_blank" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card preview-card h-100 border-0 shadow">
                    <div class="card-body text-center">
                        <div class="card-icon mb-3 text-dark"><i class="bi bi-person-circle"></i></div>
                        <h6 class="card-title">Hồ sơ</h6>
                        <a href="views/admin/admin-profile.php" target="_blank" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-eye"></i>
                        </a>
                    </div>
                </div>
            </div>

        </div>

        <div class="text-center mt-5">
            <a href="test_db.php" target="_blank" class="btn btn-light btn-lg">
                <i class="bi bi-database-check me-2"></i>Kiểm tra kết nối Database
            </a>
        </div>

        <div class="text-center mt-4 text-white opacity-75">
            <small>CMS BDU - Hệ thống Quản lý Lớp học Thông minh</small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
