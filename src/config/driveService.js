const { google } = require('googleapis');
const path = require('path');
const stream = require('stream');

// 1. Đọc cấu hình thư mục từ biến môi trường
const FOLDER_ID = process.env.GOOGLE_DRIVE_FOLDER_ID;

// Hàm thông minh tự động chọn cách đăng nhập tùy môi trường (Local / Deploy)
function resolveGoogleAuthConfig() {
    // ƯU TIÊN 1 (Clever Cloud): Đọc trực tiếp từ chuỗi JSON trong biến môi trường
    const credentialsJson = process.env.GOOGLE_SERVICE_ACCOUNT_JSON;
    if (credentialsJson) {
        return {
            credentials: JSON.parse(credentialsJson),
            scopes: ['https://www.googleapis.com/auth/drive'],
        };
    }

    // ƯU TIÊN 2 (Local): Đọc từ đường dẫn file được cấu hình trong .env
    const keyFileFromEnv = process.env.GOOGLE_APPLICATION_CREDENTIALS || process.env.GOOGLE_DRIVE_CREDENTIALS_FILE;
    if (keyFileFromEnv) {
        return {
            keyFile: path.resolve(keyFileFromEnv),
            scopes: ['https://www.googleapis.com/auth/drive'],
        };
    }

    // ƯU TIÊN 3: Fallback tìm file cứng trong dự án (Đề phòng lỡ quên config)
    return {
        keyFile: path.join(__dirname, '../../drive-credentials.json'),
        scopes: ['https://www.googleapis.com/auth/drive'],
    };
}

// 2. Cấu hình xác thực với Google
const auth = new google.auth.GoogleAuth({
    ...resolveGoogleAuthConfig(),
});

// Khởi tạo dịch vụ Drive
const driveService = google.drive({ version: 'v3', auth });

// ==========================================
// 3. HÀM TẢI FILE LÊN DRIVE
// ==========================================
const uploadFileToDrive = async (fileObject) => {
    try {
        if (!FOLDER_ID) {
            throw new Error('Lỗi: Chưa cấu hình GOOGLE_DRIVE_FOLDER_ID trong biến môi trường.');
        }

        // Biến file từ Multer thành luồng (Stream) để đẩy thẳng lên mạng, tiết kiệm RAM
        const bufferStream = new stream.PassThrough();
        bufferStream.end(fileObject.buffer);

        // Gọi API tạo file mới
        const response = await driveService.files.create({
            media: {
                mimeType: fileObject.mimetype,
                body: bufferStream,
            },
            requestBody: {
                name: fileObject.originalname, 
                parents: [FOLDER_ID], // Lưu vào đúng thư mục CMS BDU
            },
            fields: 'id, webViewLink, webContentLink', // Lấy link xem và tải
        });

        const fileId = response.data.id;

        // Cấp quyền: Bất kỳ ai có link đều xem được (Rất quan trọng để hiển thị trên web)
        await driveService.permissions.create({
            fileId: fileId,
            requestBody: {
                role: 'reader',
                type: 'anyone', 
            },
        });

        console.log('✅ Tải file thành công lên Drive. ID:', fileId);
        return response.data; // Trả về { id, webViewLink, webContentLink } để lưu vào Database

    } catch (error) {
        console.error('❌ Lỗi khi tải file lên Drive:', error.message);
        throw error;
    }
};

// ==========================================
// 4. HÀM XÓA FILE TRÊN DRIVE (BỔ SUNG THÊM)
// ==========================================
const deleteFileFromDrive = async (fileId) => {
    try {
        if (!fileId) return false;
        
        await driveService.files.delete({
            fileId: fileId,
        });
        
        console.log(`✅ Đã xóa thành công file ID [${fileId}] trên Google Drive`);
        return true;
    } catch (error) {
        console.error(`❌ Lỗi khi xóa file ID [${fileId}] trên Drive:`, error.message);
        throw error;
    }
};

// Xuất các hàm để file server.js (hoặc Controller) có thể gọi được
module.exports = {
    uploadFileToDrive,
    deleteFileFromDrive
};