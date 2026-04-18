let documentsData = [];

function formatDateDMY(dateValue) {
    const d = new Date(dateValue);
    if (Number.isNaN(d.getTime())) return '--/--/----';
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}/${mm}/${yyyy}`;
}

function escapeHtml(value) {
    return String(value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function iconByTitle(title = '') {
    const lower = title.toLowerCase();
    if (lower.endsWith('.pdf')) return 'bi-file-earmark-pdf-fill text-danger';
    if (lower.endsWith('.doc') || lower.endsWith('.docx')) return 'bi-file-earmark-word-fill text-primary';
    if (lower.endsWith('.zip') || lower.endsWith('.rar')) return 'bi-file-earmark-zip-fill text-warning';
    return 'bi-file-earmark-text-fill text-secondary';
}

async function fetchDocuments() {
    const response = await fetch('/api/student/documents');
    if (response.status === 401) {
        window.location.href = '/login.html';
        return;
    }
    if (!response.ok) {
        throw new Error('Không thể tải tài liệu');
    }
    documentsData = await response.json();
}

function renderDocuments() {
    const tableBody = document.querySelector('table tbody');
    if (!tableBody) return;

    const header = document.querySelector('.card-header');
    const selects = header ? header.querySelectorAll('select') : [];
    const categorySelect = selects.length > 1 ? selects[1] : null;
    const searchInput = header ? header.querySelector('input[type="text"]') : null;

    const selectedCategory = (categorySelect?.value || 'Tất cả danh mục').trim();
    const keyword = (searchInput?.value || '').trim().toLowerCase();

    const filtered = documentsData.filter((doc) => {
        const byCategory = selectedCategory === 'Tất cả danh mục' || (doc.category || '').toLowerCase() === selectedCategory.toLowerCase();
        const text = `${doc.title || ''} ${doc.subject_name || ''} ${doc.note || ''}`.toLowerCase();
        const byKeyword = !keyword || text.includes(keyword);
        return byCategory && byKeyword;
    });

    if (!filtered.length) {
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Chưa có tài liệu phù hợp.</td></tr>';
        return;
    }

    tableBody.innerHTML = filtered.map((doc) => {
        const title = escapeHtml(doc.title || 'Không có tiêu đề');
        const subject = escapeHtml(doc.subject_name || 'Không rõ môn học');
        const note = escapeHtml(doc.note || `Môn học: ${subject}`);
        const category = escapeHtml(doc.category || 'Khác');
        const uploader = escapeHtml(doc.uploader_name || 'Hệ thống');
        const iconClass = iconByTitle(doc.title || '');
        const link = doc.drive_link || '#';

        return `
            <tr>
                <td class="ps-4 py-3">
                    <a href="${link}" class="file-link d-flex align-items-center" target="_blank" rel="noopener noreferrer">
                        <i class="bi ${iconClass} fs-3 me-3"></i>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark file-title">${title}</h6>
                            <small class="text-muted">${note}</small>
                        </div>
                    </a>
                </td>
                <td><span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">${category}</span></td>
                <td><span class="badge bg-light text-dark border"><i class="bi bi-person-badge me-1"></i>${uploader}</span></td>
                <td class="text-center text-dark">${formatDateDMY(doc.created_at)}</td>
                <td class="text-center text-muted small">--</td>
                <td class="pe-4 text-end">
                    <a class="btn btn-sm btn-outline-primary fw-bold px-3 rounded-pill shadow-sm" href="${link}" target="_blank" rel="noopener noreferrer">
                        <i class="bi bi-download me-1"></i> Tải về
                    </a>
                </td>
            </tr>
        `;
    }).join('');
}

async function initDocuments() {
    try {
        await fetchDocuments();
        renderDocuments();

        const header = document.querySelector('.card-header');
        const selects = header ? header.querySelectorAll('select') : [];
        const categorySelect = selects.length > 1 ? selects[1] : null;
        const searchInput = header ? header.querySelector('input[type="text"]') : null;

        categorySelect?.addEventListener('change', renderDocuments);
        searchInput?.addEventListener('input', renderDocuments);
    } catch (error) {
        console.error(error);
        alert('Không thể tải danh sách tài liệu từ database.');
    }
}

document.addEventListener('DOMContentLoaded', initDocuments);
