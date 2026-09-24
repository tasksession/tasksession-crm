<?php
/**
 * Forms Module – Admin controller (data for views)
 * Admin pages require lib-initialize and auth; then include this and pass $connect, $config.
 */

class FormsController
{
    /** @var mysqli */
    private $connect;
    /** @var array */
    private $config;
    /** @var FormService */
    private $formService;
    /** @var IntegrationService */
    private $integrationService;
    /** @var SubmissionLogService */
    private $submissionLogService;
    /** @var DebugLogService */
    private $debugLogService;

    public function __construct($connect, array $config = [])
    {
        $this->connect = $connect;
        $this->config = $config;
        $this->formService = new FormService($connect);
        $this->integrationService = new IntegrationService($connect);
        $this->submissionLogService = new SubmissionLogService($connect);
        $this->debugLogService = new DebugLogService($connect);
    }

    public function getForms($activeOnly = false)
    {
        return $this->formService->getAll($activeOnly);
    }

    public function getFormById($id)
    {
        return $this->formService->getById($id);
    }

    public function getFormFields($formId)
    {
        return $this->formService->getFieldsByFormId($formId);
    }

    public function getIntegrations()
    {
        return $this->integrationService->getAll();
    }

    public function getIntegrationById($id)
    {
        return $this->integrationService->getById($id);
    }

    public function getMappings($integrationId, $formId = null)
    {
        return $this->integrationService->getMappings($integrationId, $formId);
    }

    public function getSubmissions($limit = 50, $integrationId = null, $formId = null, $status = null, $dateFrom = null, $dateTo = null)
    {
        return $this->submissionLogService->getRecent($limit, $integrationId, $formId, $status, $dateFrom, $dateTo);
    }

    public function getDebugLogs($limit = 100, $level = null, $source = null, $requestId = null, $dateFrom = null, $dateTo = null)
    {
        return $this->debugLogService->getRecent($limit, $level, $source, $requestId, $dateFrom, $dateTo);
    }

    public function getSubmissionSummary($days = 7)
    {
        return $this->debugLogService->getSubmissionSummary($days);
    }

    public function getLeadColumnsWhitelist()
    {
        return $this->config['lead_columns_whitelist'] ?? [];
    }

    public function getLeadCustomFields()
    {
        $res = mysqli_query($this->connect, "SELECT id, label, field_type FROM custom_fields WHERE entity_type = 'lead' ORDER BY sort_order ASC, id ASC");
        $rows = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    public function getLeadStatuses()
    {
        $res = mysqli_query($this->connect, "SELECT * FROM lead_statuses ORDER BY sort_order ASC, id ASC");
        $rows = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    public function getLeadSources()
    {
        $res = mysqli_query($this->connect, "SELECT * FROM lead_sources ORDER BY sort_order ASC, id ASC");
        $rows = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}
