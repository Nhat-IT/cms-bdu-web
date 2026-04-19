<?php
/**
 * CMS BDU - Trang không có quyền truy cập
 */

require_once __DIR__ . '/../config/session.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Không có quyền - CMS BDU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="text-center">
        <div class="mb-4">
            <i class="bi bi-shield-exclamation text-danger" style="font-size: 5rem;"></i>
        </div>
        <h2 class="fw-bold text-dark mb-3">Không có quyền truy cập</h2>
        <p class="text-muted mb-4">Bạn không có quyền truy cập trang này.</p>
        <a href="login.php" class="btn btn-primary">
            <i class="bi bi-house me-2"></i> Quay về trang chủ
        </a>
    </div>
</body>
</html>
