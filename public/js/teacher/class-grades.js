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
