function bcsNormalizeText(value) {
    return String(value || '').trim().toLowerCase();
}

function bcsApplyDocumentFilters() {
    const keyword = bcsNormalizeText(document.getElementById('searchInput')?.value);
    const category = bcsNormalizeText(document.getElementById('filterCategory')?.value);
    const rows = Array.from(document.querySelectorAll('#bcsDocumentsTableBody tr[data-title]'));

    rows.forEach((row) => {
        const title = row.getAttribute('data-title') || '';
        const note = row.getAttribute('data-note') || '';
        const rowCategory = row.getAttribute('data-category') || '';

        const matchKeyword = !keyword || title.includes(keyword) || note.includes(keyword);
        const matchCategory = !category || rowCategory.includes(category);
        row.style.display = (matchKeyword && matchCategory) ? '' : 'none';
    });
}

function bcsBuildQueryForSemester() {
    const semesterId = document.getElementById('filterSemester')?.value || '';
    const url = new URL(window.location.href);
    if (semesterId) {
        url.searchParams.set('semester_id', semesterId);
    }
    return url.toString();
}

function bcsOnSemesterChange() {
    window.location.href = bcsBuildQueryForSemester();
}

function bcsIsCustomCategory(value) {
    return bcsNormalizeText(value).startsWith('kh');
}

function toggleCustomCategory() {
    const categorySelect = document.getElementById('docCategory');
    const customDiv = document.getElementById('customCategoryDiv');
    if (!categorySelect || !customDiv) return;

    if (bcsIsCustomCategory(categorySelect.value)) {
        customDiv.classList.remove('d-none');
        document.getElementById('customCategoryInput')?.focus();
    } else {
        customDiv.classList.add('d-none');
    }
}

function bcsSetSelectValue(selectEl, value) {
    if (!selectEl) return false;
    const target = String(value || '');
    const option = Array.from(selectEl.options).find((opt) => String(opt.value) === target);
    if (option) {
        selectEl.value = target;
        return true;
    }
    return false;
}

function bcsSyncModalAcademicYear() {
    const modalSemester = document.getElementById('docSemesterId');
    const yearInput = document.getElementById('docAcademicYear');
    if (!modalSemester || !yearInput) return;
    const y = modalSemester.selectedOptions?.[0]?.getAttribute('data-year') || '';
    yearInput.value = y;
}

function openDocModal(mode, docData = null) {
    const modalTitle = document.getElementById('docModalTitle');
    const submitBtn = document.getElementById('docModalSubmitBtn');
    const fileContainer = document.getElementById('fileUploadContainer');
    const categorySelect = document.getElementById('docCategory');
    const customCategoryDiv = document.getElementById('customCategoryDiv');
    const customCategoryInput = document.getElementById('customCategoryInput');
    const form = document.getElementById('uploadDocForm');

    if (!modalTitle || !submitBtn || !fileContainer || !categorySelect || !form) return;

    if (mode === 'add') {
        modalTitle.innerHTML = '<i class="bi bi-cloud-arrow-up-fill me-2"></i>Tải Tài Liệu Lên';
        submitBtn.innerText = 'XÁC NHẬN';
        fileContainer.classList.remove('d-none');
        form.reset();

        if (!bcsSetSelectValue(categorySelect, 'Thông báo')) {
            categorySelect.selectedIndex = 0;
        }

        const idInput = document.getElementById('docId');
        if (idInput) idInput.value = '';

        const driveInput = document.getElementById('docDriveLink');
        if (driveInput) driveInput.value = '';

        const semesterFilter = document.getElementById('filterSemester');
        const modalSemester = document.getElementById('docSemesterId');
        if (semesterFilter && modalSemester) {
            modalSemester.value = semesterFilter.value;
        }
        bcsSyncModalAcademicYear();

        customCategoryDiv?.classList.add('d-none');
        if (customCategoryInput) customCategoryInput.value = '';
        return;
    }

    modalTitle.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Chỉnh Sửa Tài Liệu';
    submitBtn.innerText = 'LƯU THAY ĐỔI';
    fileContainer.classList.add('d-none');

    const doc = (docData && typeof docData === 'object') ? docData : null;
    if (!doc) return;

    const idInput = document.getElementById('docId');
    if (idInput) idInput.value = String(doc.id || '');

    document.getElementById('docTitle').value = doc.title || '';
    document.getElementById('docNote').value = doc.note || '';

    const driveInput = document.getElementById('docDriveLink');
    if (driveInput) driveInput.value = doc.drive_link || '';

    const modalSemester = document.getElementById('docSemesterId');
    if (modalSemester) {
        const matched = Array.from(modalSemester.options).find((opt) => {
            const text = String(opt.textContent || '');
            return text.includes(String(doc.semester_name || '')) && text.includes(String(doc.academic_year || ''));
        });
        if (matched) modalSemester.value = matched.value;
    }
    bcsSyncModalAcademicYear();

    const hasDefault = bcsSetSelectValue(categorySelect, doc.category || '');
    if (hasDefault && !bcsIsCustomCategory(categorySelect.value)) {
        customCategoryDiv?.classList.add('d-none');
        if (customCategoryInput) customCategoryInput.value = '';
    } else {
        const customOption = Array.from(categorySelect.options).find((opt) => bcsIsCustomCategory(opt.value));
        if (customOption) categorySelect.value = customOption.value;
        customCategoryDiv?.classList.remove('d-none');
        if (customCategoryInput) customCategoryInput.value = doc.category || '';
    }
}

async function uploadDocumentFileToDrive(file) {
    if (!file) return { link: '', fileId: '' };

    const formData = new FormData();
    formData.append('file', file);

    const res = await fetch('/cms/api/upload-to-drive', {
        method: 'POST',
        body: formData
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data?.success) {
        throw new Error(data?.error || 'Không thể tải file lên Drive');
    }

    return {
        link: data.link || '',
        fileId: data.fileId || ''
    };
}

async function saveDocument() {
    const docId = Number(document.getElementById('docId')?.value || 0);
    const title = (document.getElementById('docTitle')?.value || '').trim();
    const note = (document.getElementById('docNote')?.value || '').trim();
    const categorySelect = document.getElementById('docCategory');
    const customCategory = (document.getElementById('customCategoryInput')?.value || '').trim();
    const fileInput = document.getElementById('docFile');
    const selectedFile = fileInput?.files?.[0] || null;
    const driveInput = document.getElementById('docDriveLink');
    const manualDriveLink = (driveInput?.value || '').trim();
    const modalSemester = document.getElementById('docSemesterId');

    if (!title) {
        alert('Vui lòng nhập tiêu đề tài liệu.');
        return;
    }

    let category = categorySelect ? String(categorySelect.value || '').trim() : '';
    if (bcsIsCustomCategory(category)) {
        category = customCategory || 'Khác';
    }
    if (!category) {
        alert('Vui lòng chọn danh mục.');
        return;
    }

    const semesterId = Number(modalSemester?.value || 0);
    if (!semesterId) {
        alert('Vui lòng chọn học kỳ lưu.');
        return;
    }

    if (!selectedFile && !manualDriveLink && docId <= 0) {
        alert('Vui lòng chọn file tải lên hoặc nhập link tài liệu.');
        return;
    }

    let driveLink = manualDriveLink;
    let driveFileId = '';
    if (selectedFile) {
        try {
            const uploaded = await uploadDocumentFileToDrive(selectedFile);
            driveLink = uploaded.link || driveLink;
            driveFileId = uploaded.fileId || '';
        } catch (e) {
            if (!driveLink) {
                alert((e.message || 'Không thể tải file lên Drive.') + ' Vui lòng nhập Link Google Drive thủ công.');
                return;
            }
        }
    }

    const payload = {
        action: docId > 0 ? 'update' : 'create',
        id: docId > 0 ? docId : undefined,
        title,
        note,
        category,
        drive_link: driveLink,
        drive_file_id: driveFileId,
        semester_id: semesterId
    };

    try {
        const res = await fetch('/cms/api/bcs/documents.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data?.ok) {
            alert(data?.error || 'Không thể lưu tài liệu.');
            return;
        }

        window.location.href = bcsBuildQueryForSemester();
    } catch (_) {
        alert('Không thể kết nối máy chủ.');
    }
}

function deleteDocument(id) {
    const docId = Number(id || 0);
    if (!docId) return;

    if (!confirm('Bạn có chắc muốn xóa tài liệu này?')) return;

    fetch('/cms/api/bcs/documents.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json'
        },
        body: JSON.stringify({ action: 'delete', id: docId })
    })
        .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
        .then(({ ok, data }) => {
            if (!ok || !data?.ok) {
                alert(data?.error || 'Không thể xóa tài liệu.');
                return;
            }
            window.location.href = bcsBuildQueryForSemester();
        })
        .catch(() => {
            alert('Không thể kết nối máy chủ.');
        });
}

function handleFileView(event, link) {
    event.preventDefault();
    if (!link) {
        alert('Tài liệu này chưa có đường dẫn để mở.');
        return;
    }
    window.open(link, '_blank', 'noopener');
}

function downloadDocument(link) {
    if (!link) {
        alert('Tài liệu này chưa có đường dẫn tải.');
        return;
    }
    window.open(link, '_blank', 'noopener');
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('filterSemester')?.addEventListener('change', bcsOnSemesterChange);
    document.getElementById('docSemesterId')?.addEventListener('change', bcsSyncModalAcademicYear);
    document.getElementById('filterCategory')?.addEventListener('change', bcsApplyDocumentFilters);
    document.getElementById('searchInput')?.addEventListener('input', bcsApplyDocumentFilters);
    bcsApplyDocumentFilters();
    bcsSyncModalAcademicYear();
});
