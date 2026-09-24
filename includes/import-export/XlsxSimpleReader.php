<?php
/**
 * Minimal first-sheet reader for .xlsx (no PhpSpreadsheet).
 * Suitable for simple grids; fails gracefully on malformed files.
 */
class Comon_IE_XlsxSimpleReader
{
    /**
     * @return list<list<string>> All rows as string cells
     */
    public static function readRows(string $path): array
    {
        if (!class_exists('ZipArchive')) {
            return [];
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }
        $sharedStrings = [];
        if ($zip->locateName('xl/sharedStrings.xml') !== false) {
            $xml = $zip->getFromName('xl/sharedStrings.xml');
            if ($xml) {
                $sx = @simplexml_load_string($xml);
                if ($sx && isset($sx->si)) {
                    foreach ($sx->si as $si) {
                        if (isset($si->t)) {
                            $sharedStrings[] = (string)$si->t;
                        } elseif (isset($si->r)) {
                            $buf = '';
                            foreach ($si->r as $r) {
                                if (isset($r->t)) {
                                    $buf .= (string)$r->t;
                                }
                            }
                            $sharedStrings[] = $buf;
                        } else {
                            $sharedStrings[] = '';
                        }
                    }
                }
            }
        }
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if (!$sheetXml) {
            return [];
        }
        $sx = @simplexml_load_string($sheetXml);
        if (!$sx || !isset($sx->sheetData->row)) {
            return [];
        }
        $rows = [];
        foreach ($sx->sheetData->row as $row) {
            $line = [];
            $maxCol = 0;
            $cells = [];
            foreach ($row->c as $c) {
                $r = (string)$c['r'];
                if ($r === '') {
                    continue;
                }
                preg_match('/^([A-Z]+)(\d+)$/', $r, $m);
                if (!$m) {
                    continue;
                }
                $colLetters = $m[1];
                $colIndex = self::columnLettersToIndex($colLetters);
                $maxCol = max($maxCol, $colIndex);
                $type = (string)$c['t'];
                $v = isset($c->v) ? (string)$c->v : '';
                if ($type === 's' && $v !== '' && ctype_digit($v)) {
                    $idx = (int)$v;
                    $v = $sharedStrings[$idx] ?? '';
                }
                $cells[$colIndex] = $v;
            }
            for ($i = 0; $i <= $maxCol; $i++) {
                $line[] = $cells[$i] ?? '';
            }
            $rows[] = $line;
        }
        return $rows;
    }

    private static function columnLettersToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $n = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $n = $n * 26 + (ord($letters[$i]) - 64);
        }
        return $n - 1;
    }

    public static function writeTempCsv(string $xlsxPath): ?string
    {
        $rows = self::readRows($xlsxPath);
        if ($rows === []) {
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'comon_xlsx_');
        if ($tmp === false) {
            return null;
        }
        $csvPath = $tmp . '.csv';
        rename($tmp, $csvPath);
        $fh = fopen($csvPath, 'w');
        if (!$fh) {
            return null;
        }
        foreach ($rows as $r) {
            fputcsv($fh, $r);
        }
        fclose($fh);
        return $csvPath;
    }
}
