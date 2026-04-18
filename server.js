require('dotenv').config();
const express = require('express');
const path = require('path');
const cors = require('cors');
const fs = require('fs');
const { google } = require('googleapis');
const multer = require('multer');

// --- THÊM 2 DÒNG NÀY ĐỂ SỬA LỖI PASSPORT ---
const session = require('express-session');
const passport = require('./src/config/passport-config');
const apiRoutes = require('./routes/apiRoutes');
const authRoutes = require('./routes/authRoutes');

const app = express();
const isProduction = process.env.NODE_ENV === 'production';

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
app.use('/public', express.static(path.join(__dirname, 'public')));
app.use('/', express.static(path.join(__dirname, 'views')));

// --- SỬ DỤNG CÁC ROUTES ---
app.use('/api', apiRoutes);
app.use('/auth', authRoutes);

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

// 1. Upload Google Drive
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