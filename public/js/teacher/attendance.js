        function goToAttendance(subjectKey, groupKey, date) {
            const triggerEl = document.querySelector('#attendance-tab');
            const tab = new bootstrap.Tab(triggerEl);
            tab.show();
            
            document.getElementById('subjectSelect').value = subjectKey;
            onSubjectChange(); 
            document.getElementById('groupSelect').value = groupKey;
            onGroupChange();   
            document.getElementById('attendanceDate').value = date;
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        const mockDB = {
            "CSDL": {
                name: "An ninh Cơ sở dữ liệu",
                groups: {
                    "01": { 
                        start: "16/01/2026", end: "05/05/2026", session: "Sáng",
                        students: [
                            { mssv: "22050001", name: "Nguyễn Văn A", dob: "15/04/2004", cls: "25TH01", status: "1", note: "" },
                            { mssv: "22050022", name: "Trần Vũ Gia Huy", dob: "22/08/2004", cls: "25TH01", status: "2", note: "Đã nộp phép" },
                            { mssv: "22050089", name: "Phan Đình Luyến", dob: "10/11/2004", cls: "25TH01", status: "3", note: "" },
                            { mssv: "22050111", name: "Nguyễn Thị C", dob: "01/01/2004", cls: "25TH01", status: "1", note: "" }
                        ]
                    }
                }
            },
            "CTDL": {
                name: "Cấu trúc dữ liệu & Giải thuật",
                groups: {
                    "01": { 
                        start: "18/01/2026", end: "10/05/2026", session: "Chiều",
                        students: [
                            { mssv: "22050201", name: "Hoàng Thanh D", dob: "12/12/2004", cls: "24CNTT02", status: "1", note: "" },
                            { mssv: "22050333", name: "Lê Văn C", dob: "20/05/2004", cls: "24CNTT02", status: "3", note: "Nghỉ không phép" }
                        ]
                    }
                }
            }
        };

        let currentDefaultSession = "Sáng";

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

            // Cập nhật thời gian
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
                    <td class="text-center stt-col text-muted">${index + 1}</td>
                    <td class="fw-bold text-dark mssv-cell">${sv.mssv}</td>
                    <td class="fw-bold ${sv.status === '3' ? 'text-danger' : 'text-dark'} student-name name-cell">${sv.name}</td>
                    <td>${sv.dob}</td>
                    <td>${sv.cls}</td>
                    <td>
                        <select class="form-select form-select-sm status-select val-${sv.status}" onchange="updateColor(this)">
                            <option value="1" ${sv.status === '1' ? 'selected' : ''}>Có mặt</option>
                            <option value="2" ${sv.status === '2' ? 'selected' : ''}>Vắng có phép</option>
                            <option value="3" ${sv.status === '3' ? 'selected' : ''}>Vắng không phép</option>
                        </select>
                    </td>
                    <td>${badgeHtml}</td>
                `;
                tbody.appendChild(tr);
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
            recalculateAttendance();
        }

        function recalculateAttendance() {
            const selects = document.querySelectorAll('.status-select');
            let present = 0; let absent = 0;
            selects.forEach(select => { 
                const row = select.closest('tr');
                if (row.style.display !== "none") {
                    if(select.value === '1') present++; else absent++; 
                }
            });
            document.getElementById('totalCount').innerText = present + absent;
            document.getElementById('presentCount').innerText = present;
            document.getElementById('absentCount').innerText = absent;
        }

        function filterTable() {
            const input = document.getElementById("searchInput").value.toLowerCase();
            const rows = document.querySelectorAll("#attendanceTable tbody .student-row");
            rows.forEach(row => {
                const mssv = row.querySelector(".mssv-cell").innerText.toLowerCase();
                const name = row.querySelector(".name-cell").innerText.toLowerCase();
                if(mssv.includes(input) || name.includes(input)) row.style.display = "";
                else row.style.display = "none";
            });
            recalculateAttendance();
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
                <td class="text-center stt-col text-muted">${rowCount}</td>
                <td class="fw-bold text-dark mssv-cell">${mssv}</td>
                <td class="fw-bold text-primary student-name name-cell">${name} <span class="badge bg-primary ms-1 small">Mới</span></td>
                <td>${formattedDob}</td>
                <td>${cls}</td>
                <td>
                    <select class="form-select form-select-sm status-select val-1" onchange="updateColor(this)">
                        <option value="1" selected>Có mặt</option>
                        <option value="2">Vắng có phép</option>
                        <option value="3">Vắng không phép</option>
                    </select>
                </td>
                <td><input type="text" class="form-control form-control-sm bg-light border-0" placeholder="Ghi chú..."></td>
            `;

            tbody.appendChild(tr);
            bootstrap.Modal.getInstance(document.getElementById('addStudentModal')).hide();
            document.getElementById('addStudentForm').reset();
            
            document.getElementById("searchInput").value = "";
            filterTable();
        }

        function exportToExcel() {
            const subjName = document.getElementById('subjectSelect').options[document.getElementById('subjectSelect').selectedIndex].text;
            const group = document.getElementById('groupSelect').value;
            const date = document.getElementById('attendanceDate').value;
            
            alert(`📥 Đã xuất file Excel thành công!\n\nThông tin Header file:\n- Môn học: ${subjName}\n- Mã môn học: [Từ CSDL]\n- Nhóm: ${group}\n- Ngày học: ${date}\n- Cột: STT | MSSV | Họ và tên | Ngày sinh | Lớp | Trạng thái`);
        }
