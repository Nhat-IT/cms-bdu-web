let bcsDocuments = [];
let bcsDocumentMode = 'add';
let bcsEditingDocumentId = 0;

function bcsFormatDate(value) {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value).slice(0, 10);
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}/${mm}/${yyyy}`;
}

function bcsGetFileIconClass(title = '') {
    const lower = String(title).toLowerCase();
    if (lower.endsWith('.pdf')) return 'bi-file-earmark-pdf-fill text-danger';
    if (lower.endsWith('.doc') || lower.endsWith('.docx')) return 'bi-file-earmark-word-fill text-primary';
    if (lower.endsWith('.xls') || lower.endsWith('.xlsx') || lower.endsWith('.csv')) return 'bi-file-earmark-excel-fill text-success';
    if (lower.endsWith('.zip') || lower.endsWith('.rar')) return 'bi-file-earmark-zip-fill text-warning';
    return 'bi-file-earmark-text-fill text-secondary';
}

function bcsRenderDocumentStats() {
    const total = bcsDocuments.length;
    const byCategory = bcsDocuments.reduce((acc, item) => {
        const key = item.category || 'Khac';
        acc[key] = (acc[key] || 0) + 1;
        return acc;
    }, {});

    const statCards = document.querySelectorAll('.stat-card-custom h4');
    if (statCards.length >= 4) {
        statCards[0].textContent = String(byCategory['Thông báo'] || 0);
        statCards[1].textContent = String(byCategory['Biên bản'] || 0);
        statCards[2].textContent = String(byCategory['Học liệu'] || 0);
        statCards[3].textContent = String(total);
    }
}

function bcsRenderDocumentsTable() {
    const tbody = document.getElementById('bcsDocumentsTableBody');
    if (!tbody) return;

    if (!bcsDocuments.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Chua co tai lieu trong lop.</td></tr>';
        return;
    }

    tbody.innerHTML = bcsDocuments.map((doc) => {
        const category = doc.category || 'Khac';
        const badgeClass = category === 'Thông báo'
            ? 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25'
            : (category === 'Biên bản'
                ? 'bg-warning bg-opacity-10 text-dark border border-warning border-opacity-50'
                : 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25');

        const title = doc.title || '';
        const note = doc.note || '';
        const link = doc.drive_link || '#';

        return `
            <tr>
                <td class="ps-4 py-3">
                    <a href="${link}" class="file-link d-flex align-items-center" target="_blank" rel="noopener noreferrer">
                        <i class="bi ${bcsGetFileIconClass(title)} fs-3 me-3"></i>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark file-title">${title}</h6>
                            <small class="text-muted">${note || 'Khong co ghi chu'}</small>
                        </div>
                    </a>
                </td>
                <td><span class="badge ${badgeClass} px-2 py-1">${category}</span></td>
                <td>${doc.uploader_name || 'BCS'}</td>
                <td>${bcsFormatDate(doc.created_at)}</td>
                <td class="text-center text-muted small">-</td>
                <td class="pe-4 text-end">
                    <button class="btn btn-sm btn-light text-success border me-1" title="Sua" data-bs-toggle="modal" data-bs-target="#uploadModal" onclick="openDocModal('edit', ${doc.id})"><i class="bi bi-pencil-square"></i></button>
                    <button class="btn btn-sm btn-light text-danger border" title="Xoa" onclick="deleteDocument(${doc.id})"><i class="bi bi-trash"></i></button>
                </td>
            </tr>`;
    }).join('');
}

async function loadBcsDocuments() {
    const keyword = (document.getElementById('bcsDocumentKeyword')?.value || '').trim();
    const category = document.getElementById('bcsDocumentCategoryFilter')?.value || 'all';

    const query = new URLSearchParams({ keyword, category }).toString();
    const res = await fetch(`/api/bcs/documents?${query}`, { headers: { Accept: 'application/json' } });

    if (res.status === 401) {
        window.location.href = '/login.html';
        return;
    }

    const data = await res.json().catch(() => []);
    if (!res.ok) {
        alert(data.error || 'Khong the tai danh sach tai lieu.');
        return;
    }

    bcsDocuments = Array.isArray(data) ? data : [];
    bcsRenderDocumentStats();
    bcsRenderDocumentsTable();
}

function toggleCustomCategory() {
    const categorySelect = document.getElementById('docCategory');
    const customDiv = document.getElementById('customCategoryDiv');
    if (!categorySelect || !customDiv) return;

    if (categorySelect.value === 'Khác') {
        customDiv.classList.remove('d-none');
        document.getElementById('customCategoryInput')?.focus();
    } else {
        customDiv.classList.add('d-none');
    }
}

function openDocModal(mode, docId = 0) {
    bcsDocumentMode = mode;
    bcsEditingDocumentId = Number(docId || 0);

    const modalTitle = document.getElementById('docModalTitle');
    const submitBtn = document.getElementById('docModalSubmitBtn');
    const fileContainer = document.getElementById('fileUploadContainer');
    const categorySelect = document.getElementById('docCategory');
    const customCategoryDiv = document.getElementById('customCategoryDiv');
    const customCategoryInput = document.getElementById('customCategoryInput');

    if (mode === 'add') {
        modalTitle.innerHTML = '<i class="bi bi-cloud-arrow-up-fill me-2"></i>Tai Tai Lieu Len';
        submitBtn.innerText = 'XAC NHAN';
        fileContainer?.classList.remove('d-none');
        document.getElementById('uploadDocForm')?.reset();
        if (categorySelect) categorySelect.value = 'Thông báo';
        customCategoryDiv?.classList.add('d-none');
        if (customCategoryInput) customCategoryInput.value = '';
        return;
    }

    modalTitle.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Chinh Sua Tai Lieu';
    submitBtn.innerText = 'LUU THAY DOI';
    fileContainer?.classList.add('d-none');

    const doc = bcsDocuments.find((item) => Number(item.id) === bcsEditingDocumentId);
    if (!doc) return;

    document.getElementById('docTitle').value = doc.title || '';
    document.getElementById('docNote').value = doc.note || '';

    const defaultCategories = ['Thông báo', 'Biên bản', 'Học liệu'];
    if (defaultCategories.includes(doc.category)) {
        categorySelect.value = doc.category;
        customCategoryDiv?.classList.add('d-none');
        customCategoryInput.value = '';
    } else {
        categorySelect.value = 'Khác';
        customCategoryDiv?.classList.remove('d-none');
        customCategoryInput.value = doc.category || '';
    }
}

async function saveDocument() {
    const title = (document.getElementById('docTitle')?.value || '').trim();
    if (!title) {
        alert('Vui long nhap tieu de tai lieu.');
        return;
    }

    const categorySelect = document.getElementById('docCategory');
    const category = categorySelect?.value === 'Khác'
        ? ((document.getElementById('customCategoryInput')?.value || '').trim() || 'Khác')
        : (categorySelect?.value || 'Học liệu');

    const payload = {
        title,
        note: (document.getElementById('docNote')?.value || '').trim(),
        category,
        driveLink: (document.getElementById('docFile')?.files?.[0]?.name || '').trim()
    };

    const url = bcsDocumentMode === 'edit'
        ? `/api/bcs/documents/${bcsEditingDocumentId}`
        : '/api/bcs/documents';

    const method = bcsDocumentMode === 'edit' ? 'PUT' : 'POST';

    const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(payload)
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        alert(data.error || 'Khong the luu tai lieu.');
        return;
    }

    await loadBcsDocuments();
}

async function deleteDocument(id) {
    if (!confirm('Ban co chac muon xoa tai lieu nay?')) return;

    const res = await fetch(`/api/bcs/documents/${id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' }
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        alert(data.error || 'Khong the xoa tai lieu.');
        return;
    }

    await loadBcsDocuments();
}

function handleFileView(event) {
    event.preventDefault();
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('bcsDocumentKeyword')?.addEventListener('input', loadBcsDocuments);
    document.getElementById('bcsDocumentCategoryFilter')?.addEventListener('change', loadBcsDocuments);
    loadBcsDocuments();
});
