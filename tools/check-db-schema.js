require('dotenv').config();
const mysql = require('mysql2/promise');

const REQUIRED_TABLES = [
    'users',
    'password_resets',
    'roles',
    'system_logs',
    'departments',
    'semesters',
    'classes',
    'subjects',
    'class_subjects',
    'class_subject_groups',
    'class_students',
    'student_subject_registration',
    'attendance_sessions',
    'attendance_records',
    'attendance_evidences',
    'assignments',
    'assignment_submissions',
    'grades',
    'category_settings',
    'documents',
    'feedbacks',
    'notification_logs'
];

const REQUIRED_COLUMNS = {
    users: ['id', 'username', 'password', 'full_name', 'email', 'role', 'avatar', 'birth_date', 'phone_number', 'address'],
    class_subject_groups: ['id', 'class_subject_id', 'group_code'],
    student_subject_registration: ['id', 'student_id', 'class_subject_group_id', 'status'],
    attendance_records: ['id', 'session_id', 'student_id', 'status'],
    notification_logs: ['id', 'user_id', 'is_read', 'created_at']
};

async function main() {
    const dbName = process.env.DB_NAME;
    if (!dbName) {
        console.error('Missing DB_NAME in env.');
        process.exit(1);
    }

    const conn = await mysql.createConnection({
        host: process.env.DB_HOST,
        user: process.env.DB_USER,
        password: process.env.DB_PASSWORD || '',
        database: dbName,
        port: Number(process.env.DB_PORT || 3306),
        ssl: process.env.DB_SSL === 'true' ? { rejectUnauthorized: false } : undefined
    });

    try {
        const [tableRows] = await conn.query(
            `SELECT table_name
             FROM information_schema.tables
             WHERE table_schema = ?`,
            [dbName]
        );
        const existingTables = new Set(tableRows.map((r) => r.TABLE_NAME || r.table_name));

        const missingTables = REQUIRED_TABLES.filter((t) => !existingTables.has(t));
        if (missingTables.length > 0) {
            console.error('Missing tables:', missingTables.join(', '));
        } else {
            console.log('Tables OK.');
        }

        let hasColumnErrors = false;
        for (const [tableName, columns] of Object.entries(REQUIRED_COLUMNS)) {
            if (!existingTables.has(tableName)) {
                hasColumnErrors = true;
                console.error(`Skip column check for missing table: ${tableName}`);
                continue;
            }

            const [columnRows] = await conn.query(
                `SELECT column_name
                 FROM information_schema.columns
                 WHERE table_schema = ? AND table_name = ?`,
                [dbName, tableName]
            );
            const existingColumns = new Set(columnRows.map((r) => r.COLUMN_NAME || r.column_name));
            const missingColumns = columns.filter((c) => !existingColumns.has(c));

            if (missingColumns.length > 0) {
                hasColumnErrors = true;
                console.error(`Missing columns in ${tableName}: ${missingColumns.join(', ')}`);
            }
        }

        if (missingTables.length === 0 && !hasColumnErrors) {
            console.log('Schema check passed.');
            process.exit(0);
        }

        process.exit(2);
    } finally {
        await conn.end();
    }
}

main().catch((error) => {
    console.error('Schema check failed:', error.code || error.message);
    process.exit(1);
});
