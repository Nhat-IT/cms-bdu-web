require('dotenv').config();
const express = require('express');
const mysql = require('mysql2/promise');
const path = require('path');
const cors = require('cors');
const fs = require('fs');
const { google } = require('googleapis');
const multer = require('multer');

const app = express();
app.use(cors());
app.use(express.json());

// --- CẤU HÌNH FILE TĨNH ---
app.use('/public', express.static(path.join(__dirname, 'public')));
app.use('/', express.static(path.join(__dirname, 'views')));

// --- CẤU HÌNH DATABASE (CLEVER CLOUD) ---
const dbConfig = {
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME,
    port: process.env.DB_PORT,
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
};
const pool = mysql.createPool(dbConfig);

// --- CẤU HÌNH GOOGLE DRIVE API ---
// Lưu ý: Đảm bảo file drive-credentials.json nằm cùng cấp với server.js
const KEYFILEPATH = path.join(__dirname, 'drive-credentials.json');
const SCOPES = ['https://www.googleapis.com/auth/drive.file'];

const auth = new google.auth.GoogleAuth({
    keyFile: KEYFILEPATH,
    scopes: SCOPES,
});
const driveService = google.drive({ version: 'v3', auth });

// Cấu hình Multer để tạm lưu file khi upload (sẽ xóa sau khi đẩy lên Drive)
const upload = multer({ dest: 'uploads/' });

// --- CÁC ĐƯỜNG DẪN GIAO DIỆN ---
app.get('/', (req, res) => {
    res.redirect('/login.html'); 
});

// --- API HỆ THỐNG ---

// 1. Test kết nối DB
app.get('/api/test-db', async (req, res) => {
    try {
        const [rows] = await pool.query('SELECT 1 + 1 AS solution');
        res.json({ success: true, message: 'Database connected successfully!', data: rows });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

/**
 * 2. API UPLOAD FILE LÊN GOOGLE DRIVE
 * Sử dụng cho: Nộp minh chứng điểm danh, nộp bài tập...
 */
app.post('/api/upload-to-drive', upload.single('file'), async (req, res) => {
    try {
        if (!req.file) {
            return res.status(400).json({ success: false, message: 'Không có file nào được tải lên.' });
        }

        // Thông tin file Metadata (Có thể thay đổi folderId theo yêu cầu)
        const fileMetadata = {
            name: req.file.originalname,
            parents: [process.env.GOOGLE_DRIVE_FOLDER_ID], // ID thư mục lưu trữ lấy từ .env
        };

        const media = {
            mimeType: req.file.mimetype,
            body: fs.createReadStream(req.file.path),
        };

        const response = await driveService.files.create({
            resource: fileMetadata,
            media: media,
            fields: 'id, webViewLink',
        });

        // Xóa file tạm trong thư mục uploads/ trên server
        fs.unlinkSync(req.file.path);

        res.json({
            success: true,
            message: 'Tải lên Google Drive thành công!',
            fileId: response.data.id,
            link: response.data.webViewLink
        });

    } catch (error) {
        console.error('Drive Upload Error:', error);
        res.status(500).json({ success: false, error: error.message });
    }
});

// Route bắt đầu đăng nhập
app.get('/auth/google',
  passport.authenticate('google', { scope: ['profile', 'email'] })
);

// Route xử lý kết quả trả về từ Google
app.get('/auth/google/callback', 
  passport.authenticate('google', { failureRedirect: '/login?error=auth_failed' }),
  (req, res) => {
    // Dựa vào Role để đưa người dùng về đúng Dashboard
    if (req.user.role === 'teacher') {
        res.redirect('/teacher/dashboard');
    } else {
        res.redirect('/student/dashboard');
    }
  }
);

// Route đăng xuất
app.get('/logout', (req, res) => {
    req.logout(() => {
        res.redirect('/login');
    });
});

// --- KHỞI CHẠY SERVER ---
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`🚀 Server is running on port ${PORT}`);
    console.log(`📁 Thư mục tĩnh: ${path.join(__dirname, 'public')}`);
});

