function prepareProofUpload(date, session) {
        document.getElementById('proofDateInfo').innerText = `${date} (${session})`;
    }

    function submitProof() {
        alert("✅ Đã tải file minh chứng lên thành công!\nVui lòng đợi Ban Cán Sự hoặc Giảng viên xét duyệt.");
        bootstrap.Modal.getInstance(document.getElementById('uploadProofModal')).hide();
    }
