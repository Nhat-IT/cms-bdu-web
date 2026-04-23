<?php
/**
 * CMS BDU - Trang lỗi 404
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/helpers.php';

// Xác định link Trang chủ
$homeUrl = isLoggedIn() ? getHomeUrl($_SESSION['role'] ?? '') : BASE_URL . '/login.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Trang không tìm thấy - CMS BDU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .error-card { background: white; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center">
    <div class="text-center p-5 error-card">
        <div class="mb-4">
            <h1 class="display-1 fw-bold text-primary">404</h1>
            <i class="bi bi-emoji-frown text-muted" style="font-size: 4rem;"></i>
        </div>
        <h2 class="fw-bold text-dark mb-3">Trang không tìm thấy!</h2>
        <p class="text-muted mb-4">Trang bạn đang tìm kiếm không tồn tại hoặc đã bị di chuyển.</p>
        <div class="d-flex gap-2 justify-content-center">
            <a href="javascript:history.back()" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i> Quay lại
            </a>
            <a href="<?php echo $homeUrl; ?>" class="btn btn-primary">
                <i class="bi bi-house me-2"></i> Trang chủ
            </a>
        </div>
    </div>
</body>
</html>
