const express = require('express');
const passport = require('passport');

const router = express.Router();

const bcrypt = require('bcryptjs');
const db = require('../src/config/db');

function ensureGoogleAuthEnabled(req, res, next) {
  if (!passport.googleAuthEnabled) {
    return res.redirect('/login.html?error=google_oauth_not_configured');
  }
  return next();
}
// ========== LOCAL AUTHENTICATION (USERNAME + PASSWORD) ==========
router.post('/login', async (req, res) => {
  try {
    const { username, password } = req.body;

    if (!username || !password) {
      return res.status(400).json({ success: false, message: 'Vui lòng nhập tên đăng nhập và mật khẩu' });
    }

    const [users] = await db.query(
      'SELECT * FROM users WHERE username = ? OR email = ?',
      [username, username]
    );

    if (users.length === 0) {
      return res.status(401).json({ success: false, message: 'Tên đăng nhập hoặc mật khẩu không chính xác' });
    }

    const user = users[0];
    const passwordMatch = await bcrypt.compare(password, user.password || '');

    if (!passwordMatch) {
      return res.status(401).json({ success: false, message: 'Tên đăng nhập hoặc mật khẩu không chính xác' });
    }

    req.logIn(user, (err) => {
      if (err) {
        console.error('Login error:', err);
        return res.status(500).json({ success: false, message: 'Lỗi đăng nhập' });
      }

      let redirectUrl = '/student/home.html';
      if (user.role === 'teacher') {
        redirectUrl = '/teacher/home.html';
      } else if (user.role === 'admin') {
        redirectUrl = '/admin/home.html';
      } else if (user.role === 'bcs') {
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
