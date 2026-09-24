<?php

class Comon_IE_CsvUtilities
{
    /**
     * Prevent CSV formula injection when files are opened in Excel/Sheets.
     */
    public static function safeCsvCell($value): string
    {
        $s = (string)$value;
        if ($s === '') {
            return $s;
        }
        $first = $s[0];
        if ($first === '=' || $first === '+' || $first === '-' || $first === '@') {
            return "'" . $s;
        }
        return $s;
    }

    /**
     * @param array<int,mixed> $row
     * @return array<int,string>
     */
    public static function safeCsvRow(array $row): array
    {
        $out = [];
        foreach ($row as $v) {
            $out[] = self::safeCsvCell($v);
        }
        return $out;
    }

    public static function normalizeHeader(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/\s+/', ' ', $s);
        return $s;
    }

    public static function stripUtf8Bom(string $s): string
    {
        if (strncmp($s, "\xEF\xBB\xBF", 3) === 0) {
            return substr($s, 3);
        }
        return $s;
    }

    /**
     * @return array{0: list<string>, 1: array<string,int>} headers and normalized map => column index
     */
    public static function readHeadersFromHandle($fh): array
    {
        $headers = fgetcsv($fh);
        if (!$headers || !is_array($headers)) {
            return [[], []];
        }
        // Strip UTF-8 BOM from first header (common on Excel/Windows exports) so "Email" maps correctly.
        if (isset($headers[0]) && is_string($headers[0])) {
            $headers[0] = self::stripUtf8Bom($headers[0]);
        }
        $headerMap = [];
        foreach ($headers as $i => $h) {
            $headerMap[self::normalizeHeader((string)$h)] = $i;
        }
        return [$headers, $headerMap];
    }

    public static function countDataRows(string $path): int
    {
        $fh = fopen($path, 'r');
        if (!$fh) {
            return 0;
        }
        $headers = fgetcsv($fh);
        if (!$headers) {
            fclose($fh);
            return 0;
        }
        $n = 0;
        while (fgetcsv($fh) !== false) {
            $n++;
        }
        fclose($fh);
        return $n;
    }

    /**
     * @param array<string,mixed> $fieldMapping destination key => source column index (int)
     */
    public static function rowGet(
        array $row,
        string $key,
        array $fieldMapping,
        array $headerMap,
        array $headers
    ): string {
        if (strpos($key, 'CustomField_') === 0 || strpos($key, 'OrgCustomField_') === 0) {
            if (!empty($fieldMapping) && isset($fieldMapping[$key])) {
                $colIndex = (int)$fieldMapping[$key];
                if ($colIndex >= 0 && $colIndex < count($row)) {
                    return trim((string)$row[$colIndex]);
                }
            }
            return '';
        }
        if (!empty($fieldMapping) && isset($fieldMapping[$key])) {
            $colIndex = (int)$fieldMapping[$key];
            if ($colIndex >= 0 && $colIndex < count($row)) {
                return trim((string)$row[$colIndex]);
            }
            return '';
        }
        $k = self::normalizeHeader($key);
        if (!isset($headerMap[$k])) {
            return '';
        }
        $idx = $headerMap[$k];
        return isset($row[$idx]) ? trim((string)$row[$idx]) : '';
    }

    /**
     * Normalize a CSV date to Y-m-d for DB import.
     * Accepts: Y-m-d, Y/m/d, M/D/YYYY, D/M/YYYY (clear when a segment is greater than 12), optional time suffix (Excel).
     * When both parts are 1–12, M/D/Y is tried first (e.g. 5/1/2026 → May 1), then D/M/Y if invalid.
     */
    public static function normalizeImportDateToYmd(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $m)) {
            $iso = $m[1];
            $p = explode('-', $iso);
            if (count($p) === 3 && checkdate((int)$p[1], (int)$p[2], (int)$p[0])) {
                return $iso;
            }
        }
        if (preg_match('/^(.+?)\s+[\d]{1,2}:\d{2}/', $raw, $tm)) {
            $raw = trim($tm[1]);
        }
        if (preg_match('/^(\d{4})[\/.\-](\d{1,2})[\/.\-](\d{1,2})$/', $raw, $m)) {
            $y = (int)$m[1];
            $mo = (int)$m[2];
            $d = (int)$m[3];
            if (checkdate($mo, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }
            return null;
        }
        if (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/', $raw, $m)) {
            $a = (int)$m[1];
            $b = (int)$m[2];
            $y = (int)$m[3];
            if ($y < 1000 || $y > 9999) {
                return null;
            }
            $mo = 0;
            $d = 0;
            if ($a > 12) {
                $d = $a;
                $mo = $b;
            } elseif ($b > 12) {
                $mo = $a;
                $d = $b;
            } else {
                // Ambiguous: try M/D/Y (US / Excel) first, then D/M/Y if that day is invalid.
                $mo = $a;
                $d = $b;
                if (!checkdate($mo, $d, $y)) {
                    $d = $a;
                    $mo = $b;
                }
            }
            if (!checkdate($mo, $d, $y)) {
                return null;
            }
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }
        return null;
    }
}
