require('dotenv').config();
const express = require('express');
const mysql = require('mysql2/promise');
const path = require('path');
const cors = require('cors');

const app = express();
app.use(cors());
app.use(express.json());

// Cấu hình thư mục chứa file tĩnh (CSS, JS, hình ảnh)
app.use('/public', express.static(path.join(__dirname, 'public')));

// Tạm thời phục vụ các file HTML trực tiếp từ thư mục views
app.use('/', express.static(path.join(__dirname, 'views')));

// Cấu hình kết nối MySQL với Clever Cloud
const dbConfig = {
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME,
    port: process.env.DB_PORT,
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
};

// Test API kết nối
app.get('/api/test-db', async (req, res) => {
    try {
        const pool = mysql.createPool(dbConfig);
        const [rows] = await pool.query('SELECT 1 + 1 AS solution');
        res.json({ success: true, message: 'Database connected successfully!', data: rows });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`Server is running on port ${PORT}`);
});