const GoogleStrategy = require('passport-google-oauth20').Strategy;
// Giả sử bạn có file db.js để query MySQL
const db = require('./db'); 

passport.use(new GoogleStrategy({
    clientID: process.env.GOOGLE_CLIENT_ID,
    clientSecret: process.env.GOOGLE_CLIENT_SECRET,
    callbackURL: "/auth/google/callback"
  },
  async function(accessToken, refreshToken, profile, done) {
    try {
        const userEmail = profile.emails[0].value;
        const googleId = profile.id;
        const fullName = profile.displayName;
        const avatar = profile.photos[0].value;

        // 1. KIỂM TRA ĐUÔI EMAIL
        const isStudent = userEmail.endsWith('@student.bdu.edu.vn');
        const isTeacher = userEmail.endsWith('@bdu.edu.vn');

        if (!isStudent && !isTeacher) {
            // Từ chối đăng nhập nếu dùng email cá nhân (như @gmail.com)
            return done(null, false, { message: 'Vui lòng sử dụng email do BDU cấp!' });
        }

        // 2. XÁC ĐỊNH VAI TRÒ (ROLE) VÀ USERNAME
        // Nếu là sinh viên, cắt phần trước @ làm MSSV (username). Nếu giảng viên cũng tương tự.
        const username = userEmail.split('@')[0]; 
        const role = isStudent ? 'student' : 'teacher';

        // 3. TÌM HOẶC TẠO TÀI KHOẢN TRONG DATABASE
        const [rows] = await db.query('SELECT * FROM users WHERE email = ?', [userEmail]);
        
        if (rows.length > 0) {
            // Đã có tài khoản -> Cập nhật lại avatar và google_id (nếu trước đó họ chưa dùng Google)
            const user = rows[0];
            await db.query('UPDATE users SET google_id = ?, avatar = ? WHERE id = ?', [googleId, avatar, user.id]);
            return done(null, user);
        } else {
            // Chưa có tài khoản -> Tự động đăng ký mới dựa trên thông tin Google
            const [result] = await db.query(
                `INSERT INTO users (username, full_name, email, google_id, role, avatar) 
                 VALUES (?, ?, ?, ?, ?, ?)`,
                [username, fullName, userEmail, googleId, role, avatar]
            );
            
            const newUser = {
                id: result.insertId,
                username: username,
                full_name: fullName,
                email: userEmail,
                role: role
            };
            return done(null, newUser);
        }

    } catch (error) {
        return done(error, null);
    }
  }
));