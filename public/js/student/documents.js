// Logic tải file/xem trước
    function handleFileDownload(event, fileName, isDirectDownload) {
        event.preventDefault(); 
        const ext = fileName.split('.').pop().toLowerCase();
        const downloadExts = ['zip', 'rar', 'exe', 'tar'];
        
        if (isDirectDownload || downloadExts.includes(ext)) {
            alert(`⬇️ Đang tải tệp [${fileName}] xuống máy tính của bạn...`); 
        } else {
            alert(`👀 Đang mở bản xem trước của tệp [${fileName}] trên Google Drive Viewer...`);
        }
    }
