<?php
/**
 * Forms Module – JSON response helper for API
 */

class FormsResponseHelper
{
    public static function jsonSuccess($message = 'Lead created successfully', $leadId = null)
    {
        $data = ['status' => true, 'message' => $message];
        if ($leadId !== null) {
            $data['lead_id'] = (int)$leadId;
        }
        self::send(200, $data);
    }

    public static function jsonError($message, $errors = null, $httpCode = 400)
    {
        $data = ['status' => false, 'message' => $message];
        if (is_array($errors) && $errors !== []) {
            $data['errors'] = $errors;
        }
        self::send($httpCode, $data);
    }

    public static function send($httpCode, $data)
    {
        if (headers_sent() === false) {
            http_response_code($httpCode);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
