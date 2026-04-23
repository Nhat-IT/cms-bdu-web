<?php
/**
 * CMS BDU - Ph├ón C├┤ng Giß║úng Dß║íy
 * Trang ─æiß╗üu phß╗æi lß╗ïch dß║íy cho Admin
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

// Bß║úo vß╗ç trang - chß╗ë admin ─æ╞░ß╗úc ph├⌐p truy cß║¡p
requireRole('admin');

// Lß║Ñy th├┤ng tin admin hiß╗çn tß║íi
$currentUser = getCurrentUser();

// Lß║Ñy danh s├ích lß╗¢p hß╗ìc phß║ºn vß╗¢i th├┤ng tin ─æß║ºy ─æß╗º
$stmtClassSubjects = $pdo->query("
    SELECT 
        cs.id,
        cs.semester,
        s.subject_code,
        s.subject_name,
        u.full_name as teacher_name,
        c.class_name,
        cs.start_date,
        cs.end_date,
        CASE 
            WHEN cs.start_date <= CURDATE() AND cs.end_date >= CURDATE() THEN 'open'
            ELSE 'closed'
        END as status
    FROM class_subjects cs
    LEFT JOIN subjects s ON cs.subject_id = s.id
    LEFT JOIN users u ON cs.teacher_id = u.id
    LEFT JOIN classes c ON cs.class_id = c.id
    ORDER BY cs.created_at DESC
");
$classSubjects = $stmtClassSubjects->fetchAll();

// Lß║Ñy danh s├ích giß║úng vi├¬n cho dropdown
$stmtTeachers = $pdo->query("SELECT id, username, full_name FROM users WHERE role = 'teacher' ORDER BY full_name");
$teachers = $stmtTeachers->fetchAll();

// Lß║Ñy danh s├ích m├┤n hß╗ìc cho dropdown
$stmtSubjects = $pdo->query("SELECT id, subject_code, subject_name FROM subjects ORDER BY subject_code");
$subjects = $stmtSubjects->fetchAll();

// Lß║Ñy danh s├ích lß╗¢p hß╗ìc cho dropdown
$stmtClasses = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name");
$classes = $stmtClasses->fetchAll();

// Lß║Ñy danh s├ích hß╗ìc kß╗│ cho dropdown
$stmtSemesters = $pdo->query("SELECT id, semester_name, academic_year, start_date, end_date FROM semesters ORDER BY academic_year DESC, semester_name");
$semesters = $stmtSemesters->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS BDU - ─Éiß╗üu Phß╗æi Lß╗ïch Dß║íy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/style.css">
    <link rel="stylesheet" href="../../public/css/layout.css">
    <link rel="stylesheet" href="../../public/css/admin/admin-layout.css">
    <link rel="stylesheet" href="../../public/css/admin/assignments.css">
</head>
<body class="dashboard-body">

<div class="sidebar sidebar-admin" id="sidebar">
    <div>
        <div class="brand-container flex-shrink-0">
            <a href="home.php" class="text-decoration-none text-primary d-flex align-items-center">
                <i class="bi bi-mortarboard-fill fs-2 me-2"></i>
                <span class="fs-4 fw-bold hide-on-collapse">CMS ADMIN</span>
            </a>
        </div>
        <div class="text-center mb-3 text-white-50 small fw-bold hide-on-collapse">QUß║óN TRß╗è Hß╗å THß╗ÉNG</div>
        <div class="sidebar-scrollable w-100">
        <nav class="d-flex flex-column mt-3">
            <a href="home.php"><i class="bi bi-speedometer2"></i> Tß╗òng quan hß╗ç thß╗æng</a>
            <a href="org-settings.php"><i class="bi bi-gear-wide-connected"></i> Cß║Ñu h├¼nh Hß╗ìc vß╗Ñ</a>
            <a href="accounts.php"><i class="bi bi-people"></i> Quß║ún l├╜ T├ái khoß║ún</a>
            <a href="classes-subjects.php"><i class="bi bi-building"></i> Quß║ún l├╜ Lß╗¢p & M├┤n</a>
            <a href="assignments.php" class="active"><i class="bi bi-diagram-3-fill"></i> Ph├ón c├┤ng Giß║úng dß║íy</a>
            <a href="system-logs.php"><i class="bi bi-shield-lock"></i> Nhß║¡t k├╜ hß╗ç thß╗æng</a>
        </nav>
        </div>
    </div>
    
    <div class="mt-auto mb-3 flex-shrink-0 pt-3 border-top border-light border-opacity-10">
        <a href="../logout.php" class="nav-link logout-btn" title="─É─âng xuß║Ñt">
            <i class="bi bi-box-arrow-left"></i> <span class="hide-on-collapse fw-bold">─É─âng xuß║Ñt</span>
        </a>
    </div>
</div>

<div class="main-content admin-main-content" id="mainContent">
    <div class="top-navbar-admin d-flex justify-content-between align-items-center px-4 py-3">
        <div class="d-flex align-items-center">
            <button class="btn btn-outline-light d-md-none me-3" id="sidebarToggle"><i class="bi bi-list fs-4"></i></button>
            <h4 class="m-0 text-white fw-bold d-flex align-items-center">
                <i class="bi bi-calendar-range me-2 fs-3 text-warning"></i> Hß╗å THß╗ÉNG ─ÉIß╗ÇU PHß╗ÉI Lß╗èCH Dß║áY
            </h4>
        </div>

        <div class="d-flex align-items-center text-white">
            <div class="text-end me-3 d-none d-sm-block border-end pe-3 border-light border-opacity-50">
                <div class="fs-6">Quß║ún trß╗ï vi├¬n: <span class="fw-bold admin-operator-name"><?php echo e($currentUser['full_name'] ?? 'Admin'); ?></span></div>
            </div>

            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-white text-decoration-none" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle fs-2"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow mt-2">
                    <li><a class="dropdown-item fw-bold" href="admin-profile.php"><i class="bi bi-person-vcard text-primary me-2"></i>Hß╗ô s╞í c├í nh├ón</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item fw-bold text-danger" href="../logout.php"><i class="bi bi-box-arrow-right text-danger me-2"></i>─É─âng xuß║Ñt</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="p-4">
        
        <ul class="nav nav-tabs mb-4 border-bottom" id="scheduleTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#listView" type="button" role="tab">
                    <i class="bi bi-list-ul me-2"></i>Xem Theo Lß╗¢p Hß╗ìc Phß║ºn
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="grid-tab" data-bs-toggle="tab" data-bs-target="#gridView" type="button" role="tab">
                    <i class="bi bi-grid-3x3-gap-fill me-2"></i>Thß╗¥i Kh├│a Biß╗âu Tß╗òng (Master)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="scheduleTabsContent">
            
            <!-- Tab Danh s├ích Lß╗¢p Hß╗ìc Phß║ºn -->
            <div class="tab-pane fade show active" id="listView" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold text-dark">DANH S├üCH C├üC Lß╗ÜP ─Éß╗é Xß║╛P Lß╗èCH</h6>
                        <div class="input-group input-group-sm admin-assignments-search">
                            <input type="text" class="form-control" placeholder="T├¼m t├¬n m├┤n, m├ú lß╗¢p..." id="searchClassSubject">
                            <button class="btn btn-primary"><i class="bi bi-search"></i></button>
                        </div>
                    </div>
                    <div class="card-body border-bottom bg-light-subtle py-3">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-muted mb-1">N─éM Hß╗îC</label>
                                <select class="form-select form-select-sm" id="assignFilterYear">
                                    <option value="all">Chß╗ìn tß║Ñt cß║ú</option>
                                    <?php foreach ($semesters as $sem): ?>
                                        <option value="<?php echo e($sem['academic_year']); ?>"><?php echo e($sem['academic_year']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-muted mb-1">Hß╗îC Kß╗▓</label>
                                <select class="form-select form-select-sm" id="assignFilterSemester">
                                    <option value="all">Chß╗ìn tß║Ñt cß║ú</option>
                                    <option value="1">Hß╗ìc kß╗│ 1</option>
                                    <option value="2">Hß╗ìc kß╗│ 2</option>
                                    <option value="3">Hß╗ìc kß╗│ 3 (H├¿)</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-muted mb-1">TRß║áNG TH├üI M├öN</label>
                                <select class="form-select form-select-sm" id="assignFilterOpenStatus">
                                    <option value="all">Tß║Ñt cß║ú</option>
                                    <option value="open">─Éang mß╗ƒ</option>
                                    <option value="closed">─É├ú ─æ├│ng</option>
                                </select>
                            </div>
                        </div>
                        <div class="small text-muted mt-2">
                            <i class="bi bi-info-circle me-1"></i>Thß╗¥i gian mß╗ƒ m├┤n ─æ╞░ß╗úc lß║Ñy tß╗½ cß║Ñu h├¼nh tß║íi trang Quß║ún l├╜ Lß╗¢p & M├┤n v├á chß╗ë hiß╗ân thß╗ï ─æß╗â tham chiß║┐u.
                        </div>
                    </div>
                    <div class="card-body p-0" id="assignmentOfferingContainer">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small text-muted fw-bold">
                                    <tr>
                                        <th class="ps-4 py-3">M├â Lß╗ÜP</th>
                                        <th class="py-3">M├öN Hß╗îC</th>
                                        <th class="py-3">Lß╗ÜP H├ÇNH CH├ìNH</th>
                                        <th class="py-3">GIß║óNG VI├èN</th>
                                        <th class="text-center py-3">NG├ÇY Bß║«T ─Éß║ªU</th>
                                        <th class="text-center py-3">NG├ÇY Kß║╛T TH├ÜC</th>
                                        <th class="text-center py-3">TRß║áNG TH├üI</th>
                                        <th class="pe-4 py-3 text-end">H├ÇNH ─Éß╗ÿNG</th>
                                    </tr>
                                </thead>
                                <tbody id="classSubjectListBody">
                                    <?php if (count($classSubjects) > 0): ?>
                                        <?php foreach ($classSubjects as $cs): ?>
                                            <tr>
                                                <td class="ps-4 fw-bold text-primary"><?php echo e($cs['subject_code'] . '-' . $cs['semester']); ?></td>
                                                <td><?php echo e($cs['subject_name']); ?></td>
                                                <td><?php echo e($cs['class_name'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php if ($cs['teacher_name']): ?>
                                                        <span class="badge bg-light text-dark border"><?php echo e($cs['teacher_name']); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Ch╞░a ph├ón c├┤ng</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center"><?php echo formatDate($cs['start_date']); ?></td>
                                                <td class="text-center"><?php echo formatDate($cs['end_date']); ?></td>
                                                <td class="text-center">
                                                    <?php if ($cs['status'] === 'open'): ?>
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success">─Éang mß╗ƒ</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">─É├ú ─æ├│ng</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="pe-4 text-end">
                                                    <button class="btn btn-sm btn-primary" title="Ph├ón c├┤ng giß║úng dß║íy">
                                                        <i class="bi bi-calendar-plus"></i> Xß║┐p lß╗ïch
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">Ch╞░a c├│ lß╗¢p hß╗ìc phß║ºn n├áo. Vui l├▓ng tß║ío lß╗¢p hß╗ìc phß║ºn tß║íi trang Quß║ún l├╜ Lß╗¢p & M├┤n.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Thß╗¥i Kh├│a Biß╗âu Tß╗òng -->
            <div class="tab-pane fade" id="gridView" role="tabpanel">
                <div class="row g-3 align-items-end mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">CHß╗îN Hß╗îC Kß╗▓</label>
                        <select class="form-select border-primary fw-bold text-primary shadow-sm">
                            <?php if (count($semesters) > 0): ?>
                                <?php foreach ($semesters as $sem): ?>
                                    <option value="<?php echo $sem['id']; ?>"><?php echo e($sem['semester_name']); ?> - <?php echo e($sem['academic_year']); ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option>2025 - 2026 (HK1)</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Lß╗îC THEO GIß║óNG VI├èN</label>
                        <select class="form-select border-secondary shadow-sm">
                            <option value="all">-- Tß║Ñt cß║ú Giß║úng vi├¬n --</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo e($teacher['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i> Chß╗⌐c n─âng Thß╗¥i Kh├│a Biß╗âu Tß╗òng (Master) ─æang ─æ╞░ß╗úc ph├ít triß╗ân. Vui l├▓ng sß╗¡ dß╗Ñng tab "Xem Theo Lß╗¢p Hß╗ìc Phß║ºn" ─æß╗â quß║ún l├╜.
                </div>

                <div class="schedule-wrapper shadow-sm">
                    <table class="table master-schedule-table mb-0">
                        <thead>
                            <tr>
                                <th style="width: 80px;"><i class="bi bi-arrow-left fs-4 week-nav-btn"></i></th>
                                <th>Thß╗⌐ 2</th>
                                <th>Thß╗⌐ 3</th>
                                <th>Thß╗⌐ 4</th>
                                <th>Thß╗⌐ 5</th>
                                <th>Thß╗⌐ 6</th>
                                <th>Thß╗⌐ 7</th>
                                <th>Chß╗º Nhß║¡t</th>
                                <th style="width: 80px;"><i class="bi bi-arrow-right fs-4 week-nav-btn"></i></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="edge-col">Tiß║┐t 1-5</td>
                                <td colspan="5" class="text-center text-muted">-- Trß╗æng --</td>
                                <td colspan="2"></td>
                                <td class="edge-col"></td>
                            </tr>
                            <tr>
                                <td class="edge-col">Tiß║┐t 6-10</td>
                                <td colspan="5" class="text-center text-muted">-- Trß╗æng --</td>
                                <td colspan="2"></td>
                                <td class="edge-col"></td>
                            </tr>
                            <tr>
                                <td class="edge-col">Tiß║┐t 11-14</td>
                                <td colspan="5" class="text-center text-muted">-- Trß╗æng --</td>
                                <td colspan="2"></td>
                                <td class="edge-col"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Modal Ph├ón c├┤ng Giß║úng dß║íy -->
<div class="modal fade" id="initialScheduleModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header text-white border-bottom-0 pb-3" id="initModalHeader">
                <h5 class="modal-title fw-bold" id="initModalTitle"><i class="bi bi-calendar-plus me-2"></i>Thiß║┐t Lß║¡p Lß╗ïch Giß║úng Dß║íy Mß╗¢i</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form onsubmit="return handleInitialScheduleSubmit(event)">
                <div class="modal-body p-4 bg-light">
                    <div class="alert alert-warning fw-bold small">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Chß╗⌐c n─âng ─æang ─æ╞░ß╗úc ph├ít triß╗ân.
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 bg-light">
                    <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">─É├│ng</button>
                </div>
            </form>
        </div>
    </div>
</div>

<input type="file" id="assignmentStudentUploadInput" class="d-none" accept=".csv,.xlsx,.xls">

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="../../public/js/script.js"></script>
<script src="../../public/js/admin/admin-layout.js"></script>

<script>
    // T├¼m kiß║┐m lß╗¢p hß╗ìc phß║ºn
    document.getElementById('searchClassSubject').addEventListener('keyup', function() {
        const searchValue = this.value.toLowerCase();
        const rows = document.querySelectorAll('#classSubjectListBody tr');
        
        rows.forEach(function(row) {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchValue) ? '' : 'none';
        });
    });

    // Filter theo hß╗ìc kß╗│
    document.getElementById('assignFilterYear').addEventListener('change', filterClassSubjects);
    document.getElementById('assignFilterSemester').addEventListener('change', filterClassSubjects);
    document.getElementById('assignFilterOpenStatus').addEventListener('change', filterClassSubjects);

    function filterClassSubjects() {
        const yearFilter = document.getElementById('assignFilterYear').value;
        const semesterFilter = document.getElementById('assignFilterSemester').value;
        const statusFilter = document.getElementById('assignFilterOpenStatus').value;
        
        const rows = document.querySelectorAll('#classSubjectListBody tr');
        
        rows.forEach(function(row) {
            let show = true;
            
            if (yearFilter !== 'all' && !row.textContent.includes(yearFilter)) {
                show = false;
            }
            
            if (semesterFilter !== 'all') {
                const semCol = row.querySelector('td:nth-child(1)');
                if (semCol && !semCol.textContent.includes(semesterFilter)) {
                    show = false;
                }
            }
            
            if (statusFilter !== 'all') {
                const statusCol = row.querySelector('td:nth-child(7)');
                if (statusCol) {
                    if (statusFilter === 'open' && !statusCol.textContent.includes('─Éang mß╗ƒ')) {
                        show = false;
                    }
                    if (statusFilter === 'closed' && !statusCol.textContent.includes('─É├ú ─æ├│ng')) {
                        show = false;
                    }
                }
            }
            
            row.style.display = show ? '' : 'none';
        });
    }

    function handleInitialScheduleSubmit(event) {
        event.preventDefault();
        alert('Chß╗⌐c n─âng ─æang ─æ╞░ß╗úc ph├ít triß╗ân!');
        return false;
    }
</script>
</body>
</html>
