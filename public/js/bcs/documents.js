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

function bcsBuildQueryForSemesterYear() {
    const year = document.getElementById('filterYear')?.value || 'all';
    const semester = document.getElementById('filterSemester')?.value || 'all';
    const url = new URL(window.location.href);
    url.searchParams.set('year', year);
    url.searchParams.set('semester', semester);
    return url.toString();
}

function bcsOnYearSemesterChange() {
    window.location.href = bcsBuildQueryForSemesterYear();
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
        modalTitle.innerHTML = '<i class="bi bi-cloud-arrow-up-fill me-2"></i>Tai Tai Lieu Len';
        submitBtn.innerText = 'XAC NHAN';
        fileContainer.classList.remove('d-none');
        form.reset();
        if (!bcsSetSelectValue(categorySelect, 'Thong bao')) {
            categorySelect.selectedIndex = 0;
        }
        customCategoryDiv?.classList.add('d-none');
        if (customCategoryInput) customCategoryInput.value = '';
        return;
    }

    modalTitle.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Chinh Sua Tai Lieu';
    submitBtn.innerText = 'LUU THAY DOI';
    fileContainer.classList.add('d-none');

    const doc = (docData && typeof docData === 'object') ? docData : null;
    if (!doc) return;

    document.getElementById('docTitle').value = doc.title || '';
    document.getElementById('docNote').value = doc.note || '';

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

function saveDocument() {
    alert('Chuc nang luu/chinh sua tai lieu can backend API.');
}

function deleteDocument() {
    alert('Chuc nang xoa tai lieu can backend API.');
}

function handleFileView(event, link) {
    event.preventDefault();
    if (!link) return;
    window.open(link, '_blank', 'noopener');
}

function downloadDocument(link) {
    if (!link) return;
    window.open(link, '_blank', 'noopener');
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('filterYear')?.addEventListener('change', bcsOnYearSemesterChange);
    document.getElementById('filterSemester')?.addEventListener('change', bcsOnYearSemesterChange);
    document.getElementById('filterCategory')?.addEventListener('change', bcsApplyDocumentFilters);
    document.getElementById('searchInput')?.addEventListener('input', bcsApplyDocumentFilters);
    bcsApplyDocumentFilters();
});
