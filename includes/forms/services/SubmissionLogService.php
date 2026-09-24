<?php
/**
 * Forms Module – Log every submission to form_submissions table.
 */

class SubmissionLogService
{
    /** @var mysqli */
    private $connect;

    public function __construct($connect)
    {
        $this->connect = $connect;
    }

    /**
     * @param int|null $integrationId
     * @param int|null $formId
     * @param string|null $source
     * @param string|null $requestMethod
     * @param string|null $requestHeaders Truncated
     * @param string|null $rawPayload
     * @param array|null $parsedPayload
     * @param array|null $mappedPayload
     * @param int|null $leadId
     * @param string $status success|failed
     * @param string|null $errorMessage
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @return int|null submission id
     */
    public function log(
        $integrationId,
        $formId,
        $source,
        $requestMethod,
        $requestHeaders,
        $rawPayload,
        $parsedPayload,
        $mappedPayload,
        $leadId,
        $status,
        $errorMessage,
        $ipAddress,
        $userAgent
    ) {
        $tableExists = $this->tableExists();
        if (!$tableExists) return null;

        $parsedJson = $parsedPayload !== null ? json_encode($parsedPayload, JSON_UNESCAPED_UNICODE) : null;
        $mappedJson = $mappedPayload !== null ? json_encode($mappedPayload, JSON_UNESCAPED_UNICODE) : null;
        if ($requestHeaders !== null && strlen($requestHeaders) > 65535) $requestHeaders = substr($requestHeaders, 0, 65535);
        if ($rawPayload !== null && strlen($rawPayload) > 65535) $rawPayload = substr($rawPayload, 0, 65535) . '...[truncated]';

        $stmt = $this->connect->prepare("INSERT INTO form_submissions (integration_id, form_id, source, request_method, request_headers, raw_payload, parsed_payload, mapped_payload, lead_id, status, error_message, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) return null;
        $leadIdVal = $leadId !== null ? (int)$leadId : null;
        $stmt->bind_param('iissssssissss',
            $integrationId, $formId, $source, $requestMethod, $requestHeaders, $rawPayload,
            $parsedJson, $mappedJson, $leadIdVal, $status, $errorMessage, $ipAddress, $userAgent
        );
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Get recent submissions for admin listing.
     */
    public function getRecent($limit = 50, $integrationId = null, $formId = null, $status = null, $dateFrom = null, $dateTo = null)
    {
        if (!$this->tableExists()) return [];
        $conditions = ['1=1'];
        $types = '';
        $params = [];
        if ($integrationId !== null && $integrationId !== '') {
            $conditions[] = 'integration_id = ?';
            $types .= 'i';
            $params[] = (int)$integrationId;
        }
        if ($formId !== null && $formId !== '') {
            $conditions[] = 'form_id = ?';
            $types .= 'i';
            $params[] = (int)$formId;
        }
        if ($status !== null && $status !== '') {
            $conditions[] = 'status = ?';
            $types .= 's';
            $params[] = $status;
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
        $sql = "SELECT * FROM form_submissions WHERE " . implode(' AND ', $conditions) . " ORDER BY id DESC LIMIT " . (int)$limit;
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
     * Delete a submission by id.
     * @param int $id
     * @return bool
     */
    public function delete($id)
    {
        $id = (int)$id;
        if ($id <= 0) return false;
        if (!$this->tableExists()) return false;
        $stmt = $this->connect->prepare("DELETE FROM form_submissions WHERE id = ? LIMIT 1");
        if (!$stmt) return false;
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    private function tableExists()
    {
        $r = mysqli_query($this->connect, "SHOW TABLES LIKE 'form_submissions'");
        return $r && mysqli_fetch_assoc($r);
    }
}
