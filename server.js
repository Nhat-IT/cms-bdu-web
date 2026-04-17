require('dotenv').config();
const express = require('express');
const mysql = require('mysql2/promise');
const path = require('path');
const cors = require('cors');
const fs = require('fs');
const { google } = require('googleapis');
const multer = require('multer');

// --- THÊM 2 DÒNG NÀY ĐỂ SỬA LỖI PASSPORT ---
const session = require('express-session');
const passport = require('./config/passport-config'); // Trỏ tới file cấu hình passport của bạn
const apiRoutes = require('./routes/apiRoutes');
const authRoutes = require('./routes/authRoutes');

const app = express();

// --- CẤU HÌNH SESSION & PASSPORT ---
app.use(session({
    secret: process.env.SESSION_SECRET || 'bdu_default_secret_key_2026',
    resave: false,
    saveUninitialized: true
}));

app.use(passport.initialize());
app.use(passport.session());

app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// --- CẤU HÌNH FILE TĨNH ---
app.use('/public', express.static(path.join(__dirname, 'public')));
app.use('/', express.static(path.join(__dirname, 'views')));

// --- SỬ DỤNG CÁC ROUTES ---
app.use('/api', apiRoutes);
app.use('/auth', authRoutes);

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
const KEYFILEPATH = path.join(__dirname, 'drive-credentials.json');
const SCOPES = ['https://www.googleapis.com/auth/drive.file'];

const auth = new google.auth.GoogleAuth({
    keyFile: KEYFILEPATH,
    scopes: SCOPES,
});
const driveService = google.drive({ version: 'v3', auth });

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

// 2. Upload Google Drive
app.post('/api/upload-to-drive', upload.single('file'), async (req, res) => {
    try {
        if (!req.file) {
            return res.status(400).json({ success: false, message: 'Không có file nào được tải lên.' });
        }

        const fileMetadata = {
            name: req.file.originalname,
            parents: [process.env.GOOGLE_DRIVE_FOLDER_ID],
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

// ==============================================
// 3. DUPLICATE AUTH ROUTES - MOVED TO authRoutes.js
// ==============================================


// --- KHỞI CHẠY SERVER ---
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`🚀 Server is running on port ${PORT}`);
    console.log(`📁 Thư mục tĩnh: ${path.join(__dirname, 'public')}`);
});