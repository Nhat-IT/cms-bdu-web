const express = require('express');
const passport = require('passport');

const router = express.Router();

const bcrypt = require('bcryptjs');
const db = require('../src/config/db');

const LOGIN_IDENTITY_COLUMNS = ['username', 'user_name', 'account', 'account_name', 'email'];
const PASSWORD_COLUMNS = ['password', 'user_password', 'passwd'];
const USERS_SCHEMA_TTL_MS = Number(process.env.USERS_SCHEMA_CACHE_TTL_MS || 300000);

let usersSchemaCache = null;
let usersSchemaCacheAt = 0;
let usersSchemaInFlight = null;

async function resolveUsersSchema() {
  const now = Date.now();
  if (usersSchemaCache && now - usersSchemaCacheAt < USERS_SCHEMA_TTL_MS) {
    return usersSchemaCache;
  }

  if (usersSchemaInFlight) {
    return usersSchemaInFlight;
  }

  usersSchemaInFlight = (async () => {
    const [columns] = await db.query('SHOW COLUMNS FROM users');
    const columnSet = new Set(columns.map((c) => c.Field));

    const schema = {
      identityColumns: LOGIN_IDENTITY_COLUMNS.filter((c) => columnSet.has(c)),
      passwordColumn: PASSWORD_COLUMNS.find((c) => columnSet.has(c))
    };

    usersSchemaCache = schema;
    usersSchemaCacheAt = Date.now();
    return schema;
  })();

  try {
    return await usersSchemaInFlight;
  } finally {
    usersSchemaInFlight = null;
  }
}

function ensureGoogleAuthEnabled(req, res, next) {
  if (!passport.googleAuthEnabled) {
    return res.redirect('/login.html?error=google_oauth_not_configured');
  }
  return next();
}
// ========== LOCAL AUTHENTICATION (USERNAME + PASSWORD) ==========
router.post('/login', async (req, res) => {
  try {
    const username = String(req.body?.username || '').trim();
    const password = String(req.body?.password || '');

    if (!username || !password) {
      return res.status(400).json({ success: false, message: 'Vui lòng nhập tên đăng nhập và mật khẩu' });
    }

    const { identityColumns, passwordColumn } = await resolveUsersSchema();
    console.log('[Auth] Login schema:', {
      identityColumns,
      passwordColumn,
      loginInputLength: String(username || '').length
    });

    if (!identityColumns.length || !passwordColumn) {
      return res.status(500).json({
        success: false,
        message: 'Cấu trúc bảng users chưa đúng (thiếu cột đăng nhập hoặc mật khẩu)'
      });
    }

    const loginCandidates = [username];

    if (!String(username).includes('@') && identityColumns.length === 1 && identityColumns[0] === 'email') {
      const normalizedUsername = String(username).replace(/_bdu$/i, '');
      const derivedEmails = [
        `${normalizedUsername}@bdu.edu.vn`,
        `${normalizedUsername}@student.bdu.edu.vn`
      ];
      derivedEmails.forEach((candidate) => loginCandidates.push(candidate));
    }

    const whereClause = identityColumns.map((col) => {
      const candidatePredicates = loginCandidates.map(() => `LOWER(${col}) = LOWER(?)`).join(' OR ');
      return `(${candidatePredicates})`;
    }).join(' OR ');
    const queryValues = identityColumns.flatMap(() => loginCandidates);

    const [users] = await db.query(
      `SELECT * FROM users WHERE ${whereClause} LIMIT 1`,
      queryValues
    );
    console.log('[Auth] Login lookup result:', { found: users.length > 0 });

    if (users.length === 0) {
      return res.status(401).json({ success: false, message: 'Tên đăng nhập hoặc mật khẩu không chính xác' });
    }

    const user = users[0];
    const storedPassword = String(user[passwordColumn] || '');

    // Support legacy plain-text passwords while keeping bcrypt for hashed ones.
    let passwordMatch = false;
    if (storedPassword.startsWith('$2a$') || storedPassword.startsWith('$2b$') || storedPassword.startsWith('$2y$')) {
      passwordMatch = await bcrypt.compare(password, storedPassword);
    } else {
      passwordMatch = password === storedPassword;
    }

    if (!passwordMatch) {
      console.log('[Auth] Login password mismatch for matched user');
      return res.status(401).json({ success: false, message: 'Tên đăng nhập hoặc mật khẩu không chính xác' });
    }

    req.logIn(user, (err) => {
      if (err) {
        console.error('Login error:', err);
        return res.status(500).json({ success: false, message: 'Lỗi đăng nhập' });
      }

      const normalizedRole = String(user.role || '').trim().toLowerCase();
      let redirectUrl = '/student/home.html';
      if (normalizedRole === 'teacher') {
        redirectUrl = '/teacher/home.html';
      } else if (normalizedRole === 'admin') {
        redirectUrl = '/admin/home.html';
      } else if (normalizedRole === 'bcs') {
        redirectUrl = '/bcs/home.html';
      }

      return res.json({ success: true, message: 'Đăng nhập thành công', redirectUrl });
    });
  } catch (error) {
    console.error('Login error:', error);
    res.status(500).json({ success: false, message: 'Lỗi server' });
  }
});


// ========== GOOGLE AUTHENTICATION ==========
// Route bắt đầu đăng nhập
router.get('/google',
  ensureGoogleAuthEnabled,
  passport.authenticate('google', { scope: ['profile', 'email'] })
);

// Route xử lý kết quả trả về từ Google
router.get('/google/callback', 
  ensureGoogleAuthEnabled,
  passport.authenticate('google', { failureRedirect: '/login.html?error=auth_failed' }),
  (req, res) => {
    // req.user chứa thông tin lấy từ database
    if (req.user && req.user.role === 'teacher') {
        res.redirect('/teacher/home.html');
    } else if (req.user && req.user.role === 'admin') {
        res.redirect('/admin/home.html');
    } else if (req.user && req.user.role === 'bcs') {
        res.redirect('/bcs/home.html');
    } else {
        res.redirect('/student/home.html');
    }
  }
);

// ========== LOGOUT ==========
router.get('/logout', (req, res, next) => {
    req.logout((err) => {
        if (err) { return next(err); }
        res.redirect('/login.html');
    });
});

module.exports = router;
