const express = require('express');
const passport = require('passport');

const router = express.Router();

function ensureGoogleAuthEnabled(req, res, next) {
  if (!passport.googleAuthEnabled) {
    return res.redirect('/login.html?error=google_oauth_not_configured');
  }
  return next();
}

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
