const { google } = require('googleapis');
const path = require('path');
const stream = require('stream');

// 1. Khai báo file JSON chìa khóa và ID thư mục
const KEYFILEPATH = path.join(__dirname, '../../drive-credentials.json'); // Đường dẫn trỏ tới file json
const FOLDER_ID = 'ĐIỀN_FOLDER_ID_CỦA_BẠN_VÀO_ĐÂY'; // Paste mã URL bạn copy ở Bước 2

// 2. Cấu hình xác thực với Google
const auth = new google.auth.GoogleAuth({
    keyFile: KEYFILEPATH,
    scopes: ['https://www.googleapis.com/auth/drive'], // Cấp full quyền quản lý Drive
});

// Khởi tạo dịch vụ Drive
const driveService = google.drive({ version: 'v3', auth });

// 3. Hàm xử lý Tải file lên
const uploadFileToDrive = async (fileObject) => {
    try {
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