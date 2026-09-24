<?php

class Comon_IE_ChatImportPackageReader
{
    /** @var array<string,mixed> */
    private array $manifest = [];

    private string $rootDir;

    public function __construct(string $extractedPackageRootDir)
    {
        $this->rootDir = rtrim($extractedPackageRootDir, DIRECTORY_SEPARATOR);
    }

    /**
     * @return array{ok:bool,error?:string,manifest?:array}
     */
    public function loadManifest(): array
    {
        $path = $this->rootDir . DIRECTORY_SEPARATOR . Comon_IE_ChatImportManifest::FILE_MANIFEST;
        if (!is_readable($path)) {
            return ['ok' => false, 'error' => 'manifest.json missing or unreadable'];
        }
        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return ['ok' => false, 'error' => 'manifest.json empty'];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => 'manifest.json invalid JSON'];
        }
        $v = Comon_IE_ChatImportManifest::validate($decoded);
        if (!$v['ok']) {
            return $v;
        }
        $this->manifest = $decoded;
        return ['ok' => true, 'manifest' => $decoded];
    }

    public function getManifest(): array
    {
        return $this->manifest;
    }

    public function attachmentsDir(): string
    {
        return $this->rootDir . DIRECTORY_SEPARATOR . Comon_IE_ChatImportManifest::DIR_ATTACHMENTS;
    }

    /**
     * Count non-empty JSON lines across all optional jsonl files + one line per group in groups.json.
     */
    public function countWorkUnits(): int
    {
        $n = 0;
        foreach ([
            Comon_IE_ChatImportManifest::FILE_ONE_TO_ONE,
            Comon_IE_ChatImportManifest::FILE_DISCUSSION,
            Comon_IE_ChatImportManifest::FILE_GROUP_MESSAGES,
            Comon_IE_ChatImportManifest::FILE_TASK_MESSAGES,
        ] as $rel) {
            $n += $this->countJsonlLines($rel);
        }
        $g = $this->rootDir . DIRECTORY_SEPARATOR . Comon_IE_ChatImportManifest::FILE_GROUPS;
        if (is_readable($g)) {
            $raw = file_get_contents($g);
            if ($raw !== false) {
                $arr = json_decode($raw, true);
                if (is_array($arr)) {
                    $n += count($arr);
                }
            }
        }
        return max(1, $n);
    }

    /**
     * @return array{one_to_one:int,discussion:int,group_msgs:int,task_msgs:int,groups:int}
     */
    public function countPreview(): array
    {
        return [
            'one_to_one' => $this->countJsonlLines(Comon_IE_ChatImportManifest::FILE_ONE_TO_ONE),
            'discussion' => $this->countJsonlLines(Comon_IE_ChatImportManifest::FILE_DISCUSSION),
            'group_msgs' => $this->countJsonlLines(Comon_IE_ChatImportManifest::FILE_GROUP_MESSAGES),
            'task_msgs' => $this->countJsonlLines(Comon_IE_ChatImportManifest::FILE_TASK_MESSAGES),
            'groups' => $this->countGroups(),
        ];
    }

    private function countGroups(): int
    {
        $g = $this->rootDir . DIRECTORY_SEPARATOR . Comon_IE_ChatImportManifest::FILE_GROUPS;
        if (!is_readable($g)) {
            return 0;
        }
        $arr = json_decode((string)file_get_contents($g), true);
        return is_array($arr) ? count($arr) : 0;
    }

    private function countJsonlLines(string $relativeName): int
    {
        $path = $this->rootDir . DIRECTORY_SEPARATOR . $relativeName;
        if (!is_readable($path)) {
            return 0;
        }
        $fh = fopen($path, 'r');
        if (!$fh) {
            return 0;
        }
        $c = 0;
        while (($line = fgets($fh)) !== false) {
            $t = trim($line);
            if ($t !== '' && $t[0] !== '#') {
                $c++;
            }
        }
        fclose($fh);
        return $c;
    }

    /**
     * Extract .zip to a directory (caller provides empty or new dir).
     *
     * @return array{ok:bool,error?:string}
     */
    public static function extractZipTo(string $zipPath, string $destDir): array
    {
        if (!is_file($zipPath) || !is_readable($zipPath)) {
            return ['ok' => false, 'error' => 'ZIP not readable'];
        }
        if (!is_dir($destDir)) {
            if (!@mkdir($destDir, 0755, true)) {
                return ['ok' => false, 'error' => 'Cannot create extract dir'];
            }
        }
        if (!class_exists('ZipArchive')) {
            return ['ok' => false, 'error' => 'PHP ZipArchive not available'];
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return ['ok' => false, 'error' => 'Cannot open ZIP'];
        }
        if (!$zip->extractTo($destDir)) {
            $zip->close();
            return ['ok' => false, 'error' => 'ZIP extract failed'];
        }
        $zip->close();
        return ['ok' => true];
    }

    public function path(string $relative): string
    {
        return $this->rootDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    }
}
