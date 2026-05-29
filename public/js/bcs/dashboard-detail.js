let bcsDetailData   = { stats: {}, rows: [] };
let activeDetailFilter = null; // null | 'students' | 'subjects'

function bcsDetailDate(value) {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value).slice(0, 10);
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    return `${dd}/${mm}/${d.getFullYear()}`;
}

// ── Tính warning sets từ toàn bộ dữ liệu ──────────────────────────────────
function getWarningSets() {
    const rows = Array.isArray(bcsDetailData.rows) ? bcsDetailData.rows : [];
    const students = new Set();
    const subjects  = new Set();
    for (const r of rows) {
        if (Number(r.total_absent_in_subject) >= 3) {
            students.add(r.username || r.full_name || '');
            subjects.add(r.subject_name || '');
        }
    }
    return { students, subjects };
}

// ── Cập nhật UI filter (nút xóa, highlight card) ──────────────────────────
function updateFilterUI() {
    const keyword  = (document.getElementById('bcsDashboardDetailKeyword')?.value || '').trim();
    const clearBtn = document.getElementById('clearFilterBtn');
    if (clearBtn) clearBtn.style.display = (activeDetailFilter || keyword) ? '' : 'none';

    document.getElementById('cardWarnStudents')?.classList.toggle('filter-active', activeDetailFilter === 'students');
    document.getElementById('cardWarnSubjects')?.classList.toggle('filter-active', activeDetailFilter === 'subjects');
}

// ── Toggle filter khi nhấp card ────────────────────────────────────────────
function toggleFilter(type) {
    activeDetailFilter = activeDetailFilter === type ? null : type;
    updateFilterUI();
    bcsRenderDetailTable();
}

// ── Xóa tất cả filter ─────────────────────────────────────────────────────
function clearDetailFilter() {
    activeDetailFilter = null;
    const kw = document.getElementById('bcsDashboardDetailKeyword');
    if (kw) kw.value = '';
    updateFilterUI();
    bcsRenderDetailTable();
}

// ── Render stats ──────────────────────────────────────────────────────────
function bcsRenderDetailStats() {
    const cards = document.querySelectorAll('.stat-card-custom h2');
    if (cards.length >= 3) {
        cards[0].textContent = String(bcsDetailData.stats.totalStudents  || 0);
        cards[1].textContent = String(bcsDetailData.stats.warningStudents || 0);
        cards[2].textContent = String(bcsDetailData.stats.warningSubjects || 0);
    }
}

// ── Render bảng (có áp dụng filter) ──────────────────────────────────────
function bcsRenderDetailTable() {
    const tbody = document.getElementById('bcsDashboardDetailBody');
    if (!tbody) return;

    let rows = Array.isArray(bcsDetailData.rows) ? [...bcsDetailData.rows] : [];
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Chưa có dữ liệu vắng học.</td></tr>';
        return;
    }

    // Tính warning sets TRƯỚC khi filter (từ toàn bộ dữ liệu gốc)
    const { students: warnStudents, subjects: warnSubjects } = getWarningSets();

    // Áp dụng keyword filter
    const keyword = (document.getElementById('bcsDashboardDetailKeyword')?.value || '').trim().toLowerCase();
    if (keyword) {
        rows = rows.filter(r =>
            (r.full_name || '').toLowerCase().includes(keyword) ||
            (r.username  || '').toLowerCase().includes(keyword)
        );
    }

    // Áp dụng card filter
    if (activeDetailFilter === 'students') {
        rows = rows.filter(r => warnStudents.has(r.username || r.full_name || ''));
    } else if (activeDetailFilter === 'subjects') {
        rows = rows.filter(r => warnSubjects.has(r.subject_name || ''));
    }

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Không tìm thấy kết quả.</td></tr>';
        return;
    }

    // Nhóm: student → subject → [absences]
    const studentGroups = [];
    const studentIndex  = new Map();

    for (const row of rows) {
        const stuKey = row.username || row.full_name || '';
        if (!studentIndex.has(stuKey)) {
            const g = { full_name: row.full_name || '', username: row.username || '', subjects: [], subjectIndex: new Map() };
            studentIndex.set(stuKey, g);
            studentGroups.push(g);
        }
        const student = studentIndex.get(stuKey);
        const subKey  = row.subject_name || '';
        if (!student.subjectIndex.has(subKey)) {
            const sg = { subject_name: subKey, absences: [] };
            student.subjectIndex.set(subKey, sg);
            student.subjects.push(sg);
        }
        student.subjectIndex.get(subKey).absences.push(row);
    }

    let html = '';
    let stt  = 1;

    for (const student of studentGroups) {
        const totalRows = student.subjects.reduce((sum, sub) => sum + sub.absences.length, 0);
        let isFirstRow = true;

        for (const subGroup of student.subjects) {
            const subRowCount = subGroup.absences.length;
            const totalAbsent = Number(subGroup.absences[0]?.total_absent_in_subject) || subRowCount;

            for (let i = 0; i < subGroup.absences.length; i++) {
                const abs = subGroup.absences[i];

                const statusHtml = Number(abs.status) === 2
                    ? '<span class="text-warning fw-bold"><i class="bi bi-exclamation-circle me-1"></i>Vắng có phép</span>'
                    : '<span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i>Vắng không phép</span>';

                const evidenceHtml = abs.drive_link
                    ? `<a class="btn btn-sm btn-primary rounded-circle shadow-sm" href="${abs.drive_link}" target="_blank" rel="noopener noreferrer"><i class="bi bi-file-earmark-medical"></i></a>`
                    : '<button class="btn btn-sm btn-light rounded-circle" disabled><i class="bi bi-eye-slash text-muted"></i></button>';

                html += '<tr>';

                if (isFirstRow) {
                    html += `<td rowspan="${totalRows}" class="text-center align-middle border-end">${stt}</td>`;
                    html += `<td rowspan="${totalRows}" class="align-middle pe-4 border-end">
                        <div class="fw-bold text-dark" style="font-size:0.95rem;">${student.full_name}</div>
                        <div class="text-muted small">${student.username}</div>
                    </td>`;
                    isFirstRow = false;
                }

                if (i === 0) {
                    html += `<td rowspan="${subRowCount}" class="align-middle py-2 fw-semibold">${subGroup.subject_name}</td>`;
                }

                html += `<td class="text-dark fw-bold">${bcsDetailDate(abs.attendance_date)}</td>`;
                html += `<td class="text-dark">${abs.study_session || ''}</td>`;
                html += `<td>${statusHtml}</td>`;
                html += `<td class="text-center">${evidenceHtml}</td>`;

                if (i === 0) {
                    const cls = totalAbsent >= 3 ? 'text-danger' : 'text-warning';
                    html += `<td rowspan="${subRowCount}" class="text-center align-middle fw-bold ${cls} fs-5 border-start">${totalAbsent}</td>`;
                }

                html += '</tr>';
            }
        }
        stt++;
    }

    tbody.innerHTML = html;
}

// ── Tải dữ liệu từ API (không truyền keyword — filter hoàn toàn client-side) ─
async function loadDashboardData() {
    const res = await fetch('/api/bcs/dashboard-detail', { headers: { Accept: 'application/json' } });
    if (res.status === 401) { window.location.href = '/login.html'; return; }

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const tbody = document.getElementById('bcsDashboardDetailBody');
        if (tbody) tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${data.error || 'Không thể tải dữ liệu.'}</td></tr>`;
        return;
    }

    bcsDetailData = data || { stats: {}, rows: [] };
    bcsRenderDetailStats();
    bcsRenderDetailTable();
    updateFilterUI();
}

// ── Xuất Excel ────────────────────────────────────────────────────────────
function exportDetailExcel() {
    const rows = Array.isArray(bcsDetailData.rows) ? bcsDetailData.rows : [];
    if (!rows.length) { alert('Không có dữ liệu để xuất.'); return; }

    const csvRows = [
        ['MSSV', 'Họ tên', 'Môn học', 'Ngày vắng', 'Buổi', 'Trạng thái', 'Tổng vắng môn'].join(','),
        ...rows.map(r => {
            const status = Number(r.status) === 2 ? 'Vắng có phép' : 'Vắng không phép';
            const esc = v => `"${String(v).replace(/"/g, '""')}"`;
            return [r.username || '', r.full_name || '', r.subject_name || '',
                    bcsDetailDate(r.attendance_date), r.study_session || '',
                    status, r.total_absent_in_subject || 0].map(esc).join(',');
        })
    ];

    const blob = new Blob(['﻿' + csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = `bcs_dashboard_detail_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('bcsDashboardDetailKeyword')?.addEventListener('input', function () {
        updateFilterUI();
        bcsRenderDetailTable();
    });
    loadDashboardData();
});
