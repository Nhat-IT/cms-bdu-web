const passport = require('passport');
const GoogleStrategy = require('passport-google-oauth20').Strategy;
const bcrypt = require('bcryptjs');
const db = require('./db'); // Đường dẫn trỏ đến file kết nối MySQL của bạn

const GOOGLE_CALLBACK_URL = process.env.GOOGLE_CALLBACK_URL
    || process.env.GOOGLE_REDIRECT_URI
    || (process.env.RENDER_EXTERNAL_URL ? `${process.env.RENDER_EXTERNAL_URL}/auth/google/callback` : 'http://localhost:3000/auth/google/callback');

const USERNAME_COLUMNS = ['username', 'user_name', 'account', 'account_name'];
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
        const columnSet = new Set(columns.map((column) => column.Field));

        const schema = {
            hasId: columnSet.has('id'),
            hasEmail: columnSet.has('email'),
            hasName: columnSet.has('name'),
            hasFullName: columnSet.has('full_name'),
            hasGoogleId: columnSet.has('google_id'),
            hasRole: columnSet.has('role'),
            hasAvatar: columnSet.has('avatar'),
            hasUsername: USERNAME_COLUMNS.find((column) => columnSet.has(column)) || null,
            passwordColumn: PASSWORD_COLUMNS.find((column) => columnSet.has(column)) || null
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

const googleAuthEnabled = Boolean(
    process.env.GOOGLE_CLIENT_ID
    && process.env.GOOGLE_CLIENT_SECRET
    && GOOGLE_CALLBACK_URL
);

if (googleAuthEnabled) {
    passport.use(new GoogleStrategy({
        clientID: process.env.GOOGLE_CLIENT_ID,
        clientSecret: process.env.GOOGLE_CLIENT_SECRET,
        callbackURL: GOOGLE_CALLBACK_URL // Phải khớp với trên Google Cloud Console
    },
    async (accessToken, refreshToken, profile, done) => {
        try {
            const email = profile.emails?.[0]?.value;
            const googleId = profile.id;
            const fullName = profile.displayName;
            const avatar = profile.photos?.[0]?.value || null;
            const schema = await resolveUsersSchema();

            if (!email) {
                return done(null, false, { message: 'Google không trả về email hợp lệ' });
            }

            // 1. Kiểm tra domain email trường BDU
            const isStudent = email.endsWith('@student.bdu.edu.vn');
            const isTeacher = email.endsWith('@bdu.edu.vn');

            if (!isStudent && !isTeacher) {
                // Trả về false và thông báo lỗi nếu không phải email BDU
                return done(null, false, { message: 'Vui lòng sử dụng email do BDU cấp!' });
            }

            // 2. Tách lấy MSSV hoặc Mã giảng viên làm username
            const username = email.split('@')[0];
            const role = isStudent ? 'student' : 'teacher';

            // 3. Kiểm tra xem user đã tồn tại trong DB chưa
            const [users] = await db.query('SELECT * FROM users WHERE email = ?', [email]);

            if (users.length > 0) {
                // Đã có tài khoản: Cập nhật thông tin mới nhất từ Google (VD: lỡ họ đổi avatar)
                const user = users[0];
                const updatePairs = [];
                const updateValues = [];

                if (schema.hasGoogleId) {
                    updatePairs.push('google_id = ?');
                    updateValues.push(googleId);
                }
                if (schema.hasAvatar) {
                    updatePairs.push('avatar = ?');
                    updateValues.push(avatar);
                }
                if (schema.hasFullName) {
                    updatePairs.push('full_name = ?');
                    updateValues.push(fullName);
                }
                if (schema.hasName) {
                    updatePairs.push('name = ?');
                    updateValues.push(fullName);
                }

                if (updatePairs.length > 0) {
                    updateValues.push(email);
                    await db.query(
                        `UPDATE users SET ${updatePairs.join(', ')} WHERE email = ?`,
                        updateValues
                    );
                }
                return done(null, user);
            }

            // Chưa có: Tự động đăng ký tài khoản mới và gán Role
            const insertColumns = [];
            const insertValues = [];

            if (schema.hasUsername) {
                insertColumns.push(schema.hasUsername);
                insertValues.push(username);
            }
            if (schema.hasFullName) {
                insertColumns.push('full_name');
                insertValues.push(fullName);
            }
            if (schema.hasName) {
                insertColumns.push('name');
                insertValues.push(fullName);
            }
            if (schema.hasEmail) {
                insertColumns.push('email');
                insertValues.push(email);
            }
            if (schema.hasGoogleId) {
                insertColumns.push('google_id');
                insertValues.push(googleId);
            }
            if (schema.hasRole) {
                insertColumns.push('role');
                insertValues.push(role);
            }
            if (schema.hasAvatar) {
                insertColumns.push('avatar');
                insertValues.push(avatar);
            }
            if (schema.passwordColumn) {
                // Fill password for schemas where password is mandatory for all users.
                const googlePasswordSeed = `google_oauth_${googleId}`;
                const googlePasswordHash = await bcrypt.hash(googlePasswordSeed, 10);
                insertColumns.push(schema.passwordColumn);
                insertValues.push(googlePasswordHash);
            }

            const placeholders = insertColumns.map(() => '?').join(', ');
            const [result] = await db.query(
                `INSERT INTO users (${insertColumns.join(', ')}) VALUES (${placeholders})`,
                insertValues
            );

            const newUser = { id: result.insertId, username, email, role };
            return done(null, newUser);
        } catch (err) {
            console.error('Lỗi xác thực Google:', err);
            return done(err, null);
        }
    }));
    console.log('[Auth] Google OAuth callback URL:', GOOGLE_CALLBACK_URL);
} else {
    console.warn('[Auth] Google OAuth disabled: missing GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, or callback URL');
}

passport.googleAuthEnabled = googleAuthEnabled;

// Lưu ID người dùng vào Session sau khi đăng nhập thành công
passport.serializeUser((user, done) => {
    done(null, user.id);
});

// Lấy thông tin chi tiết của người dùng từ ID lưu trong Session
passport.deserializeUser(async (id, done) => {
    try {
        const [users] = await db.query('SELECT * FROM users WHERE id = ?', [id]);
        done(null, users[0]);
    } catch (err) {
        done(err, null);
    }
});

// Xuất cấu hình này ra để dùng ở file server.js
module.exports = passport;