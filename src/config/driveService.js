const { google } = require('googleapis');
const path = require('path');
const stream = require('stream');

// 1. Đọc cấu hình bảo mật từ biến môi trường
const LEGACY_KEYFILEPATH = path.join(__dirname, '../../drive-credentials.json');
const FOLDER_ID = process.env.GOOGLE_DRIVE_FOLDER_ID;

function resolveGoogleAuthConfig() {
    const credentialsJson = process.env.GOOGLE_SERVICE_ACCOUNT_JSON;
    if (credentialsJson) {
        return {
            credentials: JSON.parse(credentialsJson),
            scopes: ['https://www.googleapis.com/auth/drive'],
        };
    }

    const keyFileFromEnv = process.env.GOOGLE_APPLICATION_CREDENTIALS || process.env.GOOGLE_DRIVE_CREDENTIALS_FILE;
    if (keyFileFromEnv) {
        return {
            keyFile: path.resolve(keyFileFromEnv),
            scopes: ['https://www.googleapis.com/auth/drive'],
        };
    }

    // Fallback để tương thích cũ trong local, không nên dùng khi deploy.
    return {
        keyFile: LEGACY_KEYFILEPATH,
        scopes: ['https://www.googleapis.com/auth/drive'],
    };
}

// 2. Cấu hình xác thực với Google
const auth = new google.auth.GoogleAuth({
    ...resolveGoogleAuthConfig(),
});

// Khởi tạo dịch vụ Drive
const driveService = google.drive({ version: 'v3', auth });

// 3. Hàm xử lý Tải file lên
const uploadFileToDrive = async (fileObject) => {
    try {
        if (!FOLDER_ID) {
            throw new Error('Missing GOOGLE_DRIVE_FOLDER_ID in environment variables.');
        }

        // Biến file từ Multer thành luồng (Stream) để đẩy lên mạng
        const bufferStream = new stream.PassThrough();
        bufferStream.end(fileObject.buffer);

        // Gọi API tải lên
        const response = await driveService.files.create({
            media: {
                mimeType: fileObject.mimetype,
                body: bufferStream,
            },
            requestBody: {
                name: fileObject.originalname, // Tên file gốc
                parents: [FOLDER_ID], // Lưu vào đúng thư mục CMS_BDU_Documents
            },
            fields: 'id, webViewLink, webContentLink', // Nhờ Google trả về link để xem và tải
        });

        const fileId = response.data.id;

        // 4. (Quan trọng) Chỉnh sửa quyền để Sinh viên có link là xem được
        await driveService.permissions.create({
            fileId: fileId,
            requestBody: {
                role: 'reader',
                type: 'anyone', // Ai có link cũng xem được
            },
        });

        console.log('Tải file thành công:', response.data.webViewLink);
        return response.data; // Trả về thông tin để lưu vào Database

    } catch (error) {
        console.error('Lỗi khi tải file lên Drive:', error.message);
        throw error;
    }
};

module.exports = {
    uploadFileToDrive
};