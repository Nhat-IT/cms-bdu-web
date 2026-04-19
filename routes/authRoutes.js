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

    console.log('[Auth] Login attempt for username:', username);

    if (!username || !password) {
      return res.status(400).json({ success: false, message: 'Vui lòng nhập tên đăng nhập và mật khẩu' });
    }

    const { identityColumns, passwordColumn } = await resolveUsersSchema();
    console.log('[Auth] Schema resolved:', { identityColumns, passwordColumn });

    if (!identityColumns.length || !passwordColumn) {
      console.error('[Auth] Schema error: missing identity or password column');
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

    console.log('[Auth] Query:', `SELECT * FROM users WHERE ${whereClause}`);
    console.log('[Auth] Query values:', queryValues);

    const [users] = await db.query(
      `SELECT * FROM users WHERE ${whereClause} LIMIT 1`,
      queryValues
    );
    console.log('[Auth] User found:', users.length > 0, users.length > 0 ? 'Yes' : 'No');

    if (users.length === 0) {
      return res.status(401).json({ success: false, message: 'Tên đăng nhập hoặc mật khẩu không chính xác' });
    }

    const user = users[0];
    console.log('[Auth] User data keys:', Object.keys(user));
    console.log('[Auth] User role:', user.role);
    console.log('[Auth] Password column used:', passwordColumn);
    console.log('[Auth] Stored password (first 20 chars):', String(user[passwordColumn] || '').substring(0, 20));

    const storedPassword = String(user[passwordColumn] || '');

    // Support legacy plain-text passwords while keeping bcrypt for hashed ones.
    let passwordMatch = false;
    if (storedPassword.startsWith('$2a$') || storedPassword.startsWith('$2b$') || storedPassword.startsWith('$2y$')) {
      passwordMatch = await bcrypt.compare(password, storedPassword);
      console.log('[Auth] Using bcrypt comparison, result:', passwordMatch);
    } else {
      passwordMatch = password === storedPassword;
      console.log('[Auth] Using plain text comparison, result:', passwordMatch);
    }

    if (!passwordMatch) {
      console.log('[Auth] Password mismatch');
      return res.status(401).json({ success: false, message: 'Tên đăng nhập hoặc mật khẩu không chính xác' });
    }

    console.log('[Auth] Password matched, attempting session login...');

    req.logIn(user, (err) => {
      if (err) {
        console.error('[Auth] Session login error:', err);
        return res.status(500).json({ success: false, message: 'Lỗi đăng nhập: ' + err.message });
      }

      console.log('[Auth] Session login successful, user id:', req.session?.passport?.user);

      const normalizedRole = String(user.role || '').trim().toLowerCase();
      let redirectUrl = '/student/home.html';
      if (normalizedRole === 'teacher') {
        redirectUrl = '/teacher/home.html';
      } else if (normalizedRole === 'admin') {
        redirectUrl = '/admin/home.html';
      } else if (normalizedRole === 'bcs') {
        redirectUrl = '/bcs/home.html';
      }

      console.log('[Auth] Redirect URL:', redirectUrl);

      // Ensure session is saved before sending response
      req.session.save((saveErr) => {
        if (saveErr) {
          console.error('[Auth] Session save error:', saveErr);
          return res.status(500).json({ success: false, message: 'Lỗi lưu phiên đăng nhập' });
        }
        console.log('[Auth] Session saved successfully');
        return res.json({ success: true, message: 'Đăng nhập thành công', redirectUrl });
      });
    });
  } catch (error) {
    console.error('[Auth] Catch error:', error);
    res.status(500).json({ success: false, message: 'Lỗi server: ' + error.message });
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
