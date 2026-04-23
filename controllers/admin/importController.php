<?php
/**
 * CMS BDU - Import Controller
 * Xử lý import dữ liệu từ file CSV/XLSX/XLS cho admin.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/helpers.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../views/admin/home.php');
}

function redirectImportResult(string $page, int $ok, int $skip, int $err, string $code = ''): void {
    $query = 'import_done=1&import_ok=' . $ok . '&import_skip=' . $skip . '&import_err=' . $err;
    if ($code !== '') {
        $query .= '&import_code=' . urlencode($code);
    }
    redirect('../../views/admin/' . $page . '?' . $query);
}

function normalizeDateValue(string $raw): ?string {
    $value = trim($raw);
    if ($value === '') {
        return null;
    }

    // Excel serial date (ví dụ: 45292)
    if (preg_match('/^\d+(\.\d+)?$/', $value)) {
        $serial = (float) $value;
        if ($serial > 59) {
            $serial -= 1;
        }
        $timestamp = (int) round(($serial - 25569) * 86400);
        if ($timestamp > 0) {
            return gmdate('Y-m-d', $timestamp);
        }
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }

    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches)) {
        $d = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $m = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        return $matches[3] . '-' . $m . '-' . $d;
    }

    return null;
}

function columnLettersToIndex(string $letters): int {
    $letters = strtoupper($letters);
    $len = strlen($letters);
    $index = 0;
    for ($i = 0; $i < $len; $i++) {
        $index = $index * 26 + (ord($letters[$i]) - 64);
    }
    return $index - 1;
}

function parseXlsxRows(string $tmpPath): ?array {
    if (!class_exists('ZipArchive')) {
        return null;
    }

    $zip = new ZipArchive();
    if ($zip->open($tmpPath) !== true) {
        return null;
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        $zip->close();
        return null;
    }

    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $sharedObj = @simplexml_load_string($sharedXml);
        if ($sharedObj !== false && isset($sharedObj->si)) {
            foreach ($sharedObj->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = trim((string) $si->t);
                    continue;
                }
                $text = '';
                if (isset($si->r)) {
                    foreach ($si->r as $run) {
                        $text .= (string) ($run->t ?? '');
                    }
                }
                $sharedStrings[] = trim($text);
            }
        }
    }

    $zip->close();

    $sheet = @simplexml_load_string($sheetXml);
    if ($sheet === false) {
        return null;
    }

    $rows = [];
    if (!isset($sheet->sheetData) || !isset($sheet->sheetData->row)) {
        return $rows;
    }

    foreach ($sheet->sheetData->row as $rowNode) {
        $row = [];
        foreach ($rowNode->c as $cell) {
            $ref = (string) ($cell['r'] ?? '');
            $colLetters = preg_replace('/\d+/', '', $ref);
            $colIndex = $colLetters !== '' ? columnLettersToIndex($colLetters) : count($row);
            $type = (string) ($cell['t'] ?? '');

            $value = '';
            if ($type === 's') {
                $si = (int) ($cell->v ?? -1);
                $value = $sharedStrings[$si] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = trim((string) ($cell->is->t ?? ''));
            } else {
                $value = trim((string) ($cell->v ?? ''));
            }

            $row[$colIndex] = $value;
        }

        if (count($row) === 0) {
            continue;
        }

        ksort($row);
        $normalized = [];
        $max = max(array_keys($row));
        for ($i = 0; $i <= $max; $i++) {
            $cellValue = (string) ($row[$i] ?? '');
            if ($i === 0) {
                $cellValue = preg_replace('/^\xEF\xBB\xBF/', '', $cellValue);
            }
            $normalized[] = trim($cellValue);
        }
        $rows[] = $normalized;
    }

    return $rows;
}

function parseSpreadsheetXmlRows(string $content): ?array {
    if (stripos($content, 'urn:schemas-microsoft-com:office:spreadsheet') === false) {
        return null;
    }

    $xml = @simplexml_load_string($content);
    if ($xml === false) {
        return null;
    }

    $namespaces = $xml->getNamespaces(true);
    $ssNs = $namespaces['ss'] ?? null;
    if ($ssNs) {
        $xml->registerXPathNamespace('ss', $ssNs);
    }

    $rows = [];
    $rowNodes = $xml->xpath('//ss:Worksheet[1]/ss:Table/ss:Row');
    if (!is_array($rowNodes)) {
        return [];
    }

    foreach ($rowNodes as $rowNode) {
        $row = [];
        $cells = $rowNode->xpath('./ss:Cell');
        if (!is_array($cells)) {
            continue;
        }

        foreach ($cells as $cellNode) {
            $attrs = $cellNode->attributes($ssNs, true);
            $index = isset($attrs['Index']) ? ((int) $attrs['Index']) - 1 : count($row);
            $dataNode = $cellNode->xpath('./ss:Data');
            $value = '';
            if (is_array($dataNode) && isset($dataNode[0])) {
                $value = trim((string) $dataNode[0]);
            }
            $row[$index] = $value;
        }

        if (count($row) === 0) {
            continue;
        }

        ksort($row);
        $normalized = [];
        $max = max(array_keys($row));
        for ($i = 0; $i <= $max; $i++) {
            $cellValue = (string) ($row[$i] ?? '');
            if ($i === 0) {
                $cellValue = preg_replace('/^\xEF\xBB\xBF/', '', $cellValue);
            }
            $normalized[] = trim($cellValue);
        }
        $rows[] = $normalized;
    }

    return $rows;
}

function parseCsvLikeRowsFromString(string $content): array {
    $lines = preg_split('/\r\n|\r|\n/', $content);
    $rows = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $delimiter = ',';
        if (substr_count($line, ';') > substr_count($line, ',')) {
            $delimiter = ';';
        }
        if (substr_count($line, "\t") > max(substr_count($line, ';'), substr_count($line, ','))) {
            $delimiter = "\t";
        }

        $row = str_getcsv($line, $delimiter);
        $row = array_map(static fn($v) => trim((string) $v), $row);
        if (isset($row[0])) {
            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
        }
        $rows[] = $row;
    }

    return $rows;
}

function loadRowsFromUpload(array $fileInfo, ?string &$errorCode = null): ?array {
    if (($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errorCode = 'upload_error';
        return null;
    }

    $name = strtolower((string) ($fileInfo['name'] ?? ''));
    $tmp = (string) ($fileInfo['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        $errorCode = 'upload_tmp_missing';
        return null;
    }

    $ext = pathinfo($name, PATHINFO_EXTENSION);
    if (!in_array($ext, ['csv', 'xlsx', 'xls'], true)) {
        $errorCode = 'invalid_extension';
        return null;
    }

    if ($ext === 'csv') {
        $rows = [];
        $handle = fopen($tmp, 'r');
        if ($handle === false) {
            $errorCode = 'cannot_open_file';
            return null;
        }
        while (($line = fgetcsv($handle, 0, ',')) !== false) {
            if (count($line) <= 1) {
                $raw = implode('', $line);
                $parts = str_getcsv($raw, ';');
                if (count($parts) > 1) {
                    $line = $parts;
                }
            }
            $line = array_map(static fn($v) => trim((string) $v), $line);
            if (isset($line[0])) {
                $line[0] = preg_replace('/^\xEF\xBB\xBF/', '', $line[0]);
            }
            if (count(array_filter($line, static fn($v) => $v !== '')) === 0) {
                continue;
            }
            $rows[] = $line;
        }
        fclose($handle);
        return $rows;
    }

    if ($ext === 'xlsx') {
        $rows = parseXlsxRows($tmp);
        if ($rows === null) {
            $errorCode = 'xlsx_parse_failed';
            return null;
        }
        return $rows;
    }

    // XLS: ưu tiên SpreadsheetML XML, fallback cho dạng text/csv giả lập.
    $content = @file_get_contents($tmp);
    if ($content === false) {
        $errorCode = 'cannot_read_xls';
        return null;
    }

    $xmlRows = parseSpreadsheetXmlRows($content);
    if ($xmlRows !== null) {
        return $xmlRows;
    }

    $oleHeader = substr($content, 0, 8);
    if ($oleHeader === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
        $errorCode = 'xls_binary_unsupported';
        return null;
    }

    return parseCsvLikeRowsFromString($content);
}

function maybeDropHeaderRow(array $rows): array {
    if (count($rows) === 0) {
        return $rows;
    }

    $first = $rows[0];
    $joined = strtolower(implode(' ', $first));
    $isHeader = strpos($joined, 'mssv') !== false
        || strpos($joined, 'mã') !== false
        || strpos($joined, 'email') !== false
        || strpos($joined, 'role') !== false
        || strpos($joined, 'vai trò') !== false
        || strpos($joined, 'class') !== false
        || strpos($joined, 'niên khóa') !== false;

    if ($isHeader) {
        array_shift($rows);
    }

    return $rows;
}

function usersHasBirthDateColumn(): bool {
    $row = db_fetch_one("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'birth_date'");
    return ((int) ($row['total'] ?? 0)) > 0;
}

$action = $_POST['action'] ?? '';
$file = $_FILES['import_file'] ?? null;

if (!$file || !in_array($action, ['import_classes', 'import_students', 'import_accounts'], true)) {
    redirect('../../views/admin/home.php');
}

$loadError = null;
$rows = loadRowsFromUpload($file, $loadError);
if ($rows === null) {
    $targetPage = $action === 'import_accounts' ? 'accounts.php' : 'classes-subjects.php';
    redirectImportResult($targetPage, 0, 0, 1, $loadError ?: 'invalid_file');
}

$rows = maybeDropHeaderRow($rows);

$ok = 0;
$skip = 0;
$err = 0;

try {
    if ($action === 'import_classes') {
        foreach ($rows as $row) {
            if (count($row) < 2) {
                $skip++;
                continue;
            }

            $className = strtoupper(trim((string) ($row[0] ?? '')));
            $academicYear = trim((string) ($row[1] ?? ''));

            // Remove BOM if present
            $className = preg_replace('/^\xEF\xBB\xBF/', '', $className);
            $academicYear = preg_replace('/^\xEF\xBB\xBF/', '', $academicYear);

            if ($className === '' || $academicYear === '') {
                $skip++;
                continue;
            }

            // Validate class name format
            if (!preg_match('/^[A-Z0-9]+$/i', str_replace([' ', '-', '_'], '', $className))) {
                $skip++;
                continue;
            }

            try {
                $existing = db_fetch_one('SELECT id FROM classes WHERE class_name = ? LIMIT 1', [$className]);
                if ($existing) {
                    db_query('UPDATE classes SET academic_year = ? WHERE id = ?', [$academicYear, (int) $existing['id']]);
                } else {
                    db_query('INSERT INTO classes (class_name, academic_year) VALUES (?, ?)', [$className, $academicYear]);
                }
                $ok++;
            } catch (Exception $e) {
                $err++;
            }
        }

        logSystem("Import lớp học: $ok thành công, $skip bỏ qua, $err lỗi", 'classes', null);
        redirectImportResult('classes-subjects.php', $ok, $skip, $err);
    }

    if ($action === 'import_students') {
        $className = trim($_POST['class_name'] ?? '');
        if ($className === '') {
            redirectImportResult('classes-subjects.php', 0, 0, 1, 'missing_class');
        }

        $class = db_fetch_one('SELECT id FROM classes WHERE class_name = ? LIMIT 1', [$className]);
        if (!$class) {
            redirectImportResult('classes-subjects.php', 0, 0, 1, 'class_not_found');
        }

        $classId = (int) $class['id'];
        $hasBirthDate = usersHasBirthDateColumn();
        $defaultPasswordHash = password_hash('Bdu@123456', PASSWORD_DEFAULT);

        foreach ($rows as $row) {
            if (count($row) < 5) {
                $skip++;
                continue;
            }

            $username = trim($row[1] ?? '');
            $fullName = trim($row[2] ?? '');
            $birthDate = normalizeDateValue((string) ($row[3] ?? ''));
            $email = trim($row[4] ?? '');

            if ($username === '' || $fullName === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skip++;
                continue;
            }

            try {
                $user = db_fetch_one('SELECT id, role FROM users WHERE username = ? OR email = ? LIMIT 1', [$username, $email]);
                $userId = 0;

                if ($user) {
                    $userId = (int) $user['id'];
                    if ($hasBirthDate) {
                        db_query('UPDATE users SET full_name = ?, email = ?, role = ?, birth_date = ? WHERE id = ?', [$fullName, $email, 'student', $birthDate, $userId]);
                    } else {
                        db_query('UPDATE users SET full_name = ?, email = ?, role = ? WHERE id = ?', [$fullName, $email, 'student', $userId]);
                    }
                } else {
                    if ($hasBirthDate) {
                        db_query('INSERT INTO users (username, password, full_name, email, role, birth_date) VALUES (?, ?, ?, ?, ?, ?)', [$username, $defaultPasswordHash, $fullName, $email, 'student', $birthDate]);
                    } else {
                        db_query('INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)', [$username, $defaultPasswordHash, $fullName, $email, 'student']);
                    }
                    $created = db_fetch_one('SELECT id FROM users WHERE username = ? LIMIT 1', [$username]);
                    $userId = (int) ($created['id'] ?? 0);
                }

                if ($userId > 0) {
                    db_query('DELETE FROM class_students WHERE student_id = ?', [$userId]);
                    db_query('INSERT INTO class_students (class_id, student_id) VALUES (?, ?)', [$classId, $userId]);
                    $ok++;
                } else {
                    $err++;
                }
            } catch (Exception $e) {
                $err++;
            }
        }

        logSystem("Import sinh viên vào lớp $className: $ok thành công, $skip bỏ qua, $err lỗi", 'class_students', null);
        redirectImportResult('classes-subjects.php', $ok, $skip, $err);
    }

    if ($action === 'import_accounts') {
        $hasBirthDate = usersHasBirthDateColumn();
        $defaultPasswordHash = password_hash('Bdu@123456', PASSWORD_DEFAULT);
        $validRoles = ['admin', 'support_admin', 'teacher', 'bcs', 'student'];


        foreach ($rows as $row) {
            if (count($row) < 8) { // cần ít nhất 8 cột nếu có academic_title
                $skip++;
                continue;
            }

            $username = trim($row[1] ?? '');
            $fullName = trim($row[2] ?? '');
            $birthDate = normalizeDateValue((string) ($row[3] ?? ''));
            $className = trim($row[4] ?? '');
            $email = trim($row[5] ?? '');
            $role = strtolower(trim($row[6] ?? ''));
            if ($role === 'staff') {
                $role = 'support_admin';
            }
            $academicTitle = trim($row[7] ?? '');

            if ($username === '' || $fullName === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, $validRoles, true)) {
                $skip++;
                continue;
            }

            $classId = null;
            if ($role === 'student' || $role === 'bcs') {
                if ($className === '') {
                    $skip++;
                    continue;
                }
                $class = db_fetch_one('SELECT id FROM classes WHERE class_name = ? LIMIT 1', [$className]);
                if (!$class) {
                    $skip++;
                    continue;
                }
                $classId = (int) $class['id'];
            }

            try {
                $user = db_fetch_one('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1', [$username, $email]);
                $userId = 0;

                if ($user) {
                    $userId = (int) $user['id'];
                    if ($hasBirthDate) {
                        db_query('UPDATE users SET full_name = ?, email = ?, role = ?, birth_date = ?, academic_title = ? WHERE id = ?', [$fullName, $email, $role, $birthDate, $academicTitle, $userId]);
                    } else {
                        db_query('UPDATE users SET full_name = ?, email = ?, role = ?, academic_title = ? WHERE id = ?', [$fullName, $email, $role, $academicTitle, $userId]);
                    }
                } else {
                    if ($hasBirthDate) {
                        db_query('INSERT INTO users (username, password, full_name, email, role, birth_date, academic_title) VALUES (?, ?, ?, ?, ?, ?, ?)', [$username, $defaultPasswordHash, $fullName, $email, $role, $birthDate, $academicTitle]);
                    } else {
                        db_query('INSERT INTO users (username, password, full_name, email, role, academic_title) VALUES (?, ?, ?, ?, ?, ?)', [$username, $defaultPasswordHash, $fullName, $email, $role, $academicTitle]);
                    }
                    $created = db_fetch_one('SELECT id FROM users WHERE username = ? LIMIT 1', [$username]);
                    $userId = (int) ($created['id'] ?? 0);
                }

                if ($userId <= 0) {
                    $err++;
                    continue;
                }

                db_query('DELETE FROM class_students WHERE student_id = ?', [$userId]);
                if (($role === 'student' || $role === 'bcs') && $classId) {
                    db_query('INSERT INTO class_students (class_id, student_id) VALUES (?, ?)', [$classId, $userId]);
                }

                $ok++;
            } catch (Exception $e) {
                $err++;
            }
        }

        logSystem("Import tài khoản: $ok thành công, $skip bỏ qua, $err lỗi", 'users', null);
        redirectImportResult('accounts.php', $ok, $skip, $err);
    }
} catch (Exception $e) {
    $targetPage = $action === 'import_accounts' ? 'accounts.php' : 'classes-subjects.php';
    redirectImportResult($targetPage, $ok, $skip, $err + 1, 'unexpected_error');
}
