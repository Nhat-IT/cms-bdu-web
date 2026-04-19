require('dotenv').config();
const express = require('express');
<<<<<<< HEAD
const mysql = require('mysql2/promise');
=======
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
const path = require('path');
const cors = require('cors');
const fs = require('fs');
const { google } = require('googleapis');
const multer = require('multer');

<<<<<<< HEAD
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
=======
// --- THÊM 2 DÒNG NÀY ĐỂ SỬA LỖI PASSPORT ---
const session = require('express-session');
const passport = require('./src/config/passport-config');
const apiRoutes = require('./routes/apiRoutes');
const authRoutes = require('./routes/authRoutes');
const db = require('./src/config/db');

const app = express();
const isProduction = process.env.NODE_ENV === 'production';
const strictDbStartup = process.env.DB_STARTUP_STRICT === 'true';
const staticMaxAge = isProduction ? '7d' : '0';

if (isProduction) {
    app.set('trust proxy', 1);
    if (!process.env.SESSION_SECRET) {
        throw new Error('SESSION_SECRET is required in production');
    }
}

// --- CẤU HÌNH SESSION & PASSPORT ---
app.use(session({
    secret: process.env.SESSION_SECRET || 'change_me_before_production',
    resave: false,
    saveUninitialized: false,
    cookie: {
        httpOnly: true,
        sameSite: 'lax',
        secure: isProduction
    }
}));

app.use(passport.initialize());
app.use(passport.session());

app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// --- CẤU HÌNH FILE TĨNH ---
app.use('/public', express.static(path.join(__dirname, 'public'), {
    maxAge: staticMaxAge,
    etag: true,
    setHeaders: (res, filePath) => {
        if (/\.(css|js|png|jpg|jpeg|gif|svg|webp|woff|woff2|ttf)$/i.test(filePath)) {
            res.setHeader('Cache-Control', isProduction
                ? 'public, max-age=604800, stale-while-revalidate=86400'
                : 'no-cache');
        }
    }
}));

app.use('/', express.static(path.join(__dirname, 'views'), {
    etag: true,
    setHeaders: (res, filePath) => {
        if (/\.html$/i.test(filePath)) {
            // Always revalidate HTML to reduce stale markup and FOUC-style flashes.
            res.setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
            res.setHeader('Pragma', 'no-cache');
            res.setHeader('Expires', '0');
        }
    }
}));

// --- SỬ DỤNG CÁC ROUTES ---
app.use('/api', apiRoutes);
app.use('/auth', authRoutes);

// --- CẤU HÌNH GOOGLE DRIVE API ---
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
const KEYFILEPATH = path.join(__dirname, 'drive-credentials.json');
const SCOPES = ['https://www.googleapis.com/auth/drive.file'];

const auth = new google.auth.GoogleAuth({
    keyFile: KEYFILEPATH,
    scopes: SCOPES,
});
const driveService = google.drive({ version: 'v3', auth });

<<<<<<< HEAD
// Cấu hình Multer để tạm lưu file khi upload (sẽ xóa sau khi đẩy lên Drive)
=======
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
const upload = multer({ dest: 'uploads/' });

// --- CÁC ĐƯỜNG DẪN GIAO DIỆN ---
app.get('/', (req, res) => {
    res.redirect('/login.html'); 
});

<<<<<<< HEAD
// --- API HỆ THỐNG ---

// 1. Test kết nối DB
app.get('/api/test-db', async (req, res) => {
    try {
        const [rows] = await pool.query('SELECT 1 + 1 AS solution');
        res.json({ success: true, message: 'Database connected successfully!', data: rows });
=======
app.get('/student', (req, res) => {
    res.redirect('/student/home.html');
});

app.get('/notifications', (req, res) => {
    res.redirect('/student/notifications-all.html');
});

// --- API HỆ THỐNG ---

app.get('/api/health/db', async (req, res) => {
    try {
        await db.testConnection();
        return res.json({ ok: true, service: 'database' });
    } catch (error) {
        return res.status(500).json({
            ok: false,
            service: 'database',
            error: error.code || error.message
        });
    }
});

// Tạo tài khoản test - truy cập: /api/create-test-user?role=student&username=test&password=123456
app.get('/api/create-test-user', async (req, res) => {
    try {
        const bcrypt = require('bcryptjs');
        const role = req.query.role || 'student';
        const username = req.query.username || `test_${role}`;
        const password = req.query.password || '123456';
        const hashedPassword = await bcrypt.hash(password, 10);

        const email = `${username}@student.bdu.edu.vn`;
        const fullName = `Test ${role.charAt(0).toUpperCase() + role.slice(1)}`;

        const [existing] = await db.query('SELECT id FROM users WHERE username = ?', [username]);

        if (existing.length > 0) {
            await db.query('UPDATE users SET password = ?, role = ? WHERE username = ?', [hashedPassword, role, username]);
            return res.json({ success: true, message: 'Đã cập nhật tài khoản', username, password, role });
        } else {
            await db.query('INSERT INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)',
                [username, hashedPassword, email, fullName, role]);
            return res.json({ success: true, message: 'Đã tạo tài khoản mới', username, password, role });
        }
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

<<<<<<< HEAD
/**
 * 2. API UPLOAD FILE LÊN GOOGLE DRIVE
 * Sử dụng cho: Nộp minh chứng điểm danh, nộp bài tập...
 */
=======
// 1. Upload Google Drive
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
app.post('/api/upload-to-drive', upload.single('file'), async (req, res) => {
    try {
        if (!req.file) {
            return res.status(400).json({ success: false, message: 'Không có file nào được tải lên.' });
        }

<<<<<<< HEAD
        // Thông tin file Metadata (Có thể thay đổi folderId theo yêu cầu)
        const fileMetadata = {
            name: req.file.originalname,
            parents: [process.env.GOOGLE_DRIVE_FOLDER_ID], // ID thư mục lưu trữ lấy từ .env
=======
        const fileMetadata = {
            name: req.file.originalname,
            parents: [process.env.GOOGLE_DRIVE_FOLDER_ID],
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
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

<<<<<<< HEAD
        // Xóa file tạm trong thư mục uploads/ trên server
=======
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
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

<<<<<<< HEAD
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

=======
// ==============================================
// 3. DUPLICATE AUTH ROUTES - MOVED TO authRoutes.js
// ==============================================


// --- KHỞI CHẠY SERVER ---
const PORT = process.env.PORT || 3000;

async function startServer() {
    try {
        await db.testConnection({ retries: 2, delayMs: 1000 });
        console.log('✅ Database connected successfully.');
    } catch (error) {
        if (strictDbStartup) {
            console.error('❌ Cannot connect to database. Server startup aborted.');
            console.error(error.message);
            process.exit(1);
        }

        console.error('⚠️ Database is currently unavailable at startup.');
        console.error(`⚠️ Continuing server boot (DB_STARTUP_STRICT=false). Detail: ${error.code || error.message}`);
    }

    app.listen(PORT, () => {
        console.log(`🚀 Server is running on port ${PORT}`);
        console.log(`📁 Thư mục tĩnh: ${path.join(__dirname, 'public')}`);
    });
}

startServer();
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
