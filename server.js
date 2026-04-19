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
