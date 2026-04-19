<<<<<<< HEAD
        const semesterClassMap = {
            "HK1-2025": [
                { value: "25TH01", label: "25TH01 - An ninh Cơ sở dữ liệu" },
                { value: "24CNTT02", label: "24CNTT02 - Cấu trúc DL & Giải thuật" }
            ],
            "HK2-2025": [
                { value: "25TH03", label: "25TH03 - Lập trình Web" },
                { value: "24CNTT01", label: "24CNTT01 - Hệ điều hành" }
            ],
            "HK3-2025": [
                { value: "25TH05", label: "25TH05 - Thực tập hè CNTT" }
            ]
        };

        // Gọi hàm tính điểm cho tất cả các dòng khi trang vừa load xong
        document.addEventListener("DOMContentLoaded", function() {
            const semesterSelect = document.getElementById("semesterSelect");
            const classSelect = document.getElementById("classSelect");

            if (semesterSelect && classSelect) {
                populateClassOptionsBySemester(semesterSelect.value);
                semesterSelect.addEventListener("change", function() {
                    populateClassOptionsBySemester(this.value);
                });
            }

            const rows = document.querySelectorAll("#gradesTable tbody tr");
            rows.forEach(row => {
                const firstInput = row.querySelector(".assign-score");
                if (firstInput) calculateRow(firstInput);
            });
        });

        function populateClassOptionsBySemester(semesterValue) {
            const classSelect = document.getElementById("classSelect");
            if (!classSelect) {
                return;
            }

            const classes = semesterClassMap[semesterValue] || [];
            classSelect.innerHTML = "";

            classes.forEach(function(cls, index) {
                const option = document.createElement("option");
                option.value = cls.value;
                option.text = cls.label;
                if (index === 0) {
                    option.selected = true;
                }
                classSelect.appendChild(option);
            });
        }

        // Hàm tính toán điểm khi Giảng viên nhập số
        function calculateRow(inputElement) {
            const tr = inputElement.closest('tr');
            
            // Lấy giá trị các ô nhập (nếu rỗng thì tính là chưa có điểm)
            const assignVal = tr.querySelector('.assign-score').value;
            const midVal = tr.querySelector('.midterm-score').value;
            const finalVal = tr.querySelector('.final-score').value;

            const totalCell = tr.querySelector('.total-score');
            const letterCell = tr.querySelector('.grade-letter');

            // Chỉ tính tổng kết khi ĐÃ NHẬP ĐỦ 3 cột điểm
            if (assignVal !== "" && midVal !== "" && finalVal !== "") {
                const assignScore = parseFloat(assignVal);
                const midScore = parseFloat(midVal);
                const finalScore = parseFloat(finalVal);

                // Giới hạn nhập từ 0 đến 10
                if(assignScore < 0 || assignScore > 10 || midScore < 0 || midScore > 10 || finalScore < 0 || finalScore > 10) {
                    alert("⚠️ Vui lòng nhập điểm trong khoảng từ 0.0 đến 10.0");
                    inputElement.value = "";
                    return;
                }

                // Tính toán trọng số: 20% - 30% - 50%
                let total = (assignScore * 0.2) + (midScore * 0.3) + (finalScore * 0.5);
                total = total.toFixed(1); // Làm tròn 1 chữ số thập phân
                
                totalCell.innerText = total;

                // Quy đổi điểm chữ và set màu sắc badge
                letterCell.className = "badge w-100 py-2 grade-letter "; // Reset class
                const t = parseFloat(total);
                
                if (t >= 8.5) {
                    letterCell.innerText = "A";
                    letterCell.classList.add("grade-A");
                } else if (t >= 7.0) {
                    letterCell.innerText = "B";
                    letterCell.classList.add("grade-B");
                } else if (t >= 5.5) {
                    letterCell.innerText = "C";
                    letterCell.classList.add("grade-C");
                } else if (t >= 4.0) {
                    letterCell.innerText = "D";
                    letterCell.classList.add("grade-D");
                } else {
                    letterCell.innerText = "F";
                    letterCell.classList.add("grade-F");
                }

            } else {
                // Chưa nhập đủ điểm thì hiển thị '--'
                totalCell.innerText = "--";
                letterCell.innerText = "--";
                letterCell.className = "badge w-100 py-2 grade-letter text-muted border";
            }
        }

        // Hàm giả lập lưu điểm
        function saveGrades() {
            alert("✅ Đã lưu toàn bộ Bảng điểm vào Cơ sở dữ liệu thành công!");
        }
=======
const gradeState = {
    groups: [],
    rows: [],
    selectedGroupId: 0
};

function roundHalf(value) {
    return Math.round(value * 2) / 2;
}

function calcFinal(midterm, finalExam) {
    return roundHalf((midterm * 0.4) + (finalExam * 0.6));
}

function toGrade(value) {
    if (value >= 8.5) return 'A';
    if (value >= 7.0) return 'B';
    if (value >= 5.5) return 'C';
    if (value >= 4.0) return 'D';
    return 'F';
}

function toResult(value) {
    return value >= 4.0 ? 'Pass' : 'Fail';
}

function updateBadge(count) {
    const badge = document.getElementById('gradeStudentCountBadge');
    if (badge) badge.textContent = `${count} students`;
}

function renderGrades(rows) {
    const tbody = document.getElementById('teacherGradesTbody');
    if (!tbody) return;

    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No grade rows found for this group.</td></tr>';
        updateBadge(0);
        return;
    }

    tbody.innerHTML = rows.map((row, idx) => {
        const midterm = Number(row.midterm_score ?? 0);
        const finalExam = Number(row.final_score ?? 0);
        const finalScore = calcFinal(midterm, finalExam);
        const grade = toGrade(finalScore);
        const result = toResult(finalScore);
        const resultClass = result === 'Pass' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger';

        return `
            <tr data-student-id="${row.student_id}">
                <td class="text-center fw-bold">${idx + 1}</td>
                <td class="fw-bold text-primary">${row.username || ''}</td>
                <td class="fw-bold">${row.full_name || ''}</td>
                <td>${row.class_name || ''}</td>
                <td><input type="number" class="form-control text-center midterm-input" min="0" max="10" step="0.5" value="${midterm}"></td>
                <td><input type="number" class="form-control text-center final-input" min="0" max="10" step="0.5" value="${finalExam}"></td>
                <td class="text-center fw-bold final-score">${finalScore.toFixed(1)}</td>
                <td class="text-center letter-grade">${grade}</td>
                <td class="text-center"><span class="badge ${resultClass} fw-bold result-badge">${result}</span></td>
                <td><input type="text" class="form-control" placeholder="Optional note"></td>
            </tr>`;
    }).join('');

    updateBadge(rows.length);

    tbody.querySelectorAll('input.midterm-input, input.final-input').forEach((input) => {
        input.addEventListener('input', function () {
            const tr = input.closest('tr');
            if (!tr) return;

            const mid = Number(tr.querySelector('.midterm-input')?.value || 0);
            const fin = Number(tr.querySelector('.final-input')?.value || 0);
            const finalScore = calcFinal(mid, fin);
            const grade = toGrade(finalScore);
            const result = toResult(finalScore);

            tr.querySelector('.final-score').textContent = finalScore.toFixed(1);
            tr.querySelector('.letter-grade').textContent = grade;
            const badge = tr.querySelector('.result-badge');
            badge.textContent = result;
            badge.className = `badge fw-bold result-badge ${result === 'Pass' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'}`;
        });
    });
}

async function loadGroups() {
    const res = await fetch('/api/teacher/groups', { headers: { Accept: 'application/json' } });
    if (res.status === 401) {
        window.location.href = '/login.html';
        return [];
    }
    if (!res.ok) {
        alert('Cannot load teacher groups.');
        return [];
    }

    const groups = await res.json();
    return Array.isArray(groups) ? groups : [];
}

function fillClassSelect(groups) {
    const select = document.getElementById('classSelect');
    if (!select) return;

    const options = ['<option value="">-- Select group --</option>'];
    groups.forEach((g) => {
        const text = `${g.class_name} - ${g.subject_name} (G${g.group_code || ''})`;
        options.push(`<option value="${g.group_id}">${text}</option>`);
    });
    select.innerHTML = options.join('');
}

async function loadGrades() {
    const groupId = Number(document.getElementById('classSelect')?.value || 0);
    gradeState.selectedGroupId = groupId;

    if (!groupId) {
        gradeState.rows = [];
        renderGrades([]);
        return;
    }

    const res = await fetch(`/api/teacher/grades?groupId=${groupId}`, { headers: { Accept: 'application/json' } });
    if (!res.ok) {
        alert('Cannot load grades.');
        return;
    }

    const rows = await res.json();
    gradeState.rows = Array.isArray(rows) ? rows : [];
    renderGrades(gradeState.rows);
}

async function saveGrades() {
    if (!gradeState.selectedGroupId) {
        alert('Please select a group first.');
        return;
    }

    const grades = Array.from(document.querySelectorAll('#teacherGradesTbody tr[data-student-id]')).map((tr) => {
        return {
            studentId: Number(tr.getAttribute('data-student-id')),
            midtermScore: Number(tr.querySelector('.midterm-input')?.value || 0),
            finalScore: Number(tr.querySelector('.final-input')?.value || 0)
        };
    });

    const res = await fetch('/api/teacher/grades/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ groupId: gradeState.selectedGroupId, grades })
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        alert(data.error || 'Cannot save grades.');
        return;
    }

    alert('Grades saved successfully.');
}

window.saveGrades = saveGrades;

async function initTeacherGrades() {
    gradeState.groups = await loadGroups();
    fillClassSelect(gradeState.groups);

    document.getElementById('classSelect')?.addEventListener('change', loadGrades);

    if (gradeState.groups.length) {
        document.getElementById('classSelect').value = String(gradeState.groups[0].group_id);
        loadGrades();
    }
}

document.addEventListener('DOMContentLoaded', initTeacherGrades);
>>>>>>> 667040e9222c4fa2832f8cd5ae162acf226ecff6
