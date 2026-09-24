<?php
/**
 * Forms Module – Query forms_debug_logs for admin debug page.
 */

class DebugLogService
{
    /** @var mysqli */
    private $connect;

    public function __construct($connect)
    {
        $this->connect = $connect;
    }

    /**
     * Get recent log entries with optional filters.
     * @param int $limit
     * @param string|null $level
     * @param string|null $source
     * @param string|null $requestId
     * @param string|null $dateFrom Y-m-d
     * @param string|null $dateTo Y-m-d
     */
    public function getRecent($limit = 100, $level = null, $source = null, $requestId = null, $dateFrom = null, $dateTo = null)
    {
        if (!$this->tableExists()) return [];
        $conditions = ['1=1'];
        $types = '';
        $params = [];

        if ($level !== null && $level !== '') {
            $conditions[] = 'level = ?';
            $types .= 's';
            $params[] = $level;
        }
        if ($source !== null && $source !== '') {
            $conditions[] = 'source = ?';
            $types .= 's';
            $params[] = $source;
        }
        if ($requestId !== null && $requestId !== '') {
            $conditions[] = 'request_id = ?';
            $types .= 's';
            $params[] = $requestId;
        }
        if ($dateFrom !== null && $dateFrom !== '') {
            $conditions[] = 'created_at >= ?';
            $types .= 's';
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== null && $dateTo !== '') {
            $conditions[] = 'created_at <= ?';
            $types .= 's';
            $params[] = $dateTo . ' 23:59:59';
        }

        $sql = "SELECT * FROM forms_debug_logs WHERE " . implode(' AND ', $conditions) . " ORDER BY id DESC LIMIT " . (int)$limit;
        $stmt = $this->connect->prepare($sql);
        if (!$stmt) return [];
        if ($params !== []) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }

    /**
     * Get submission status summary (success/failed counts).
     */
    public function getSubmissionSummary($days = 7)
    {
        if (!$this->tableExists()) return ['success' => 0, 'failed' => 0];
        $res = mysqli_query($this->connect, "SELECT status, COUNT(*) AS c FROM form_submissions WHERE created_at >= DATE_SUB(NOW(), INTERVAL " . (int)$days . " DAY) GROUP BY status");
        $summary = ['success' => 0, 'failed' => 0];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $summary[$row['status']] = (int)$row['c'];
            }
        }
        return $summary;
    }

    private function tableExists()
    {
        $r = mysqli_query($this->connect, "SHOW TABLES LIKE 'forms_debug_logs'");
        return $r && mysqli_fetch_assoc($r);
    }
}
