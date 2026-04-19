const passport = require('passport');
const GoogleStrategy = require('passport-google-oauth20').Strategy;
const db = require('./db');

passport.use(new GoogleStrategy({
    clientID: process.env.GOOGLE_CLIENT_ID,
    clientSecret: process.env.GOOGLE_CLIENT_SECRET,
    callbackURL: "/auth/google/callback" // Đường dẫn này phải khớp với cấu hình trên Google Console
  },
  async (accessToken, refreshToken, profile, done) => {
    try {
        const email = profile.emails[0].value;
        const googleId = profile.id;
        const fullName = profile.displayName;
        const avatar = profile.photos[0].value;

        // 1. Kiểm tra domain email trường BDU
        const isStudent = email.endsWith('@student.bdu.edu.vn');
        const isTeacher = email.endsWith('@bdu.edu.vn');

        if (!isStudent && !isTeacher) {
            return done(null, false, { message: 'Vui lòng sử dụng email do BDU cấp!' });
        }

        // 2. Tách lấy MSSV hoặc Mã giảng viên làm username
        const username = email.split('@')[0];
        const role = isStudent ? 'student' : 'teacher';

        // 3. Kiểm tra xem user đã tồn tại trong DB chưa
        const [users] = await db.query('SELECT * FROM users WHERE email = ?', [email]);

        if (users.length > 0) {
            // Đã có tài khoản: Cập nhật thông tin mới nhất từ Google
            const user = users[0];
            await db.query(
                'UPDATE users SET google_id = ?, avatar = ?, full_name = ? WHERE id = ?',
                [googleId, avatar, fullName, user.id]
            );
            return done(null, user);
        } else {
            // Chưa có: Tự động đăng ký tài khoản mới và gán Role
            const [result] = await db.query(
                'INSERT INTO users (username, full_name, email, google_id, role, avatar) VALUES (?, ?, ?, ?, ?, ?)',
                [username, fullName, email, googleId, role, avatar]
            );
            const newUser = { id: result.insertId, username, email, role };
            return done(null, newUser);
        }
    } catch (err) {
        return done(err, null);
    }
  }
));

// Lưu và lấy thông tin user từ Session
passport.serializeUser((user, done) => done(null, user.id));
passport.deserializeUser(async (id, done) => {
    const [users] = await db.query('SELECT * FROM users WHERE id = ?', [id]);
    done(null, users[0]);
});