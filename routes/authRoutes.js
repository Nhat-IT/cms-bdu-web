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
  // Dev mode: disable Google OAuth
  return res.redirect('/login.html?info=google_disabled');
}
// ========== BYPASS LOGIN (dev only) ==========
// Bỏ qua xác thực, đăng nhập trực tiếp với username và role chỉ định
// Dùng để dev/test - không dùng trong production
router.post('/bypass-login', async (req, res) => {
  try {
    const username = String(req.body?.username || '').trim();

    if (!username) {
      return res.status(400).json({ success: false, message: 'Thiếu username' });
    }

    const [users] = await db.query(
      'SELECT * FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1',
      [username]
    );

    if (users.length === 0) {
      return res.status(401).json({ success: false, message: 'Không tìm thấy user' });
    }

    const user = users[0];

    req.logIn(user, (err) => {
      if (err) {
        return res.status(500).json({ success: false, message: 'Lỗi đăng nhập' });
      }

      req.session.save((saveErr) => {
        if (saveErr) {
          return res.status(500).json({ success: false, message: 'Lỗi lưu phiên' });
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

        return res.json({ success: true, message: 'Đăng nhập thành công (bypass)', redirectUrl });
      });
    });
  } catch (error) {
    res.status(500).json({ success: false, message: 'Lỗi server' });
  }
});

// ========== LOCAL AUTHENTICATION - DISABLED (dev only, use /auth/bypass-login) ==========
router.post('/login', async (req, res) => {
  return res.redirect('/login.html?info=login_disabled');
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
