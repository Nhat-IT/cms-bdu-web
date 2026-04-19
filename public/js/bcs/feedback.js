<<<<<<< HEAD
    function resolveFeedback() {
        alert("✅ Đã lưu phản hồi và chuyển trạng thái thành 'Đã giải quyết'. Sinh viên sẽ nhận được thông báo!");
    }
    function confirmDelete() {
        if(confirm("⚠️ Bạn có chắc chắn muốn xóa phản hồi này?")) {
            alert("🗑️ Đã xóa thành công!");
        }
    }
=======
let bcsFeedbackList = [];
let bcsSelectedFeedbackId = 0;

function bcsFeedbackTime(value) {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value).slice(0, 16);
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    const hh = String(d.getHours()).padStart(2, '0');
    const min = String(d.getMinutes()).padStart(2, '0');
    return `${dd}/${mm}/${yyyy} ${hh}:${min}`;
}

function bcsRenderFeedbackStats() {
    const total = bcsFeedbackList.length;
    const pending = bcsFeedbackList.filter((x) => x.status === 'Pending').length;
    const resolved = bcsFeedbackList.filter((x) => x.status === 'Resolved').length;

    const cards = document.querySelectorAll('.stat-card-custom h3');
    if (cards.length >= 3) {
        cards[0].textContent = String(total);
        cards[1].textContent = String(pending);
        cards[2].textContent = String(resolved);
    }
}

function openFeedbackDetail(id) {
    bcsSelectedFeedbackId = Number(id || 0);
    const fb = bcsFeedbackList.find((item) => Number(item.id) === bcsSelectedFeedbackId);
    if (!fb) return;

    document.getElementById('fbSenderName').textContent = `${fb.full_name || ''} (${fb.username || ''})`;
    document.getElementById('fbTime').textContent = bcsFeedbackTime(fb.updated_at);
    document.getElementById('fbSubject').textContent = fb.title || '';
    document.getElementById('fbContent').textContent = fb.content || '';
    document.getElementById('fbReply').value = fb.reply_content || '';

    const badge = document.getElementById('fbStatusBadge');
    if (badge) {
        if (fb.status === 'Pending') {
            badge.className = 'badge bg-warning text-dark border border-warning';
            badge.textContent = 'Cho xu ly';
        } else {
            badge.className = 'badge bg-success';
            badge.textContent = 'Da giai quyet';
        }
    }

    const modal = new bootstrap.Modal(document.getElementById('feedbackModal'));
    modal.show();
}

function bcsRenderFeedbackTable() {
    const tbody = document.getElementById('bcsFeedbackTableBody');
    if (!tbody) return;

    if (!bcsFeedbackList.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Chua co phan hoi tu sinh vien.</td></tr>';
        return;
    }

    tbody.innerHTML = bcsFeedbackList.map((fb) => {
        const isPending = fb.status === 'Pending';
        const statusBadge = isPending
            ? '<span class="badge bg-warning text-dark px-3 py-2 rounded-pill"><i class="bi bi-hourglass me-1"></i>Cho xu ly</span>'
            : '<span class="badge bg-success px-3 py-2 rounded-pill"><i class="bi bi-check-circle me-1"></i>Da giai quyet</span>';

        return `
            <tr ${isPending ? 'class="bg-light"' : ''}>
                <td class="ps-4 py-3">
                    <div class="fw-bold text-dark">${fb.full_name || ''}</div>
                    <div class="text-muted small">MSSV: ${fb.username || ''}</div>
                </td>
                <td>
                    <div class="fw-bold text-dark mb-1">${fb.title || ''}</div>
                    <div class="text-muted small text-truncate" style="max-width: 300px;">${fb.content || ''}</div>
                </td>
                <td class="text-dark small">${bcsFeedbackTime(fb.updated_at)}</td>
                <td>${statusBadge}</td>
                <td class="pe-4 text-end">
                    <button class="btn btn-sm ${isPending ? 'btn-primary' : 'btn-outline-secondary'} fw-bold" onclick="openFeedbackDetail(${fb.id})"><i class="bi bi-eye me-1"></i>Xem</button>
                </td>
            </tr>`;
    }).join('');
}

async function loadBcsFeedbacks() {
    const keyword = (document.getElementById('bcsFeedbackKeyword')?.value || '').trim();
    const status = document.getElementById('bcsFeedbackStatusFilter')?.value || 'all';

    const query = new URLSearchParams({ keyword, status }).toString();
    const res = await fetch(`/api/bcs/feedbacks?${query}`, { headers: { Accept: 'application/json' } });

    if (res.status === 401) {
        window.location.href = '/login.html';
        return;
    }

    const data = await res.json().catch(() => []);
    if (!res.ok) {
        alert(data.error || 'Khong the tai phan hoi.');
        return;
    }

    bcsFeedbackList = Array.isArray(data) ? data : [];
    bcsRenderFeedbackStats();
    bcsRenderFeedbackTable();
}

async function resolveFeedback() {
    if (!bcsSelectedFeedbackId) {
        alert('Khong xac dinh duoc phan hoi.');
        return;
    }

    const replyContent = (document.getElementById('fbReply')?.value || '').trim();
    const res = await fetch(`/api/bcs/feedbacks/${bcsSelectedFeedbackId}/resolve`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ status: 'Resolved', replyContent })
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        alert(data.error || 'Khong the cap nhat phan hoi.');
        return;
    }

    alert('Da cap nhat phan hoi thanh cong.');
    await loadBcsFeedbacks();
}

async function confirmDelete() {
    if (!bcsSelectedFeedbackId) {
        alert('Khong xac dinh duoc phan hoi.');
        return;
    }

    if (!confirm('Ban co chac muon xoa phan hoi nay?')) return;

    const res = await fetch(`/api/bcs/feedbacks/${bcsSelectedFeedbackId}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' }
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        alert(data.error || 'Khong the xoa phan hoi.');
        return;
    }

    alert('Da xoa phan hoi thanh cong.');
    await loadBcsFeedbacks();
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('bcsFeedbackKeyword')?.addEventListener('input', loadBcsFeedbacks);
    document.getElementById('bcsFeedbackStatusFilter')?.addEventListener('change', loadBcsFeedbacks);
    loadBcsFeedbacks();
});
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
