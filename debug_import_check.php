<?php
/**
 * Debug script: simulates import_accounts logic with detailed output
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/helpers.php';

// Find import file
$files = glob(__DIR__ . '/.claude/worktrees/optimistic-neumann/DB_demo/Import*.xlsx');
if (empty($files)) {
    echo "ERROR: Import file not found\n";
    exit;
}
$xlsxPath = $files[0];
echo "Using file: $xlsxPath\n\n";

// --- Parse XLSX (same logic as importController.php) ---
function columnLettersToIndex(string $letters): int {
    $letters = strtoupper($letters);
    $len = strlen($letters);
    $index = 0;
    for ($i = 0; $i < $len; $i++) {
        $index = $index * 26 + (ord($letters[$i]) - 64);
    }
    return $index - 1;
}

function normalizeDateValue(string $raw): ?string {
    $value = trim($raw);
    if ($value === '') return null;
    if (preg_match('/^\d+(\.\d+)?$/', $value)) {
        $serial = (float) $value;
        if ($serial > 59) $serial -= 1;
        $timestamp = (int) round(($serial - 25569) * 86400);
        if ($timestamp > 0) return gmdate('Y-m-d', $timestamp);
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches)) {
        $d = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $m = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        return $matches[3] . '-' . $m . '-' . $d;
    }
    return null;
}

$zip = new ZipArchive();
if ($zip->open($xlsxPath) !== true) {
    echo "ERROR: Cannot open XLSX\n";
    exit;
}

$sharedStrings = [];
$sharedXml = $zip->getFromName('xl/sharedStrings.xml');
if ($sharedXml !== false) {
    $sharedObj = @simplexml_load_string($sharedXml);
    if ($sharedObj !== false && isset($sharedObj->si)) {
        foreach ($sharedObj->si as $si) {
            if (isset($si->t)) {
                $sharedStrings[] = trim((string) $si->t);
            } else {
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
}

$sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
$zip->close();

if ($sheetXml === false) {
    echo "ERROR: Cannot read sheet1.xml\n";
    exit;
}

$sheet = @simplexml_load_string($sheetXml);
if ($sheet === false) {
    echo "ERROR: Cannot parse sheet XML\n";
    exit;
}

$rows = [];
if (!isset($sheet->sheetData) || !isset($sheet->sheetData->row)) {
    echo "ERROR: No sheetData found\n";
    exit;
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
    if (count($row) === 0) continue;
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

// Drop header row
$first = $rows[0] ?? [];
$joined = strtolower(implode(' ', $first));
$isHeader = strpos($joined, 'mssv') !== false
    || strpos($joined, 'mã') !== false
    || strpos($joined, 'email') !== false
    || strpos($joined, 'role') !== false
    || strpos($joined, 'vai trò') !== false
    || strpos($joined, 'class') !== false
    || strpos($joined, 'niên khóa') !== false;

if ($isHeader) array_shift($rows);

echo "Total data rows after header drop: " . count($rows) . "\n\n";

// --- Check database state ---
$hasBirthDate = (function() {
    $row = db_fetch_one("SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'birth_date'");
    return ((int) ($row['total'] ?? 0)) > 0;
})();

echo "DB has birth_date column: " . ($hasBirthDate ? "YES" : "NO") . "\n";
echo "DB has classes: ";
$allClasses = db_fetch_all('SELECT id, class_name, academic_year FROM classes');
foreach ($allClasses as $c) {
    echo "\n  - " . $c['class_name'] . " (id=" . $c['id'] . ", year=" . $c['academic_year'] . ")";
}
echo "\n\n";

// --- Simulate import logic ---
$validRoles = ['admin', 'support_admin', 'teacher', 'bcs', 'student'];
$seenUsernames = [];
$ok = 0;
$skip = 0;
$err = 0;
$skipReasons = [];

foreach ($rows as $idx => $row) {
    $rowNum = $idx + 2; // +2 because header was row 1 and is 0-indexed
    $colCount = count($row);
    
    if ($colCount < 7) {
        $skip++;
        $skipReasons[] = "Row $rowNum: SKIP (colCount=$colCount < 7)";
        continue;
    }

    $username = trim($row[1] ?? '');
    $fullName = trim($row[2] ?? '');
    $birthDate = normalizeDateValue((string) ($row[3] ?? ''));
    $className = trim($row[4] ?? '');
    $email = trim($row[5] ?? '');
    $role = strtolower(trim($row[6] ?? ''));
    $academicTitle = trim($row[7] ?? '');
    
    if ($role === 'staff') $role = 'support_admin';

    // Check 1: required fields & valid role
    if ($username === '' || $fullName === '' || !in_array($role, $validRoles, true)) {
        $skip++;
        $skipReasons[] = "Row $rowNum ($username/$role): SKIP (username='$username', fullName='$fullName', role='$role', valid=" . (in_array($role, $validRoles, true) ? "YES" : "NO") . ")";
        continue;
    }

    // Check 2: email for student/bcs
    if ($role === 'student' || $role === 'bcs') {
        $expectedStudentEmail = strtolower($username) . '@student.bdu.edu.vn';
        if ($email === '' || preg_match('/@student\.bdu\.edu\.vn$/i', $email)) {
            $email = $expectedStudentEmail;
        }
    }

    // Check 3: email format
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $skip++;
        $skipReasons[] = "Row $rowNum ($username/$role): SKIP (email='$email' invalid)";
        continue;
    }

    // Check 4: duplicate in file
    $usernameKey = strtolower($username);
    if (isset($seenUsernames[$usernameKey])) {
        $skip++;
        $skipReasons[] = "Row $rowNum ($username/$role): SKIP (duplicate username in file)";
        continue;
    }
    $seenUsernames[$usernameKey] = true;

    // Check 5: class required for student/bcs
    $classId = null;
    if ($role === 'student' || $role === 'bcs') {
        if ($className === '') {
            $skip++;
            $skipReasons[] = "Row $rowNum ($username/$role): SKIP (className is empty)";
            continue;
        }
        $class = db_fetch_one('SELECT id FROM classes WHERE class_name = ? LIMIT 1', [$className]);
        if (!$class) {
            $skip++;
            $skipReasons[] = "Row $rowNum ($username/$role): SKIP (class '$className' NOT FOUND in DB)";
            continue;
        }
        $classId = (int) $class['id'];
    }

    // Check 6: username already exists
    $existingByUsername = db_fetch_one('SELECT id FROM users WHERE username = ? LIMIT 1', [$username]);
    if ($existingByUsername) {
        $skip++;
        $skipReasons[] = "Row $rowNum ($username/$role): SKIP (username ALREADY EXISTS in DB, id=" . $existingByUsername['id'] . ")";
        continue;
    }

    // Check 7: email already exists
    $existingByEmail = db_fetch_one('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);
    if ($existingByEmail) {
        $skip++;
        $skipReasons[] = "Row $rowNum ($username/$role): SKIP (email '$email' ALREADY EXISTS in DB, id=" . $existingByEmail['id'] . ")";
        continue;
    }

    // All checks passed
    $ok++;
}

echo "=== IMPORT RESULTS ===\n";
echo "OK: $ok, SKIP: $skip, ERR: $err\n\n";

if (!empty($skipReasons)) {
    echo "=== SKIP REASONS ===\n";
    foreach ($skipReasons as $reason) {
        echo "  $reason\n";
    }
}

echo "\n=== SAMPLE DATA (first 5 rows) ===\n";
foreach (array_slice($rows, 0, 5) as $idx => $row) {
    echo "Row " . ($idx+2) . ": " . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}
