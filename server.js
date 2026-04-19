require('dotenv').config();
const express = require('express');
const session = require('express-session');
const passport = require('./src/config/passport-config');
const mysql = require('mysql2/promise');
const path = require('path');
const cors = require('cors');
const fs = require('fs');
const multer = require('multer');

const app = express();
const isProduction = process.env.NODE_ENV === 'production';

// Trust proxy in production
if (isProduction) {
    app.set('trust proxy', 1);
}

// Session config
app.use(session({
    secret: process.env.SESSION_SECRET || 'cms-bdu-secret-key',
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

// Static files
const staticMaxAge = isProduction ? '7d' : '0';
app.use('/public', express.static(path.join(__dirname, 'public'), {
    maxAge: staticMaxAge
}));
app.use('/', express.static(path.join(__dirname, 'views')));

// Routes
const apiRoutes = require('./routes/apiRoutes');
const authRoutes = require('./routes/authRoutes');
app.use('/api', apiRoutes);
app.use('/auth', authRoutes);

// Database pool
const dbConfig = {
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASS,
    database: process.env.DB_NAME,
    port: process.env.DB_PORT || 3306,
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
};
const pool = mysql.createPool(dbConfig);

// Multer for file uploads
const upload = multer({ dest: 'uploads/' });

// Redirects
app.get('/', (req, res) => res.redirect('/login.html'));
app.get('/student', (req, res) => res.redirect('/student/home.html'));
app.get('/notifications', (req, res) => res.redirect('/student/notifications-all.html'));

// Health check - DB
app.get('/api/health/db', async (req, res) => {
    try {
        await pool.query('SELECT 1');
        res.json({ ok: true, service: 'database' });
    } catch (error) {
        res.status(500).json({ ok: false, service: 'database', error: error.message });
    }
});

// Create test user
app.get('/api/create-test-user', async (req, res) => {
    try {
        const bcrypt = require('bcryptjs');
        const role = req.query.role || 'student';
        const username = req.query.username || `test_${role}`;
        const password = req.query.password || '123456';
        const hashedPassword = await bcrypt.hash(password, 10);
        const email = `${username}@bdu.edu.vn`;
        const fullName = `Test ${role.charAt(0).toUpperCase() + role.slice(1)}`;

        const [existing] = await pool.query('SELECT id FROM users WHERE username = ?', [username]);

        if (existing.length > 0) {
            await pool.query('UPDATE users SET password = ?, role = ? WHERE username = ?', 
                [hashedPassword, role, username]);
            res.json({ success: true, message: 'Da cap nhat tai khoan', username, password, role });
        } else {
            await pool.query('INSERT INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)',
                [username, hashedPassword, email, fullName, role]);
            res.json({ success: true, message: 'Da tao tai khoan moi', username, password, role });
        }
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

// Start server
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`Server running on port ${PORT}`);
});
