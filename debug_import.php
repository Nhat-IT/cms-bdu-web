<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/helpers.php';

// Path to the import file
$xlsxPath = __DIR__ . '/.claude/worktrees/optimistic-neumann/DB_demo/Import Tài Khoản.xlsx';

// Find the actual file
$files = glob(__DIR__ . '/.claude/worktrees/optimistic-neumann/DB_demo/Import*.xlsx');
if (empty($files)) {
    echo "File not found\n";
    exit;
}
$xlsxPath = $files[0];
echo "Using file: $xlsxPath\n\n";

$rows = parseXlsxRows($xlsxPath);

if ($rows === null) {
    echo "Failed to parse XLSX\n";
    exit;
}

echo "Total rows (including header if any): " . count($rows) . "\n\n";

// Print all rows
foreach ($rows as $i => $row) {
    echo "Row " . ($i+1) . ": " . json_encode($row) . "\n";
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

    function columnLettersToIndex(string $letters): int {
        $letters = strtoupper($letters);
        $len = strlen($letters);
        $index = 0;
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }
        return $index - 1;
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
