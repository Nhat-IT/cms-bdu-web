<?php
/**
 * CMS BDU - Trang lỗi 500
 */

require_once __DIR__ . '/config/config.php';
http_response_code(500);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lỗi hệ thống - CMS BDU</title>
    <style>
        :root {
            --bg: #f4f7fb;
            --card: #ffffff;
            --text: #16212e;
            --muted: #5b6573;
            --accent: #1e63d6;
            --danger: #d0342c;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: radial-gradient(circle at top left, #dfeeff 0%, var(--bg) 45%, #edf2f8 100%);
            color: var(--text);
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .card {
            width: 100%;
            max-width: 640px;
            background: var(--card);
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 16px 40px rgba(20, 31, 48, 0.12);
        }

        h1 {
            margin: 0 0 10px;
            font-size: 28px;
        }

        p {
            margin: 8px 0;
            color: var(--muted);
            line-height: 1.55;
        }

        .tag {
            display: inline-block;
            margin-top: 6px;
            margin-bottom: 8px;
            color: var(--danger);
            font-weight: 700;
        }

        .actions {
            margin-top: 18px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            text-decoration: none;
            border-radius: 10px;
            padding: 10px 14px;
            font-weight: 600;
            border: 1px solid transparent;
        }

        .btn-primary {
            background: var(--accent);
            color: #fff;
        }

        .btn-secondary {
            background: #fff;
            border-color: #d7dce3;
            color: #1c2b3d;
        }

        code {
            background: #f0f3f7;
            padding: 2px 6px;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <main class="card">
        <h1>Hệ thống tạm thời bị lỗi</h1>
        <div class="tag">HTTP 500</div>
        <p>Trang đang gặp sự cố nội bộ. Vui lòng thử tải lại sau ít phút.</p>
        <p>Nếu lỗi liên tục xảy ra, hãy kiểm tra cấu hình database trong <code>config/env.local</code> hoặc <code>.env.local</code>.</p>
        <div class="actions">
            <a class="btn btn-primary" href="<?php echo BASE_URL; ?>/login.php">Thử lại đăng nhập</a>
            <a class="btn btn-secondary" href="<?php echo BASE_URL; ?>/">Về trang chủ</a>
        </div>
    </main>
</body>
</html>
