    const mockDB = {
        "CSDL": {
            name: "Cơ sở dữ liệu",
            groups: {
                "01": { 
                    teacher: "ThS. Nguyễn Văn X", start: "12/01/2026", end: "05/05/2026", session: "Sáng",
                    students: [
                        { mssv: "22050001", name: "Nguyễn Văn A", dob: "15/04/2004", cls: "25TH01", status: "1", note: "" },
                        { mssv: "22050022", name: "Trần Vũ Gia Huy", dob: "22/08/2004", cls: "25TH01", status: "2", note: "Đã nộp phép" },
                        { mssv: "22050089", name: "Phan Đình Luyến", dob: "10/11/2004", cls: "25TH01", status: "3", note: "" }
                    ]
                },
                "02": { 
                    teacher: "ThS. Lê Hoàng Z", start: "13/01/2026", end: "06/05/2026", session: "Chiều",
                    students: [
                        { mssv: "22050100", name: "Lê Tuấn B", dob: "20/05/2004", cls: "25TH02", status: "1", note: "" },
                        { mssv: "22050105", name: "Đỗ Minh C", dob: "05/09/2004", cls: "25TH02", status: "1", note: "" }
                    ]
                }
            }
        },
        "WEB": {
            name: "Lập trình Web",
            groups: {
                "01": { 
                    teacher: "ThS. Trần Thị Y", start: "15/01/2026", end: "10/05/2026", session: "Chiều",
                    students: [
                        { mssv: "22050201", name: "Hoàng Thanh D", dob: "12/12/2004", cls: "25TH01", status: "1", note: "" }
                    ]
                }
            }
        }
    };

    let currentDefaultSession = "Sáng";

    function applyStatusSelectStyle(selectElement) {
        if (!selectElement) {
            return;
        }

        const value = selectElement.value;
        if (value === '1') {
            selectElement.style.color = '#198754';
            selectElement.style.backgroundColor = '#ecfdf3';
            selectElement.style.borderColor = '#86efac';
        } else if (value === '2') {
            selectElement.style.color = '#b58105';
            selectElement.style.backgroundColor = '#fffbeb';
            selectElement.style.borderColor = '#fcd34d';
        } else {
            selectElement.style.color = '#dc3545';
            selectElement.style.backgroundColor = '#fff1f2';
            selectElement.style.borderColor = '#fda4af';
        }
        selectElement.style.fontWeight = '700';
    }

    function initDropdowns() {
        const subjectSelect = document.getElementById('subjectSelect');
        subjectSelect.innerHTML = '';
        for (const subjKey in mockDB) {
            const option = document.createElement('option');
            option.value = subjKey;
            option.text = mockDB[subjKey].name;
            subjectSelect.appendChild(option);
        }
        onSubjectChange(); 
    }

    function onSubjectChange() {
        const subjKey = document.getElementById('subjectSelect').value;
        const groupSelect = document.getElementById('groupSelect');
        groupSelect.innerHTML = '';
        
        const groups = mockDB[subjKey].groups;
        for (const groupKey in groups) {
            const option = document.createElement('option');
            option.value = groupKey;
            option.text = "Nhóm " + groupKey;
            groupSelect.appendChild(option);
        }
        onGroupChange();
    }

    function onGroupChange() {
        const subjKey = document.getElementById('subjectSelect').value;
        const groupKey = document.getElementById('groupSelect').value;
        const groupData = mockDB[subjKey].groups[groupKey];

        document.getElementById('lblTeacher').innerText = groupData.teacher;
        document.getElementById('lblTime').innerText = groupData.start + " - " + groupData.end;
        
        currentDefaultSession = groupData.session;
        document.getElementById('attendanceSession').value = currentDefaultSession;
        checkSessionReason();

        renderStudentList(groupData.students);
    }

    function renderStudentList(students) {
        const tbody = document.getElementById('studentTableBody');
        tbody.innerHTML = '';
        
        students.forEach((sv, index) => {
            const tr = document.createElement('tr');
            tr.className = `student-row ${sv.status === '2' ? 'bg-warning bg-opacity-10' : sv.status === '3' ? 'bg-danger bg-opacity-10' : ''}`;
            
            const badgeHtml = sv.status === '2' ? `<span class="badge bg-warning text-dark border border-warning"><i class="bi bi-file-medical-fill me-1"></i>${sv.note}</span>` 
                            : `<input type="text" class="form-control form-control-sm bg-light border-0" placeholder="Ghi chú..." value="${sv.note}">`;

            tr.innerHTML = `
                <td class="text-center stt-col">${index + 1}</td>
                <td class="fw-bold text-dark">${sv.mssv}</td>
                <td class="fw-bold ${sv.status === '3' ? 'text-danger' : 'text-dark'} student-name">${sv.name}</td>
                <td>${sv.dob}</td>
                <td>${sv.cls}</td>
                <td>
                    <select class="form-select form-select-sm status-select val-${sv.status}" onchange="updateColor(this)">
                        <option value="1" style="color:#198754;" ${sv.status === '1' ? 'selected' : ''}>Có mặt</option>
                        <option value="2" style="color:#b58105;" ${sv.status === '2' ? 'selected' : ''}>Vắng có phép</option>
                        <option value="3" style="color:#dc3545;" ${sv.status === '3' ? 'selected' : ''}>Vắng không phép</option>
                    </select>
                </td>
                <td>${badgeHtml}</td>
            `;
            tbody.appendChild(tr);
        });
        tbody.querySelectorAll('.status-select').forEach(function (select) {
            applyStatusSelectStyle(select);
        });
        recalculateAttendance();
    }

    document.addEventListener('DOMContentLoaded', initDropdowns);

    function checkSessionReason() {
        const selectedSession = document.getElementById('attendanceSession').value;
        const reasonDiv = document.getElementById('sessionReasonDiv');
        if(selectedSession !== currentDefaultSession) {
            reasonDiv.classList.remove('invisible');
            document.getElementById('sessionReason').required = true;
        } else {
            reasonDiv.classList.add('invisible');
            document.getElementById('sessionReason').required = false;
        }
    }

    function updateColor(selectElement) {
        const tr = selectElement.closest('tr');
        selectElement.classList.remove('val-1', 'val-2', 'val-3');
        tr.classList.remove('bg-warning', 'bg-danger', 'bg-opacity-10');
        tr.querySelector('.student-name').classList.remove('text-danger');
        tr.querySelector('.student-name').classList.add('text-dark');

        if (selectElement.value === '1') {
            selectElement.classList.add('val-1');
        } else if (selectElement.value === '2') {
            selectElement.classList.add('val-2');
            tr.classList.add('bg-warning', 'bg-opacity-10');
        } else if (selectElement.value === '3') {
            selectElement.classList.add('val-3');
            tr.classList.add('bg-danger', 'bg-opacity-10');
            tr.querySelector('.student-name').classList.remove('text-dark');
            tr.querySelector('.student-name').classList.add('text-danger');
        }
        applyStatusSelectStyle(selectElement);
        recalculateAttendance();
    }

    function recalculateAttendance() {
        const selects = document.querySelectorAll('.status-select');
        let present = 0; let absent = 0;
        selects.forEach(select => { if(select.value === '1') present++; else absent++; });
        document.getElementById('totalCount').innerText = present + absent;
        document.getElementById('presentCount').innerText = present;
        document.getElementById('absentCount').innerText = absent;
    }

    function filterTable() {
        const input = document.getElementById("searchInput").value.toLowerCase();
        const rows = document.querySelectorAll("#attendanceTable tbody .student-row");
        rows.forEach(row => {
            const mssv = row.cells[1].innerText.toLowerCase();
            const name = row.cells[2].innerText.toLowerCase();
            if(mssv.includes(input) || name.includes(input)) row.style.display = "";
            else row.style.display = "none";
        });
    }

    function addStudentToTable() {
        const mssv = document.getElementById('newMssv').value;
        const name = document.getElementById('newFullName').value;
        const dob = document.getElementById('newDob').value || "Chưa cập nhật";
        const cls = document.getElementById('newClass').value;

        if(!mssv || !name || !cls) { alert("Vui lòng nhập MSSV, Họ Tên và Lớp học!"); return; }

        const tbody = document.querySelector('#attendanceTable tbody');
        const rowCount = tbody.querySelectorAll('tr').length + 1;
        
        let formattedDob = dob;
        if(dob !== "Chưa cập nhật") {
            const parts = dob.split('-');
            formattedDob = `${parts[2]}/${parts[1]}/${parts[0]}`;
        }

        const tr = document.createElement('tr');
        tr.className = "student-row";
        tr.innerHTML = `
            <td class="text-center stt-col">${rowCount}</td>
            <td class="fw-bold text-dark">${mssv}</td>
            <td class="fw-bold text-primary student-name">${name} <span class="badge bg-primary ms-1 small">Mới</span></td>
            <td>${formattedDob}</td>
            <td>${cls}</td>
            <td>
                <select class="form-select form-select-sm status-select val-1" onchange="updateColor(this)">
                    <option value="1" style="color:#198754;" selected>Có mặt</option>
                    <option value="2" style="color:#b58105;">Vắng có phép</option>
                    <option value="3" style="color:#dc3545;">Vắng không phép</option>
                </select>
            </td>
            <td><input type="text" class="form-control form-control-sm bg-light border-0" placeholder="Ghi chú..."></td>
        `;

        tbody.appendChild(tr);
        applyStatusSelectStyle(tr.querySelector('.status-select'));
        bootstrap.Modal.getInstance(document.getElementById('addStudentModal')).hide();
        document.getElementById('addStudentForm').reset();
        recalculateAttendance();
    }

    function exportToExcel() {
        const subjName = document.getElementById('subjectSelect').options[document.getElementById('subjectSelect').selectedIndex].text;
        const group = document.getElementById('groupSelect').value;
        const date = document.getElementById('attendanceDate').value;
        
        alert(`📥 Đã xuất file Excel thành công!\n\nThông tin Header file:\n- Môn học: ${subjName}\n- Mã môn học: [Từ CSDL]\n- Nhóm: ${group}\n- Ngày học: ${date}\n- Cột: STT | MSSV | Họ và tên | Ngày sinh | Lớp | Trạng thái`);
    }
