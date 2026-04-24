let bcsSelectedFeedback = null;
const bcsFeedbackApiUrl = (window.cmsUrl || function (path) { return path; })('/api/bcs/feedbacks.php');

function bcsFeedbackNormalize(value) {
    return String(value || '').trim().toLowerCase();
}

function openFeedbackModal(feedback) {
    if (!feedback || typeof feedback !== 'object') return;
    bcsSelectedFeedback = feedback;

    const sender = `${feedback.full_name || ''} (${feedback.student_code || feedback.username || ''})`;
    document.getElementById('fbSenderName').textContent = sender.trim();
    document.getElementById('fbTime').textContent = feedback.updated_at || '';
    document.getElementById('fbSubject').textContent = feedback.title || 'Phản hồi';
    document.getElementById('fbContent').textContent = feedback.content || '';
    document.getElementById('fbReply').value = feedback.reply_content || '';

    const badge = document.getElementById('fbStatusBadge');
    if (feedback.status === 'Resolved') {
        badge.className = 'badge bg-success';
        badge.textContent = 'Đã giải quyết';
    } else {
        badge.className = 'badge bg-warning text-dark border border-warning';
        badge.textContent = 'Chờ xử lý';
    }
}

window.openFeedbackModal = openFeedbackModal;

window.resolveFeedback = async function () {
    const id = Number(bcsSelectedFeedback?.id || 0);
    if (!id) {
        alert('Không xác định được phản hồi cần xử lý.');
        return;
    }

    const reply = (document.getElementById('fbReply')?.value || '').trim();
    const res = await fetch(bcsFeedbackApiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
            action: 'resolve',
            id,
            reply_content: reply
        })
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data?.ok) {
        alert(data?.error || 'Không thể cập nhật phản hồi.');
        return;
    }

    window.location.reload();
};

window.deleteFeedback = async function () {
    const id = Number(bcsSelectedFeedback?.id || 0);
    if (!id) {
        alert('Không xác định được phản hồi cần xóa.');
        return;
    }

    if (!confirm('Bạn có chắc muốn xóa phản hồi này?')) return;

    const res = await fetch(bcsFeedbackApiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
            action: 'delete',
            id
        })
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data?.ok) {
        alert(data?.error || 'Không thể xóa phản hồi.');
        return;
    }

    window.location.reload();
};

function bcsFilterFeedbackTable() {
    const keyword = bcsFeedbackNormalize(document.getElementById('searchInput')?.value);
    const status = bcsFeedbackNormalize(document.getElementById('filterStatus')?.value || 'all');

    const rows = Array.from(document.querySelectorAll('#feedbackTable tbody tr[data-search]'));
    rows.forEach((row) => {
        const rowText = bcsFeedbackNormalize(row.getAttribute('data-search'));
        const rowStatus = bcsFeedbackNormalize(row.getAttribute('data-status'));
        const matchText = !keyword || rowText.includes(keyword);
        const matchStatus = status === 'all' || rowStatus === status;
        row.style.display = (matchText && matchStatus) ? '' : 'none';
    });
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('searchInput')?.addEventListener('input', bcsFilterFeedbackTable);
    document.getElementById('filterStatus')?.addEventListener('change', bcsFilterFeedbackTable);
});
