const mysql = require('mysql2/promise');

function ensureRequiredEnv() {
    const required = ['DB_HOST', 'DB_USER', 'DB_NAME'];
    const missing = required.filter((key) => !process.env[key]);

    if (missing.length > 0) {
        throw new Error(`Missing DB env: ${missing.join(', ')}`);
    }
}

ensureRequiredEnv();

const pool = mysql.createPool({
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME,
    port: Number(process.env.DB_PORT || 3306),
    waitForConnections: true,
    connectionLimit: Number(process.env.DB_CONNECTION_LIMIT || 10),
    queueLimit: 0,
    charset: 'utf8mb4',
    timezone: process.env.DB_TIMEZONE || 'Z',
    ssl: process.env.DB_SSL === 'true' ? { rejectUnauthorized: false } : undefined
});

pool.testConnection = async () => {
    const conn = await pool.getConnection();
    try {
        await conn.ping();
    } finally {
        conn.release();
    }
};

module.exports = pool;
