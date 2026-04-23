# CMS BDU - Hệ Thống Quản Lý Đào Tạo

Hệ thống Quản lý Đào tạo (Content Management System) dành cho Trường Đại học Bình Dương (BDU).

## Giới thiệu

CMS BDU là hệ thống quản lý học vụ trực tuyến, hỗ trợ quản lý:

- **Tài khoản người dùng** (Admin, Giảng viên, Sinh viên, BCS)
- **Lớp học & Môn học** (phân công giảng dạy, quản lý học phần)
- **Phân công giảng dạy** (xếp lịch, quản lý nhóm, lịch trình chi tiết)
- **Điểm danh & Báo cáo** (theo dõi buổi học, thống kê)
- **Tài liệu & Thông báo** (quản lý tài liệu môn học, gửi thông báo)

## Yêu cầu hệ thống

- **PHP** 8.0+
- **MySQL** 5.7+ / **MariaDB** 10.3+
- **Web Server**: Apache (XAMPP, WAMP) hoặc Nginx
- **PHP Extensions**: PDO, MySQLi, mbstring, json, session

## Cài đặt

### 1. Clone/Download project

```bash
git clone <repository-url>
cd cms
```

### 2. Cấu hình Database

Tạo database trong MySQL:

```sql
CREATE DATABASE cms_bdu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Import file `db.sql`:

```bash
mysql -u root -p cms_bdu < db.sql
```

### 3. Cấu hình kết nối

Copy file cấu hình môi trường:

```bash
cp .env.example .env
```

Chỉnh sửa `.env`:

```env
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=your_password
DB_NAME=cms_bdu
DB_PORT=3306
```

### 4. Chạy hệ thống

Truy cập qua trình duyệt:

```
http://localhost/cms/
```

**Tài khoản Admin mặc định:**

- Username: `admin`
- Password: `123456@`
- Email: `admin@bdu.edu.vn`

## Cấu trúc dự án

```
cms/
├── config/
│   ├── config.php        # Cấu hình database & app
│   ├── helpers.php       # Hàm helper (e(), db_fetch_all(), ...)
│   └── session.php       # Quản lý session & phân quyền
├── controllers/
│   ├── authController.php
│   ├── admin/
│   │   ├── accountController.php
│   │   ├── classController.php
│   │   ├── classSubjectController.php
│   │   ├── departmentController.php
│   │   ├── importController.php
│   │   ├── roomController.php
│   │   ├── semesterController.php
│   │   └── subjectController.php
│   ├── attendanceController.php
│   ├── documentController.php
│   └── feedbackController.php
├── layouts/
│   ├── admin-sidebar.php
│   ├── admin-topbar.php
│   └── footer.php
├── public/
│   ├── css/
│   │   ├── admin/
│   │   │   ├── admin-layout.css
│   │   │   └── assignments.css
│   │   ├── layout.css
│   │   └── style.css
│   └── js/
│       ├── admin/
│       │   ├── admin-layout.js
│       │   └── assignments.js
│       └── student/
├── views/
│   ├── admin/
│   │   ├── accounts.php       # Quản lý tài khoản
│   │   ├── admin-profile.php
│   │   ├── assignments.php    # Phân công giảng dạy
│   │   ├── classes-subjects.php
│   │   ├── home.php
│   │   ├── org-settings.php
│   │   └── system-logs.php
│   ├── bcs/                  # Ban Cán sự lớp
│   ├── student/               # Trang sinh viên
│   ├── login.php
│   └── ...
├── db.sql                    # Cấu trúc database
├── .env.example
└── README.md
```

## Các tính năng chính

### Trang Admin

| Trang | Mô tả |
|-------|--------|
| `home.php` | Dashboard tổng quan với thống kê |
| `accounts.php` | Quản lý tài khoản (thêm/sửa/xóa/phân quyền) |
| `classes-subjects.php` | Quản lý lớp học & môn học |
| `assignments.php` | Phân công giảng dạy, xếp lịch, quản lý buổi học |
| `org-settings.php` | Cấu hình học vụ (khoa, phòng, năm học) |
| `system-logs.php` | Nhật ký hoạt động hệ thống |

### Trang Ban Cán Sự (BCS)

| Trang | Mô tả |
|-------|--------|
| `home.php` | Dashboard BCS với thông tin lớp, sĩ số, vắng hôm nay |
| `attendance.php` | Ghi nhận điểm danh buổi học |
| `announcements.php` | Xem thông báo từ giảng viên |
| `feedback.php` | Phản hồi/Hỗ trợ (gửi yêu cầu, theo dõi trạng thái) |
| `documents.php` | Xem tài liệu môn học |
| `dashboard-detail.php` | Chi tiết bảng điều khiển |
| `profile.php` | Hồ sơ cá nhân BCS |

### Trang Sinh viên

| Trang | Mô tả |
|-------|--------|
| `home.php` | Dashboard cá nhân với số môn, vắng, cảnh báo |
| `schedule.php` | Xem thời khóa biểu cá nhân |
| `my-attendance.php` | Lịch sử điểm danh của bản thân |
| `my-feedback.php` | Gửi & theo dõi phản hồi |
| `grades.php` | Xem điểm số |
| `documents.php` | Tải tài liệu môn học |
| `notifications-all.php` | Tất cả thông báo |
| `student-profile.php` | Hồ sơ sinh viên |
| `assignments.php` | Xem phân công giảng dạy liên quan |

### Phân quyền người dùng

| Role | Mô tả |
|------|--------|
| `admin` | Quản trị viên hệ thống - toàn quyền quản lý |
| `support_admin` | Hỗ trợ quản trị - quản lý cấu hình học vụ |
| `teacher` | Giảng viên - quản lý lớp học phần, điểm danh, tài liệu |
| `bcs` | Ban Cán sự lớp - điểm danh, phản hồi, xem thông báo |
| `student` | Sinh viên - xem lịch học, điểm danh, tài liệu, phản hồi |

### Tính năng theo vai trò

#### Admin
- Dashboard thống kê tổng quan (sinh viên, giảng viên, lớp học)
- Quản lý tài khoản người dùng
- Quản lý lớp học, môn học, học kỳ
- Phân công giảng dạy & xếp lịch
- Cấu hình học vụ (khoa, phòng)
- Xem nhật ký hệ thống

#### Giảng viên
- Dashboard lớp phụ trách
- Ghi nhận điểm danh sinh viên
- Tải lên tài liệu môn học
- Gửi thông báo đến lớp
- Xem phản hồi từ BCS
- Quản lý điểm số

#### BCS (Ban Cán Sự)
- Dashboard với sĩ số lớp, số vắng hôm nay
- Ghi nhận điểm danh buổi học
- Xem thông báo từ giảng viên
- Gửi phản hồi/yêu cầu hỗ trợ
- Xem tài liệu môn học

#### Sinh viên
- Dashboard cá nhân với thống kê học tập
- Xem thời khóa biểu cá nhân
- Xem lịch sử điểm danh
- Xem điểm số
- Xem & tải tài liệu
- Gửi phản hồi

## Cách sử dụng

### Admin

**Thêm lớp học phần mới:**

1. Vào **Quản lý Lớp & Môn**
2. Chọn môn học → nhấn **Thêm lớp học phần**
3. Chọn lớp hành chính, học kỳ, giảng viên
4. Điền thời gian mở/đóng môn

**Phân công giảng dạy:**

1. Vào **Phân công Giảng dạy**
2. Tab **Xem Theo Lớp Học Phần**: Xem danh sách môn đã phân công
3. Tab **Thời Khóa Biểu Tổng**: Xem lịch dạy tuần, lọc theo giảng viên/phòng

### BCS (Ban Cán Sự)

**Ghi nhận điểm danh:**

1. Vào trang **Điểm danh**
2. Chọn lớp học phần và nhóm
3. Đánh dấu vắng/mặt cho từng sinh viên
4. Nộp phiếu điểm danh

**Gửi phản hồi:**

1. Vào trang **Phản hồi**
2. Chọn chủ đề và mô tả vấn đề
3. Gửi và theo dõi trạng thái

### Sinh viên

**Xem lịch học:**

1. Vào trang **Thời khóa biểu**
2. Chọn học kỳ để xem lịch các buổi học

**Xem điểm danh:**

1. Vào trang **Điểm danh của tôi**
2. Xem lịch sử các buổi đã học và trạng thái

## Công nghệ sử dụng

- **Backend**: PHP 8+ (Vanilla PHP, không framework)
- **Frontend**: HTML5, CSS3, Bootstrap 5.3, Vanilla JavaScript
- **Database**: MySQL/MariaDB với utf8mb4
- **Icons**: Bootstrap Icons
- **Excel Processing**: SheetJS (xlsx)

## Database Schema

Hệ thống sử dụng các bảng chính:

- `users` - Tài khoản người dùng
- `departments` - Khoa/ngành
- `semesters` - Học kỳ
- `classes` - Lớp hành chính
- `subjects` - Danh mục môn học
- `class_subjects` - Lớp học phần
- `class_subject_groups` - Nhóm học trong lớp học phần
- `class_sessions` - Buổi học chi tiết
- `attendance` - Điểm danh
- `documents` - Tài liệu
- `notifications` - Thông báo
- `feedback` - Phản hồi BCS
- `system_logs` - Nhật ký hệ thống

## Bảo mật

- Mật khẩu được hash bằng `password_hash()` (bcrypt)
- Session-based authentication
- Role-based access control (RBAC)
- SQL Injection protection qua PDO prepared statements
- XSS protection qua `htmlspecialchars()`

## Mô tả luồng hoạt động hệ thống

### 1. Luồng Đăng nhập & Xác thực

```
┌─────────┐     ┌──────────────┐     ┌─────────────┐     ┌──────────────┐
│  User   │────▶│  login.php   │────▶│ authController│────▶│   Database   │
│ (Browser)│     │  (Form)      │     │  (Process)   │     │  (Validate)  │
└─────────┘     └──────────────┘     └─────────────┘     └──────┬───────┘
                                                                │
                                            ┌───────────────────┴───────────┐
                                            ▼                               ▼
                                     ┌────────────┐                  ┌────────────┐
                                     │  Success   │                  │  Failed    │
                                     │  → Redirect│                  │  → Error   │
                                     │  Role Home │                  │  → Login   │
                                     └────────────┘                  └────────────┘
```

**Chi tiết:**
1. User truy cập `login.php`, nhập username/password
2. `authController.php` nhận dữ liệu POST
3. Validate thông tin từ bảng `users` trong database
4. Nếu thành công: tạo session, lưu `user_id`, `role`, chuyển hướng theo role
5. Nếu thất bại: hiển thị thông báo lỗi

---

### 2. Luồng Phân công Giảng dạy

```
┌─────────┐     ┌──────────────────┐     ┌──────────────────┐
│  Admin  │────▶│ assignments.php  │────▶│ loadSubjects()   │
│         │     │ (Tab 1: Card)   │     │ (AJAX/Fetch)     │
└─────────┘     └────────┬─────────┘     └────────┬─────────┘
                         │                        │
                         ▼                        ▼
                ┌──────────────────┐     ┌──────────────────┐
                │ Click "Thêm nhóm"│     │ fetch subjects   │
                │ → Modal mở ra   │     │ from DB          │
                └────────┬─────────┘     └────────┬─────────┘
                         │                        │
                         ▼                        ▼
                ┌──────────────────┐     ┌──────────────────┐
                │ Form: Chọn lớp,  │     │ Render cards:   │
                │ giảng viên, thứ, │     │ - Subject name  │
                │ tiết, phòng      │     │ - Class list    │
                └────────┬─────────┘     │ - Add group btn │
                         │               └────────┬─────────┘
                         ▼                        │
                ┌──────────────────┐               │
                │ Submit → API     │               │
                │ /api/assignments │               │
                └────────┬─────────┘               │
                         │                        │
                         ▼                        ▼
                ┌──────────────────────────────────────────┐
                │           Database Tables                  │
                │  class_subjects → class_subject_groups    │
                │  (cs_id)              (group_id)         │
                └──────────────────────────────────────────┘
```

**Chi tiết:**
1. Admin vào trang `assignments.php`
2. PHP load danh sách subjects, assignments từ DB
3. JavaScript render cards cho từng môn học
4. Admin click "Thêm nhóm" → Modal mở với form
5. Chọn: lớp, giảng viên, thứ, tiết bắt đầu/kết thúc, phòng
6. Submit → `classSubjectController.php` xử lý
7. Lưu vào `class_subjects` và `class_subject_groups`
8. Ghi log vào `system_logs`

---

### 3. Luồng Xếp lịch & Master Schedule

```
┌─────────┐     ┌──────────────────┐     ┌──────────────────┐
│  Admin  │────▶│ Tab 2: TKB Tổng  │────▶│ renderMasterSch()│
│         │     │ (Master Schedule)│     │ (JavaScript)     │
└─────────┘     └────────┬─────────┘     └────────┬─────────┘
                         │                        │
                         ▼                        ▼
                ┌──────────────────┐     ┌──────────────────┐
                │ Filter: Học kỳ, │     │ Load allCourses  │
                │ Giảng viên,      │     │ from window.*    │
                │ Phòng            │     │ data             │
                └────────┬─────────┘     └────────┬─────────┘
                         │                        │
                         ▼                        ▼
                ┌──────────────────────────────────────────┐
                │         Render Grid Table (14 tiết x 7 ngày)│
                │  - occupied[day][period] tracking         │
                │  - rowSpan cho blocks dài                │
                └──────────────────────────────────────────┘
                         │
                         ▼
                ┌──────────────────┐
                │ Click block      │
                │ → Modal sửa     │
                │ buổi đơn         │
                └──────────────────┘
```

---

### 4. Luồng Điểm danh (BCS)

```
┌─────────┐     ┌──────────────────┐     ┌──────────────────┐
│   BCS   │────▶│ bcs/attendance.php│────▶│ loadSession()    │
│         │     │                  │     │ (Load buổi học)  │
└─────────┘     └────────┬─────────┘     └────────┬─────────┘
                         │                        │
                         ▼                        ▼
                ┌──────────────────┐     ┌──────────────────┐
                │ Chọn lớp học     │     │ fetch students   │
                │ phần & nhóm      │     │ in group         │
                └────────┬─────────┘     └────────┬─────────┘
                         │                        │
                         ▼                        ▼
                ┌──────────────────┐     ┌──────────────────┐
                │ Hiển thị danh    │     │ Render table:    │
                │ sách sinh viên   │     │ [ ] Vắng         │
                │ với checkbox     │     │ [x] Có mặt      │
                └────────┬─────────┘     │ [?] Chưa điểm   │
                         │               └──────────────────┘
                         ▼
                ┌──────────────────┐     ┌──────────────────┐
                │ Submit điểm danh│────▶│ attendanceController│
                │ → Save records  │     │ (Process POST)   │
                └────────┬─────────┘     └────────┬─────────┘
                         │                        │
                         ▼                        ▼
                ┌──────────────────────────────────────────┐
                │           Database Tables                  │
                │  attendance_sessions → attendance_records │
                └──────────────────────────────────────────┘
```

**Trạng thái điểm danh:**
- `1` = Có mặt
- `2` = Muộn
- `3` = Vắng có phép
- `4` = Vắng không phép

---

### 5. Luồng Quản lý Tài khoản (Admin)

```
┌─────────┐     ┌──────────────────┐     ┌──────────────────┐
│  Admin  │────▶│ accounts.php     │────▶│ loadUsers()      │
│         │     │                  │     │ (AJAX)           │
└─────────┘     └────────┬─────────┘     └────────┬─────────┘
                         │                        │
                         ▼                        ▼
                ┌──────────────────┐     ┌──────────────────┐
                │ Click: Thêm/Sửa/│     │ fetch users      │
                │ Xóa tài khoản   │     │ with filters     │
                └────────┬─────────┘     └────────┬─────────┘
                         │                        │
           ┌─────────────┼─────────────┐          │
           ▼             ▼             ▼          ▼
    ┌──────────┐  ┌──────────┐  ┌──────────┐   ┌──────────────┐
    │  Create  │  │  Update  │  │  Delete  │   │ Render Modal │
    │  Modal   │  │  Modal   │  │  Confirm │   │ with Form    │
    └────┬─────┘  └────┬─────┘  └────┬─────┘   └──────────────┘
         │             │             │
         ▼             ▼             ▼
    ┌──────────────────────────────────────────┐
    │      accountController.php (POST)         │
    │  - password_hash() for new passwords     │
    │  - Validation & error handling           │
    │  - system_logs for audit trail           │
    └──────────────────────────────────────────┘
```

---

### 6. Luồng Phản hồi (Feedback)

```
┌─────────┐     ┌──────────────────┐     ┌──────────────────┐
│  BCS/   │────▶│ feedback.php     │────▶│ Form: Chọn loại, │
│ Student │     │                  │     │ mô tả vấn đề    │
└─────────┘     └────────┬─────────┘     └────────┬─────────┘
                         │                        │
                         ▼                        ▼
                ┌──────────────────┐     ┌──────────────────┐
                │ Submit feedback  │     │ Validate &       │
                │                  │     │ Sanitize input   │
                └────────┬─────────┘     └────────┬─────────┘
                         │                        │
                         ▼                        ▼
                ┌──────────────────────────────────────────┐
                │  feedbacks table                           │
                │  - student_id, type, description          │
                │  - status: Pending/Processing/Resolved    │
                └──────────────────────────────────────────┘
                         │
           ┌─────────────┴─────────────┐
           ▼                           ▼
    ┌──────────────┐           ┌──────────────┐
    │ BCS View     │           │ Teacher View │
    │ - Xem status │           │ - Nhận & xử  │
    │ - Reply      │           │   lý feedback│
    └──────────────┘           └──────────────┘
```

---

### 7. Luồng Xem Lịch học (Sinh viên)

```
┌─────────┐     ┌──────────────────┐     ┌──────────────────┐
│ Student │────▶│ schedule.php     │────▶│ PHP: Load data   │
│         │     │                  │     │ (semester, reg)  │
└─────────┘     └────────┬─────────┘     └────────┬─────────┘
                         │                        │
                         ▼                        ▼
                ┌──────────────────┐     ┌──────────────────┐
                │ Select semester  │     │ Query:           │
                │ from dropdown   │     │ student_subject  │
                └────────┬─────────┘     │ _registration    │
                         │              └────────┬─────────┘
                         │                       │
                         ▼                       ▼
                ┌──────────────────────────────────────────┐
                │   Render Calendar/Table View              │
                │   - Group sessions by week              │
                │   - Show: subject, room, time, teacher  │
                └──────────────────────────────────────────┘
```

---

### 8. Luồng Upload Tài liệu (Giảng viên)

```
┌─────────┐     ┌──────────────────┐     ┌──────────────────┐
│ Teacher │────▶│ documents.php   │────▶│ Select file      │
│         │     │                  │     │ (pdf, docx, xlsx)│
└─────────┘     └────────┬─────────┘     └────────┬─────────┘
                         │                        │
                         ▼                        ▼
                ┌──────────────────┐     ┌──────────────────┐
                │ Select subject   │     │ Validate file   │
                │ & description    │     │ (size, type)    │
                └────────┬─────────┘     └────────┬─────────┘
                         │                        │
                         ▼                        ▼
                ┌──────────────────────────────────────────┐
                │  Upload to: /public/uploads/documents/    │
                │  Save to: documents table               │
                │  - title, file_path, subject_id         │
                │  - uploaded_by, created_at              │
                └──────────────────────────────────────────┘
                         │
                         ▼
                ┌──────────────────┐
                │ Notify students  │
                │ (notifications)  │
                └──────────────────┘
```

---

### Tổng quan Database Relationships

```
┌──────────────┐     ┌──────────────────┐     ┌──────────────────┐
│   users      │────▶│ class_students   │◀────│     classes      │
│ (id, role)   │     │ (student_id,     │     │ (id, class_name)│
└──────────────┘     │  class_id)       │     └────────┬─────────┘
                     └────────┬─────────┘              │
                              │                       │
                              ▼                       ▼
                     ┌──────────────────┐     ┌──────────────────┐
                     │class_subjects    │────▶│   subjects        │
                     │(cs_id, teacher,  │     │ (id, code, name) │
                     │ semester_id)     │     └──────────────────┘
                     └────────┬─────────┘
                              │
                              ▼
                     ┌──────────────────┐     ┌──────────────────┐
                     │class_subject     │────▶│ class_sessions   │
                     │_groups          │     │ (session detail) │
                     │(group, room,   │     └────────┬─────────┘
                     │ day, period)    │              │
                     └────────┬─────────┘              │
                              │                       │
                              ▼                       ▼
                     ┌──────────────────────────────────────────┐
                     │    attendance_sessions + attendance_records │
                     └──────────────────────────────────────────┘
```

---

### Sơ đồ hoạt động tổng - Admin

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                           ADMIN WORKFLOW                                      │
│                                                                               │
│  ┌─────────┐                                                                 │
│  │  LOGIN  │                                                                 │
│  └────┬────┘                                                                 │
│       │                                                                      │
│       ▼                                                                      │
│  ┌─────────────────────────────────────────────────────────────────────┐      │
│  │                        DASHBOARD (home.php)                          │      │
│  │  ┌───────────┐  ┌───────────┐  ┌───────────┐  ┌───────────┐       │      │
│  │  │  Students │  │ Teachers  │  │  Classes  │  │ Open CS   │       │      │
│  │  │   Count   │  │   Count   │  │   Count   │  │   Count   │       │      │
│  │  └───────────┘  └───────────┘  └───────────┘  └───────────┘       │      │
│  └─────────────────────────────────────────────────────────────────────┘      │
│       │                                                                      │
│       ├──────────────────────────────────────────────────────────┐            │
│       │                                                          │            │
│       ▼                                                          ▼            │
│  ┌──────────────────────┐                              ┌──────────────────────┐│
│  │  QUẢN LÝ TÀI KHOẢN  │                              │  QUẢN LÝ LỚP & MÔN ││
│  │  (accounts.php)       │                              │  (classes-subjects)  ││
│  │                      │                              │                     ││
│  │  ┌────────────────┐  │                              │  ┌───────────────┐  ││
│  │  │ • Thêm tài khoản│  │                              │  │ • Thêm môn    │  ││
│  │  │ • Sửa thông tin │  │                              │  │ • Thêm lớp HP │  ││
│  │  │ • Reset password│  │                              │  │ • Cấu hình HK │  ││
│  │  │ • Phân quyền    │  │                              │  │ • Import SV   │  ││
│  │  │ • Khóa/Mở TK   │  │                              │  └───────────────┘  ││
│  │  └───────┬────────┘  │                              └──────────┬───────────┘│
│  └──────────┼───────────┘                                         │           │
│             │                                                     │           │
│             ▼                                                     │           │
│  ┌────────────────────────────────────────────────────────────────────────┐  │
│  │                 PHÂN CÔNG GIẢNG DẠY (assignments.php)                │  │
│  │                                                                        │  │
│  │   Tab 1: Card View              Tab 2: Master Schedule               │  │
│  │  ┌─────────────────┐           ┌─────────────────────────────────┐  │  │
│  │  │ Subject Card 1  │           │     TUẦN    │ T2 │ T3 │...│ CN │  │  │
│  │  │ ├─ Class A     │           │  Tiết 1    │    │    │   │    │  │  │
│  │  │ ├─ Class B     │           │  Tiết 2    │ ▓▓ │    │   │    │  │  │
│  │  │ └─ [Thêm nhóm] │           │  ...       │    │▓▓▓│   │    │  │  │
│  │  └─────────────────┘           └─────────────────────────────────┘  │  │
│  │                                                                        │  │
│  │  Modal Flow:                                                          │  │
│  │  Chọn môn → Thêm nhóm → Form(lớp,GV,thứ,tiết,phòng) → Save → Log   │  │
│  └────────────────────────────────────────────────────────────────────────┘  │
│       │                                                                      │
│       ├──────────────────────────────────────────────────────────┐            │
│       │                                                          │            │
│       ▼                                                          ▼            │
│  ┌──────────────────────┐                              ┌──────────────────────┐│
│  │  CẤU HÌNH HỌC VỤ    │                              │  NHẬT KÝ HỆ THỐNG  ││
│  │  (org-settings.php)   │                              │  (system-logs.php)   ││
│  │                      │                              │                      ││
│  │  • Khoa/Ngành        │                              │  • Xem lịch sử      ││
│  │  • Phòng học         │                              │  • Filter theo user ││
│  │  • Năm học/HK        │                              │  • Filter theo act  ││
│  └──────────────────────┘                              └──────────────────────┘│
│                                                                               │
│  ┌────────────────────────────────────────────────────────────────────────┐  │
│  │                      COMMON ACTIONS                                     │  │
│  │  • Search/Filter bất kỳ trang nào                                      │  │
│  │  • Export dữ liệu (Excel/PDF)                                          │  │
│  │  • Xem thông báo hệ thống                                              │  │
│  │  • Cập nhật hồ sơ cá nhân                                              │  │
│  └────────────────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────────────┘
```

**Các thao tác chính của Admin:**

| Module | Tạo mới | Chỉnh sửa | Xóa | Import | Export |
|--------|---------|-----------|-----|--------|--------|
| Tài khoản | ✅ | ✅ | ✅ | ✅ | ✅ |
| Lớp học | ✅ | ✅ | ✅ | - | ✅ |
| Môn học | ✅ | ✅ | ✅ | - | ✅ |
| Lớp HP | ✅ | ✅ | ✅ | ✅ (SV) | ✅ |
| Phân công | ✅ | ✅ | ✅ | - | ✅ |
| Phòng/Khoa | ✅ | ✅ | ✅ | - | - |

---

### Sơ đồ hoạt động tổng - BCS (Ban Cán Sự)

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                           BCS WORKFLOW                                        │
│                                                                               │
│  ┌─────────┐                                                                 │
│  │  LOGIN  │                                                                 │
│  └────┬────┘                                                                 │
│       │                                                                      │
│       ▼                                                                      │
│  ┌─────────────────────────────────────────────────────────────────────┐      │
│  │                        DASHBOARD (bcs/home.php)                        │      │
│  │  ┌───────────┐  ┌───────────┐  ┌───────────┐  ┌───────────┐           │      │
│  │  │ Lớp: 25TH01│  │ Sĩ số: 45 │  │ Vắng HT: 2 │  │ Chờ duyệt: 3│          │      │
│  │  └───────────┘  └───────────┘  └───────────┘  └───────────┘           │      │
│  │                                                                        │      │
│  │  Thông tin lớp:                                                        │      │
│  │  • Thời khóa biểu hôm nay                                             │      │
│  │  • Thông báo mới                                                      │      │
│  │  • Cảnh báo vắng nhiều                                               │      │
│  └─────────────────────────────────────────────────────────────────────┘      │
│       │                                                                      │
│       ├──────────────────────────────┬──────────────────────────────────┐    │
│       │                              │                                  │    │
│       ▼                              ▼                                  ▼    │
│  ┌──────────────────────┐  ┌──────────────────────┐  ┌──────────────────────┐│
│  │      ĐIỂM DANH        │  │      PHẢN HỒI        │  │    TÀI LIỆU         ││
│  │  (attendance.php)     │  │  (feedback.php)     │  │  (documents.php)    ││
│  │                      │  │                      │  │                     ││
│  │  1. Chọn môn học     │  │  1. Chọn loại phản  │  │  • Danh sách tài liệu││
│  │     & nhóm            │  │     hồi              │  │  • Lọc theo môn    ││
│  │                      │  │                      │  │  • Tải về          ││
│  │  2. Load danh sách   │  │  2. Mô tả vấn đề    │  │                     ││
│  │     sinh viên         │  │                      │  │                     ││
│  │     ┌─────────────┐  │  │  3. Đính kèm (nếu) │  │                     ││
│  │     │ □ Nguyễn A │  │  │                      │  │                     ││
│  │     │ ☑ Trần B   │  │  │  4. Gửi phản hồi    │  │                     ││
│  │     │ □ Lê C     │  │  │     ↓               │  │                     ││
│  │     │ □ ...      │  │  │  5. Theo dõi trạng  │  │                     ││
│  │     └─────────────┘  │  │     thái             │  │                     ││
│  │                      │  │     ┌─────────────┐  │  │                     ││
│  │  3. Submit điểm     │  │     │ Pending     │  │  │                     ││
│  │     danh             │  │     │ Processing  │  │  │                     ││
│  │     ↓                │  │     │ Resolved ✓  │  │  │                     ││
│  │  4. Xác nhận        │  │     └─────────────┘  │  │                     ││
│  │     & gửi           │  │                      │  │                     ││
│  └──────────────────────┘  └──────────────────────┘  └──────────────────────┘│
│       │                              │                                  │    │
│       │                              │                                  │    │
│       ▼                              │                                  │    │
│  ┌──────────────────────┐            │                                  │    │
│  │    THÔNG BÁO         │            │                                  │    │
│  │  (announcements.php) │            │                                  │    │
│  │                      │            │                                  │    │
│  │  • Danh sách thông  │            │                                  │    │
│  │    báo từ GV        │            │                                  │    │
│  │                      │            │                                  │    │
│  │  • Xem chi tiết      │            │                                  │    │
│  │  • Đánh dấu đã đọc  │            │                                  │    │
│  └──────────────────────┘            │                                  │    │
│                                      │                                  │    │
│                                      ▼                                  │    │
│  ┌──────────────────────────────────────────────────────────────────┐ │    │
│  │                      PROFILE (profile.php)                         │ │    │
│  │  • Xem thông tin cá nhân                                         │ │    │
│  │  • Xem chức vụ (Lớp trưởng/Thư ký/BTV)                         │ │    │
│  │  • Đổi mật khẩu                                                 │ │    │
│  └──────────────────────────────────────────────────────────────────┘ │    │
│                                                                               │
│  ┌────────────────────────────────────────────────────────────────────────┐  │
│  │                      COMMON ACTIONS                                     │  │
│  │  • Xem thông báo mới (badge count)                                   │  │
│  │  • Xem lịch học tuần này                                            │  │
│  │  • Kiểm tra cảnh báo vắng                                           │  │
│  └────────────────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────────────┘
```

**Luồng điểm danh chi tiết của BCS:**

```
    Start
      │
      ▼
┌─────────────┐
│ Chọn Môn học │
│ (dropdown)  │
└──────┬──────┘
       │
       ▼
┌─────────────┐
│ Chọn Nhóm   │
│ (N1, N2...) │
└──────┬──────┘
       │
       ▼
┌─────────────────────────────────────────┐
│ Load danh sách sinh viên                │
│ Từ: class_students + class_subject_reg │
└─────────────────────┬───────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────┐
│ Render table:                           │
│ ┌──────┬──────────────────┬──────────┐  │
│ │ #    │ Họ tên          │ Trạng thái│ │
│ ├──────┼──────────────────┼──────────┤  │
│ │ 1    │ □ Nguyễn Văn A  │ ○ ○ ○ ○ │  │
│ │ 2    │ ☑ Trần Thị B   │ ● ○ ○ ○ │  │
│ │ 3    │ □ Lê Văn C     │ ○ ○ ○ ○ │  │
│ └──────┴──────────────────┴──────────┘  │
│                                         │
│ (● Có mặt, ○ Vắng)                    │
└─────────────────────┬───────────────────┘
                      │
                      ▼
         ┌─────────────────────────┐
         │ Đánh dấu từng sinh viên │
         │ Click checkbox          │
         └────────────┬────────────┘
                      │
                      ▼
┌─────────────────────────────────────────┐
│ Submit "Nộp phiếu điểm danh"           │
└─────────────────────┬───────────────────┘
                      │
                      ▼
         ┌─────────────────────────┐
         │ Validation:             │
         │ • Ít nhất 1 sinh viên │
         │ • Đã đánh dấu hết?    │
         └────────────┬────────────┘
                      │
          ┌───────────┴───────────┐
          │                       │
          ▼                       ▼
   ┌────────────┐          ┌────────────┐
   │  Invalid   │          │   Valid    │
   │ Alert user │          │ Save to DB │
   └──────┬─────┘          └──────┬─────┘
          │                       │
          └───────┬───────────────┘
                  │
                  ▼
         ┌─────────────────────────┐
         │ attendance_sessions     │
         │ + attendance_records   │
         │ (status: 1=Có, 3=Vắng)│
         └────────────┬────────────┘
                      │
                      ▼
         ┌─────────────────────────┐
         │ Success Message          │
         │ "Điểm danh thành công!" │
         └─────────────────────────┘
                      │
                      ▼
                   End
```

**Các quyền hạn của BCS:**

| Chức năng | Quyền | Ghi chú |
|-----------|-------|---------|
| Xem dashboard | ✅ | Thông tin lớp, sĩ số |
| Điểm danh | ✅ | Chỉ lớp mình thuộc về |
| Gửi phản hồi | ✅ | Tạo mới, xem trạng thái |
| Xem thông báo | ✅ | Từ giảng viên |
| Xem tài liệu | ✅ | Tải về |
| Quản lý tài khoản | ❌ | Không có quyền |
| Phân công GV | ❌ | Không có quyền |

## Troubleshooting

### Lỗi kết nối database

Kiểm tra:
- MySQL service đang chạy
- Thông tin `.env` chính xác
- Database đã được import

### Lỗi permission

```bash
chmod 755 -R cms/
chmod 644 .env
```

### Lỗi session

Đảm bảo thư mục session có quyền ghi:

```php
session_save_path(__DIR__ . '/sessions');
```

## License

Internal use only - Trường Đại học Bình Dương (BDU)
