<<<<<<< HEAD
        // XỬ LÝ SỰ KIỆN CLICK VÀO FILE
        function handleFileClick(event, fileName, fileUrl) {
            event.preventDefault(); // Ngăn chặn chuyển trang mặc định của thẻ <a>
            
            // Lấy đuôi file (extension)
            const ext = fileName.split('.').pop().toLowerCase();
            
            // Danh sách các định dạng file nén hoặc file chạy -> Bắt buộc tải xuống
            const downloadExts = ['zip', 'rar', '7z', 'tar', 'gz', 'exe'];
            
            if (downloadExts.includes(ext)) {
                // Hành vi 1: Tải xuống trực tiếp
                if(confirm(`📦 Tệp [${fileName}] là tệp nén.\nBấm OK để tải xuống thiết bị của bạn.`)) {
                    // Mô phỏng tải xuống. Thực tế sẽ dùng window.location.href = fileUrl;
                    alert('⬇️ Đang tải tệp xuống...'); 
                }
            } else {
                // Hành vi 2: Xem trực tiếp bằng Google Drive Viewer
                if(confirm(`👀 Mở tệp [${fileName}] trong trình xem trước?`)) {
                    // Nếu là PDF thì trình duyệt đọc được ngay. 
                    // Nếu là DOCX, XLSX thì dùng Google Viewer đọc giùm.
                    let viewerUrl = fileUrl;
                    if(ext === 'doc' || ext === 'docx' || ext === 'xls' || ext === 'xlsx' || ext === 'ppt' || ext === 'pptx') {
                        viewerUrl = `https://drive.google.com/viewerng/viewer?url=${encodeURIComponent(fileUrl)}`;
                    }
                    window.open(viewerUrl, '_blank');
                }
            }
        }

        // JS Xử lý Modal Thêm/Sửa Bài Tập
        function openAssignmentModal(mode, title = '', classId = '', deadline = '', desc = '') {
            const modalTitle = document.getElementById('assignmentModalTitle');
            const submitBtn = document.getElementById('assignmentModalSubmitBtn');

            if(mode === 'add') {
                modalTitle.innerHTML = '<i class="bi bi-journal-plus me-2"></i>Tạo Bài tập mới';
                submitBtn.innerText = 'Giao bài tập';
                document.getElementById('modalAssignTitle').value = '';
                document.getElementById('modalAssignClass').value = '';
                document.getElementById('modalAssignDeadline').value = '';
                document.getElementById('modalAssignDesc').value = '';
                document.getElementById('assignmentFile').value = '';
            } else {
                modalTitle.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Cập nhật Bài tập';
                submitBtn.innerText = 'Lưu thay đổi';
                document.getElementById('modalAssignTitle').value = title;
                document.getElementById('modalAssignClass').value = classId;
                document.getElementById('modalAssignDeadline').value = deadline;
                document.getElementById('modalAssignDesc').value = desc;
            }
        }

        function confirmDeleteAssignment(title) {
            if(confirm(`⚠️ CẢNH BÁO: Bạn có chắc chắn muốn xóa bài tập [${title}]?\nTOÀN BỘ bài nộp của sinh viên cho bài tập này cũng sẽ bị xóa vĩnh viễn!`)) {
                alert(`✅ Đã xóa bài tập thành công!`);
            }
        }

        function openGradeModal(studentId, studentName, score, feedback) {
            document.getElementById('modalStudentId').innerText = 'MSSV: ' + studentId;
            document.getElementById('modalStudentName').innerText = studentName;
            document.getElementById('modalScoreInput').value = score;
            document.getElementById('modalFeedbackInput').value = feedback;
        }
=======
const assignmentState = {
    groups: [],
    assignments: [],
    submissions: [],
    selectedAssignmentId: 0,
    editingAssignmentId: 0,
    gradingSubmissionId: 0
};

function fmtDate(value) {
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

function openFile(link) {
    if (!link) {
        alert('No file attached.');
        return;
    }
    window.open(link, '_blank');
}

function populateGroupSelects(groups) {
    const filter = document.getElementById('assignmentGroupFilter');
    const modal = document.getElementById('modalAssignClass');

    const opts = ['<option value="">-- All groups --</option>'];
    const modalOpts = ['<option value="">-- Select group --</option>'];

    groups.forEach((g) => {
        const label = `${g.class_name} - ${g.subject_name} (G${g.group_code || ''})`;
        opts.push(`<option value="${g.group_id}">${label}</option>`);
        modalOpts.push(`<option value="${g.group_id}">${label}</option>`);
    });

    if (filter) filter.innerHTML = opts.join('');
    if (modal) modal.innerHTML = modalOpts.join('');
}

function renderAssignmentTable(rows) {
    const tbody = document.getElementById('teacherAssignmentsTbody');
    if (!tbody) return;

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No assignments found.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map((row) => {
        const deadline = fmtDate(row.deadline);
        const now = new Date();
        const isOpen = row.deadline ? new Date(row.deadline) >= now : true;
        const status = isOpen
            ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Open</span>'
            : '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">Closed</span>';
        const submitted = `${Number(row.submitted_count || 0)}/${Number(row.total_students || 0)}`;
        const label = `${row.class_name} - ${row.subject_name} (G${row.group_code || ''})`;

        return `
            <tr>
                <td class="ps-4 fw-bold text-primary">${row.title || ''}</td>
                <td class="fw-bold text-dark">${label}</td>
                <td><span class="text-muted small">N/A</span></td>
                <td><span class="text-danger fw-bold">${deadline}</span></td>
                <td>${status}</td>
                <td><span class="fw-bold text-primary">${submitted}</span></td>
                <td class="text-end pe-4">
                    <button class="btn btn-light action-btn text-primary border me-1" data-edit-id="${row.id}"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-light action-btn text-danger border" data-delete-id="${row.id}"><i class="bi bi-trash"></i></button>
                </td>
            </tr>`;
    }).join('');

    tbody.querySelectorAll('button[data-edit-id]').forEach((btn) => {
        btn.addEventListener('click', function () {
            const id = Number(btn.getAttribute('data-edit-id'));
            const item = assignmentState.assignments.find((x) => Number(x.id) === id);
            if (!item) return;
            assignmentState.editingAssignmentId = id;
            document.getElementById('assignmentModalTitle').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Update Assignment';
            document.getElementById('assignmentModalSubmitBtn').textContent = 'Save changes';
            document.getElementById('modalAssignTitle').value = item.title || '';
            document.getElementById('modalAssignClass').value = String(item.group_id || '');
            document.getElementById('modalAssignDeadline').value = item.deadline ? String(item.deadline).slice(0, 16) : '';
            document.getElementById('modalAssignDesc').value = item.description || '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('addAssignmentModal')).show();
        });
    });

    tbody.querySelectorAll('button[data-delete-id]').forEach((btn) => {
        btn.addEventListener('click', async function () {
            const id = Number(btn.getAttribute('data-delete-id'));
            if (!confirm('Delete this assignment?')) return;
            const res = await fetch(`/api/teacher/assignments/${id}`, { method: 'DELETE', headers: { Accept: 'application/json' } });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                alert(data.error || 'Cannot delete assignment.');
                return;
            }
            await loadAssignments();
            await loadAssignmentSelect();
        });
    });
}

async function loadAssignments() {
    const groupId = document.getElementById('assignmentGroupFilter')?.value || '';
    const query = new URLSearchParams();
    if (groupId) query.set('groupId', groupId);

    const res = await fetch(`/api/teacher/assignments?${query.toString()}`, { headers: { Accept: 'application/json' } });
    if (res.status === 401) {
        window.location.href = '/login.html';
        return;
    }
    if (!res.ok) {
        alert('Cannot load assignments.');
        return;
    }

    assignmentState.assignments = await res.json();
    if (!Array.isArray(assignmentState.assignments)) assignmentState.assignments = [];
    renderAssignmentTable(assignmentState.assignments);
}

async function submitAssignment() {
    const groupId = Number(document.getElementById('modalAssignClass')?.value || 0);
    const title = document.getElementById('modalAssignTitle')?.value?.trim() || '';
    const deadline = document.getElementById('modalAssignDeadline')?.value || null;
    const description = document.getElementById('modalAssignDesc')?.value?.trim() || '';

    if (!groupId || !title || !deadline) {
        alert('Please fill group, title and deadline.');
        return;
    }

    const payload = { groupId, title, deadline, description };
    const isEdit = Number.isFinite(assignmentState.editingAssignmentId) && assignmentState.editingAssignmentId > 0;
    const url = isEdit ? `/api/teacher/assignments/${assignmentState.editingAssignmentId}` : '/api/teacher/assignments';
    const method = isEdit ? 'PUT' : 'POST';

    const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(payload)
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        alert(data.error || 'Cannot save assignment.');
        return;
    }

    bootstrap.Modal.getInstance(document.getElementById('addAssignmentModal'))?.hide();
    assignmentState.editingAssignmentId = 0;
    await loadAssignments();
    await loadAssignmentSelect();
}

async function loadAssignmentSelect() {
    const select = document.getElementById('teacherAssignmentSelect');
    if (!select) return;

    const options = ['<option value="">-- Select assignment --</option>'];
    assignmentState.assignments.forEach((a) => {
        options.push(`<option value="${a.id}">[${a.class_name}] ${a.title}</option>`);
    });
    select.innerHTML = options.join('');

    if (assignmentState.selectedAssignmentId) {
        select.value = String(assignmentState.selectedAssignmentId);
    }
}

function renderSubmissions(rows) {
    const tbody = document.getElementById('teacherSubmissionTbody');
    if (!tbody) return;

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No submissions for this assignment.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map((row) => {
        const scoreCell = Number.isFinite(Number(row.score))
            ? `<span class="fw-bold text-success fs-5">${Number(row.score).toFixed(1)}</span>`
            : '<span class="badge bg-warning text-dark">Not graded</span>';

        return `
            <tr>
                <td class="ps-4 fw-bold text-dark">${row.username || ''}</td>
                <td class="fw-bold">${row.full_name || ''}</td>
                <td><span class="text-success small"><i class="bi bi-check-circle-fill me-1"></i>${fmtDate(row.submitted_at)}</span></td>
                <td><a class="text-decoration-none file-link text-primary" href="#" data-open-link="${row.drive_link || ''}">${row.drive_link ? 'Open submission file' : 'No file'}</a></td>
                <td class="text-center">${scoreCell}</td>
                <td class="text-end pe-4">
                    <button class="btn btn-sm btn-outline-primary fw-bold" data-grade-id="${row.submission_id}"><i class="bi bi-pencil-square me-1"></i>${Number.isFinite(Number(row.score)) ? 'Edit score' : 'Grade'}</button>
                </td>
            </tr>`;
    }).join('');

    tbody.querySelectorAll('a[data-open-link]').forEach((a) => {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            openFile(a.getAttribute('data-open-link'));
        });
    });

    tbody.querySelectorAll('button[data-grade-id]').forEach((btn) => {
        btn.addEventListener('click', function () {
            const id = Number(btn.getAttribute('data-grade-id'));
            const item = assignmentState.submissions.find((x) => Number(x.submission_id) === id);
            if (!item) return;
            assignmentState.gradingSubmissionId = id;
            document.getElementById('modalStudentId').textContent = `MSSV: ${item.username || ''}`;
            document.getElementById('modalStudentName').textContent = item.full_name || '';
            document.getElementById('modalScoreInput').value = Number.isFinite(Number(item.score)) ? Number(item.score) : '';
            document.getElementById('modalFeedbackInput').value = item.feedback || '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('gradeModal')).show();
        });
    });
}

async function loadSubmissions() {
    const assignmentId = Number(document.getElementById('teacherAssignmentSelect')?.value || 0);
    assignmentState.selectedAssignmentId = assignmentId;

    if (!assignmentId) {
        renderSubmissions([]);
        return;
    }

    const res = await fetch(`/api/teacher/assignments/${assignmentId}/submissions`, { headers: { Accept: 'application/json' } });
    if (!res.ok) {
        alert('Cannot load submissions.');
        return;
    }

    assignmentState.submissions = await res.json();
    if (!Array.isArray(assignmentState.submissions)) assignmentState.submissions = [];
    renderSubmissions(assignmentState.submissions);
}

function filterSubmissions() {
    const keyword = (document.getElementById('submissionSearchInput')?.value || '').trim().toLowerCase();
    const rows = Array.from(document.querySelectorAll('#teacherSubmissionTbody tr'));
    rows.forEach((row) => {
        const studentId = (row.children[0]?.textContent || '').toLowerCase();
        const fullName = (row.children[1]?.textContent || '').toLowerCase();
        row.style.display = (!keyword || studentId.includes(keyword) || fullName.includes(keyword)) ? '' : 'none';
    });
}

async function saveGrade() {
    if (!assignmentState.gradingSubmissionId) return;

    const scoreRaw = document.getElementById('modalScoreInput')?.value;
    const score = scoreRaw === '' ? null : Number(scoreRaw);
    const feedback = document.getElementById('modalFeedbackInput')?.value?.trim() || '';

    const res = await fetch(`/api/teacher/submissions/${assignmentState.gradingSubmissionId}/grade`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ score, feedback })
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        alert(data.error || 'Cannot save grade.');
        return;
    }

    bootstrap.Modal.getInstance(document.getElementById('gradeModal'))?.hide();
    loadSubmissions();
}

async function initTeacherAssignments() {
    const groupsRes = await fetch('/api/teacher/groups', { headers: { Accept: 'application/json' } });
    if (groupsRes.status === 401) {
        window.location.href = '/login.html';
        return;
    }
    if (groupsRes.ok) {
        assignmentState.groups = await groupsRes.json();
        if (!Array.isArray(assignmentState.groups)) assignmentState.groups = [];
        populateGroupSelects(assignmentState.groups);
    }

    document.getElementById('assignmentGroupFilter')?.addEventListener('change', loadAssignments);
    document.getElementById('teacherAssignmentSelect')?.addEventListener('change', loadSubmissions);
    document.getElementById('submissionSearchInput')?.addEventListener('input', filterSubmissions);

    document.getElementById('createAssignmentBtn')?.addEventListener('click', function () {
        assignmentState.editingAssignmentId = 0;
        document.getElementById('assignmentModalTitle').innerHTML = '<i class="bi bi-journal-plus me-2"></i>Tao Bai tap moi';
        document.getElementById('assignmentModalSubmitBtn').textContent = 'Giao bai tap';
        document.getElementById('modalAssignTitle').value = '';
        document.getElementById('modalAssignClass').value = '';
        document.getElementById('modalAssignDeadline').value = '';
        document.getElementById('modalAssignDesc').value = '';
    });

    document.getElementById('assignmentModalSubmitBtn')?.addEventListener('click', submitAssignment);
    document.getElementById('gradeSaveBtn')?.addEventListener('click', saveGrade);

    document.getElementById('list-tab')?.addEventListener('click', function () {
        assignmentState.editingAssignmentId = 0;
    });

    await loadAssignments();
    await loadAssignmentSelect();
    await loadSubmissions();
}

document.addEventListener('DOMContentLoaded', initTeacherAssignments);
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
