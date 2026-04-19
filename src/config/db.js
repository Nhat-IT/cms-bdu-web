const mysql = require('mysql2/promise');

<<<<<<< HEAD
const pool = mysql.createPool({
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME,
    port: process.env.DB_PORT || 3306,
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
});

module.exports = pool;
=======
function ensureRequiredEnv() {
    const required = ['DB_HOST', 'DB_USER', 'DB_NAME'];
    const missing = required.filter((key) => !process.env[key]);

    if (missing.length > 0) {
        throw new Error(`Missing DB env: ${missing.join(', ')}`);
    }
}

ensureRequiredEnv();
const isProduction = process.env.NODE_ENV === 'production';
const defaultConnectionLimit = isProduction ? 8 : 5;

const pool = mysql.createPool({
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME,
    port: Number(process.env.DB_PORT || 3306),
    waitForConnections: true,
    connectionLimit: Number(process.env.DB_CONNECTION_LIMIT || defaultConnectionLimit),
    maxIdle: Number(process.env.DB_MAX_IDLE || defaultConnectionLimit),
    idleTimeout: Number(process.env.DB_IDLE_TIMEOUT || 60000),
    queueLimit: 0,
    connectTimeout: Number(process.env.DB_CONNECT_TIMEOUT || 10000),
    enableKeepAlive: true,
    keepAliveInitialDelay: 0,
    charset: 'utf8mb4',
    timezone: process.env.DB_TIMEZONE || 'Z',
    ssl: process.env.DB_SSL === 'true' ? { rejectUnauthorized: false } : undefined
});

pool.testConnection = async (options = {}) => {
    const retries = Number(options.retries ?? 3);
    const delayMs = Number(options.delayMs ?? 1500);

    let lastError = null;
    for (let attempt = 1; attempt <= retries; attempt += 1) {
        try {
            const conn = await pool.getConnection();
            try {
                await conn.ping();
                return true;
            } finally {
                conn.release();
            }
        } catch (error) {
            lastError = error;
            if (attempt < retries) {
                await new Promise((resolve) => setTimeout(resolve, delayMs));
            }
        }
    }

    throw lastError;
};

module.exports = pool;
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
