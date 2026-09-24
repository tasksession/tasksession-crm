<?php
/**
 * AJAX endpoint to get email history for a lead
 * Returns all threads linked to the lead
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, but log them
ini_set('log_errors', 1);

ob_start();
header('Content-Type: application/json');

// Logging function for lead sidebar operations (disabled in production)
function logLeadSidebar($message, $data = []) {
    // Intentionally left blank – no file logging to keep production clean.
}

// Set error handler to log errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    logLeadSidebar('PHP Error', [
        'errno' => $errno,
        'errstr' => $errstr,
        'errfile' => $errfile,
        'errline' => $errline
    ]);
    return false; // Let PHP handle the error normally
});

try {
    require_once("../../includes/lib-initialize.php");
    require_once("../../includes/functions.php");
    $__emailMessage = __DIR__ . '/../../includes/email/EmailMessage.php';
    $__emailEncryption = __DIR__ . '/../../includes/email/EmailEncryption.php';
    if (!is_file($__emailMessage) || !is_file($__emailEncryption)
        || (function_exists('tasksession_is_free_edition') && tasksession_is_free_edition())) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Email history is not available'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ob_end_flush();
        exit;
    }
    require_once($__emailMessage);
    require_once($__emailEncryption);
} catch (Exception $e) {
    logLeadSidebar('Require Error', [
        'error_message' => $e->getMessage(),
        'error_file' => $e->getFile(),
        'error_line' => $e->getLine()
    ]);
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load required files: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ob_end_flush();
    exit;
}

// Log request start
logLeadSidebar('Get Lead Email History - Request Started', [
    'user_id' => isset($session) && $session->isLoggedIn() ? $session->userId : 'NOT LOGGED IN',
    'lead_id_get' => $_GET['lead_id'] ?? 'NOT SET',
    'all_get_params' => $_GET,
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
]);

// Check authentication
if (!isset($session) || !$session->isLoggedIn()) {
    logLeadSidebar('Get Lead Email History - Unauthorized', [
        'user_id' => 'NOT LOGGED IN',
        'lead_id_get' => $_GET['lead_id'] ?? 'NOT SET',
        'session_exists' => isset($session)
    ]);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ob_end_flush();
    exit;
}

$userId = $session->userId;
$leadId = isset($_GET['lead_id']) ? (int)$_GET['lead_id'] : 0;

logLeadSidebar('Get Lead Email History - Parameters Processed', [
    'user_id' => $userId,
    'lead_id_raw' => $_GET['lead_id'] ?? 'NOT SET',
    'lead_id_processed' => $leadId,
    'lead_id_valid' => ($leadId > 0)
]);

if ($leadId <= 0) {
    logLeadSidebar('Get Lead Email History - Invalid lead_id', [
        'user_id' => $userId,
        'lead_id' => $leadId,
        'lead_id_raw' => $_GET['lead_id'] ?? 'NOT SET'
    ]);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Missing or invalid lead_id'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ob_end_flush();
    exit;
}

try {
    global $database;
    
    // Check if table exists first
    $tableCheckSql = "SHOW TABLES LIKE 'email_thread_lead_links'";
    $tableCheckResult = @$database->query($tableCheckSql);
    if (!$tableCheckResult || $database->numRows($tableCheckResult) == 0) {
        logLeadSidebar('Get Lead Email History - Table Missing', [
            'user_id' => $userId,
            'lead_id' => $leadId,
            'table' => 'email_thread_lead_links'
        ]);
        ob_clean();
        echo json_encode([
            'success' => true,
            'threads' => [] // Return empty array if table doesn't exist
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ob_end_flush();
        exit;
    }
    
    // Get all thread_ids linked to this lead
    $linksSql = "SELECT DISTINCT thread_id, account_id, created_at 
                 FROM email_thread_lead_links 
                 WHERE lead_id = {$leadId} 
                 ORDER BY created_at DESC";
    
    logLeadSidebar('Get Lead Email History - Executing SQL', [
        'user_id' => $userId,
        'lead_id' => $leadId,
        'sql' => $linksSql
    ]);
    
    $linksResult = $database->query($linksSql);
    $linksCount = $linksResult ? $database->numRows($linksResult) : 0;
    
    logLeadSidebar('Get Lead Email History - Links Query Result', [
        'user_id' => $userId,
        'lead_id' => $leadId,
        'links_found' => $linksCount,
        'query_success' => ($linksResult !== false),
        'database_error' => $linksResult === false ? $database->error() : 'NONE'
    ]);
    
    $threads = [];
    
    if ($linksResult && $database->numRows($linksResult) > 0) {
        $linkIndex = 0;
        while ($link = $database->fetchArray($linksResult)) {
            $linkIndex++;
            $threadId = $link['thread_id'];
            $accountId = (int)$link['account_id'];
            $threadIdEscaped = $database->escapeValue($threadId);
            
            logLeadSidebar('Get Lead Email History - Processing Link', [
                'user_id' => $userId,
                'lead_id' => $leadId,
                'link_index' => $linkIndex,
                'thread_id' => $threadId,
                'account_id' => $accountId,
                'linked_at' => $link['created_at'] ?? 'N/A'
            ]);
            
            // First, check ALL messages in thread to see what we have
            $allMessagesSql = "SELECT id, received_date, is_sent, folder 
                              FROM email_messages 
                              WHERE CAST(account_id AS UNSIGNED) = " . (int)$accountId . " 
                                AND BINARY thread_id = BINARY '{$threadIdEscaped}' 
                              ORDER BY received_date DESC";
            $allMessagesResult = $database->query($allMessagesSql);
            $allMessagesCount = $allMessagesResult ? $database->numRows($allMessagesResult) : 0;
            $allMessages = [];
            if ($allMessagesResult) {
                while ($msg = $database->fetchArray($allMessagesResult)) {
                    $allMessages[] = [
                        'id' => $msg['id'],
                        'received_date' => $msg['received_date'],
                        'is_sent' => $msg['is_sent'] ?? 0,
                        'folder' => $msg['folder'] ?? 'N/A'
                    ];
                }
            }
            
            logLeadSidebar('Get Lead Email History - All Messages in Thread', [
                'user_id' => $userId,
                'lead_id' => $leadId,
                'thread_id' => $threadId,
                'account_id' => $accountId,
                'total_messages' => $allMessagesCount,
                'messages' => $allMessages,
                'latest_message_date' => !empty($allMessages) ? $allMessages[0]['received_date'] : 'NONE'
            ]);
            
            // Get ALL messages in the thread - each message will be shown as a separate card
            // First, let's check what messages exist for this thread_id (regardless of account_id) for debugging
            $debugSql = "SELECT id, account_id, thread_id, received_date, is_sent, folder 
                        FROM email_messages 
                        WHERE BINARY thread_id = BINARY '{$threadIdEscaped}' 
                        ORDER BY received_date DESC";
            $debugResult = $database->query($debugSql);
            $debugMessages = [];
            if ($debugResult) {
                while ($dbg = $database->fetchArray($debugResult)) {
                    $debugMessages[] = [
                        'id' => $dbg['id'],
                        'account_id' => $dbg['account_id'],
                        'account_id_type' => gettype($dbg['account_id']),
                        'thread_id' => substr($dbg['thread_id'], 0, 20) . '...',
                        'received_date' => $dbg['received_date'],
                        'is_sent' => $dbg['is_sent'] ?? 0,
                        'folder' => $dbg['folder'] ?? 'N/A'
                    ];
                }
            }
            
            logLeadSidebar('Get Lead Email History - Debug: All Messages for Thread ID', [
                'user_id' => $userId,
                'lead_id' => $leadId,
                'thread_id' => $threadId,
                'account_id_from_link' => $accountId,
                'account_id_type' => gettype($accountId),
                'messages_found' => count($debugMessages),
                'messages' => $debugMessages,
                'note' => 'Checking all messages with this thread_id (regardless of account_id)'
            ]);
            
            // Use CAST to ensure account_id comparison works regardless of type
            $messageSql = "SELECT id, subject, from_email, from_name, to_emails, received_date, thread_id, is_sent, folder, is_read, body_text, body_html
                          FROM email_messages 
                          WHERE CAST(account_id AS UNSIGNED) = " . (int)$accountId . " 
                            AND BINARY thread_id = BINARY '{$threadIdEscaped}' 
                          ORDER BY received_date DESC";
            
            logLeadSidebar('Get Lead Email History - Executing Message Query (ALL messages)', [
                'user_id' => $userId,
                'lead_id' => $leadId,
                'thread_id' => $threadId,
                'thread_id_length' => strlen($threadId),
                'account_id' => $accountId,
                'account_id_type' => gettype($accountId),
                'account_id_string' => (string)$accountId,
                'sql' => $messageSql,
                'note' => 'Getting ALL messages in thread - each will be a separate card',
                'total_messages_in_thread' => $allMessagesCount
            ]);
            
            $messageResult = $database->query($messageSql);
            $messageFound = $messageResult && $database->numRows($messageResult) > 0;
            
            // If no messages found, try with account_id as string for comparison
            if (!$messageFound) {
                $messageSqlString = "SELECT id, account_id, thread_id, received_date 
                                   FROM email_messages 
                                   WHERE account_id = '{$accountId}' 
                                     AND BINARY thread_id = BINARY '{$threadIdEscaped}' 
                                   LIMIT 1";
                $debugResult2 = $database->query($messageSqlString);
                $foundWithString = $debugResult2 && $database->numRows($debugResult2) > 0;
                
                logLeadSidebar('Get Lead Email History - Debug: Trying account_id as string', [
                    'user_id' => $userId,
                    'lead_id' => $leadId,
                    'thread_id' => $threadId,
                    'account_id' => $accountId,
                    'found_with_string' => $foundWithString,
                    'sql' => $messageSqlString
                ]);
            }
            
            logLeadSidebar('Get Lead Email History - Message Query Result', [
                'user_id' => $userId,
                'lead_id' => $leadId,
                'thread_id' => $threadId,
                'thread_id_length' => strlen($threadId),
                'account_id' => $accountId,
                'account_id_type' => gettype($accountId),
                'message_found' => $messageFound,
                'query_success' => ($messageResult !== false),
                'database_error' => $messageResult === false ? $database->error() : 'NONE',
                'total_messages_in_thread' => $allMessagesCount,
                'num_rows' => $messageResult ? $database->numRows($messageResult) : 0
            ]);
            
            if ($messageResult && $database->numRows($messageResult) > 0) {
                $messageIndex = 0;
                while ($message = $database->fetchArray($messageResult)) {
                    $messageIndex++;
                    
                    logLeadSidebar('Get Lead Email History - Processing Message', [
                        'user_id' => $userId,
                        'lead_id' => $leadId,
                        'thread_id' => $threadId,
                        'message_index' => $messageIndex,
                        'message_id' => $message['id'] ?? 'N/A',
                        'message_received_date' => $message['received_date'] ?? 'N/A',
                        'message_is_sent' => $message['is_sent'] ?? 0,
                        'message_folder' => $message['folder'] ?? 'N/A',
                        'note' => 'Processing individual message to show as separate card'
                    ]);
                    
                    // Decrypt email data
                    try {
                        $decrypted = EmailEncryption::decryptEmailData($message);
                        logLeadSidebar('Get Lead Email History - Decryption Success', [
                            'user_id' => $userId,
                            'lead_id' => $leadId,
                            'thread_id' => $threadId,
                            'message_id' => $message['id'] ?? 'N/A',
                            'has_subject' => isset($decrypted['subject']),
                            'has_from_email' => isset($decrypted['from_email']),
                            'has_body_text' => isset($decrypted['body_text']),
                            'has_body_html' => isset($decrypted['body_html'])
                        ]);
                    } catch (Exception $decryptError) {
                        logLeadSidebar('Get Lead Email History - Decryption Error', [
                            'user_id' => $userId,
                            'lead_id' => $leadId,
                            'thread_id' => $threadId,
                            'message_id' => $message['id'] ?? 'N/A',
                            'error' => $decryptError->getMessage(),
                            'error_file' => $decryptError->getFile(),
                            'error_line' => $decryptError->getLine()
                        ]);
                        // Skip this message if decryption fails
                        continue;
                    }
                    
                    try {
                        $subject = $decrypted['subject'] ?? '(No Subject)';
                        $fromEmail = $decrypted['from_email'] ?? '';
                        $fromName = $decrypted['from_name'] ?? $fromEmail;
                        
                        // Get body snippet (first line/preview)
                        $bodyText = $decrypted['body_text'] ?? '';
                        $bodyHtml = $decrypted['body_html'] ?? '';
                        $bodySnippet = '';
                        
                        // Ensure bodyText and bodyHtml are strings
                        if (!is_string($bodyText)) {
                            $bodyText = '';
                        }
                        if (!is_string($bodyHtml)) {
                            $bodyHtml = '';
                        }
                        
                        if (!empty($bodyText)) {
                            $bodyText = trim($bodyText);
                            if (function_exists('mb_substr')) {
                                $bodySnippet = mb_substr($bodyText, 0, 100);
                            } else {
                                $bodySnippet = substr($bodyText, 0, 100);
                            }
                        } elseif (!empty($bodyHtml)) {
                            $bodyHtml = trim($bodyHtml);
                            $stripped = strip_tags($bodyHtml);
                            if (function_exists('mb_substr')) {
                                $bodySnippet = mb_substr($stripped, 0, 100);
                            } else {
                                $bodySnippet = substr($stripped, 0, 100);
                            }
                        }
                        $bodySnippet = trim($bodySnippet);
                    } catch (Exception $bodyError) {
                        logLeadSidebar('Get Lead Email History - Body Processing Error', [
                            'user_id' => $userId,
                            'lead_id' => $leadId,
                            'thread_id' => $threadId,
                            'message_id' => $message['id'] ?? 'N/A',
                            'error' => $bodyError->getMessage(),
                            'error_file' => $bodyError->getFile(),
                            'error_line' => $bodyError->getLine()
                        ]);
                        $bodySnippet = ''; // Set empty snippet on error
                    }
                    
                    // Get to_emails for recipient display
                    // Note: to_emails is decrypted as an array (JSON), not a string
                    $toEmails = $decrypted['to_emails'] ?? [];
                    $toEmailsArray = [];
                    
                    // Handle both array and string formats
                    if (is_array($toEmails)) {
                        // Already an array from decryption
                        foreach ($toEmails as $email) {
                            if (is_string($email)) {
                                $email = trim($email);
                                if (!empty($email)) {
                                    // Extract email from "Name <email>" format
                                    if (preg_match('/<(.+?)>/', $email, $matches)) {
                                        $toEmailsArray[] = trim($matches[1]);
                                    } else {
                                        $toEmailsArray[] = trim($email);
                                    }
                                }
                            } elseif (is_array($email) && isset($email['email'])) {
                                // Handle array format like [['email' => '...', 'name' => '...']]
                                $toEmailsArray[] = trim($email['email']);
                            }
                        }
                    } elseif (is_string($toEmails) && !empty($toEmails)) {
                        // Fallback: if it's a string, parse it
                        $emails = preg_split('/[,;]/', $toEmails);
                        foreach ($emails as $email) {
                            $email = trim($email);
                            if (!empty($email)) {
                                // Extract email from "Name <email>" format
                                if (preg_match('/<(.+?)>/', $email, $matches)) {
                                    $toEmailsArray[] = trim($matches[1]);
                                } else {
                                    $toEmailsArray[] = trim($email);
                                }
                            }
                        }
                    }
                    
                    // Determine if email is sent or received
                    $isSent = (bool)($message['is_sent'] ?? 0);
                    $displayFrom = $fromEmail;
                    $displayTo = !empty($toEmailsArray) ? $toEmailsArray : [];
                    
                    try {
                        // For each message, create a separate entry
                        // Each message will be displayed as its own card in the history
                        $threads[] = [
                            'message_id' => $message['id'], // Add message_id to identify individual messages
                            'thread_id' => $threadId,
                            'account_id' => $accountId,
                            'subject' => $subject,
                            'from_email' => $displayFrom, // Sender email
                            'from_name' => $fromName, // Sender name
                            'to_emails' => $displayTo, // Array of recipient emails
                            'body_snippet' => $bodySnippet, // Body preview (1 line)
                            'is_read' => (bool)($message['is_read'] ?? 0), // Read/unread status
                            'participants' => [[
                                'email' => $fromEmail,
                                'name' => $fromName
                            ]], // Single participant for this message
                            'message_count' => 1, // Each message is counted as 1
                            'received_date' => $message['received_date'],
                            'linked_at' => $link['created_at'],
                            'is_sent' => $isSent, // Sent or received
                            'folder' => $message['folder'] ?? 'N/A'
                        ];
                        
                        logLeadSidebar('Get Lead Email History - Message Added to Results', [
                            'user_id' => $userId,
                            'lead_id' => $leadId,
                            'thread_id' => $threadId,
                            'message_id' => $message['id'],
                            'account_id' => $accountId,
                            'account_id_type' => gettype($accountId),
                            'message_index' => $messageIndex,
                            'total_messages_so_far' => count($threads),
                            'subject' => substr($subject, 0, 50),
                            'subject_length' => strlen($subject),
                            'body_snippet_length' => strlen($bodySnippet),
                            'to_emails_count' => count($displayTo),
                            'is_sent' => $isSent,
                            'folder' => $message['folder'] ?? 'N/A'
                        ]);
                    } catch (Exception $arrayError) {
                        logLeadSidebar('Get Lead Email History - Array Creation Error', [
                            'user_id' => $userId,
                            'lead_id' => $leadId,
                            'thread_id' => $threadId,
                            'message_id' => $message['id'] ?? 'N/A',
                            'error' => $arrayError->getMessage(),
                            'error_file' => $arrayError->getFile(),
                            'error_line' => $arrayError->getLine(),
                            'error_trace' => $arrayError->getTraceAsString()
                        ]);
                        // Skip this message if array creation fails
                        continue;
                    }
                }
            } else {
                // If no messages found, check if message exists with different account_id
                $checkOtherAccountSql = "SELECT id, account_id, received_date, is_sent, folder 
                                         FROM email_messages 
                                         WHERE BINARY thread_id = BINARY '{$threadIdEscaped}' 
                                         LIMIT 5";
                $checkOtherResult = $database->query($checkOtherAccountSql);
                $otherAccountMessages = [];
                if ($checkOtherResult) {
                    while ($other = $database->fetchArray($checkOtherResult)) {
                        $otherAccountMessages[] = [
                            'id' => $other['id'],
                            'account_id' => $other['account_id'],
                            'account_id_type' => gettype($other['account_id']),
                            'account_id_string' => (string)$other['account_id'],
                            'received_date' => $other['received_date'],
                            'is_sent' => $other['is_sent'] ?? 0,
                            'folder' => $other['folder'] ?? 'N/A'
                        ];
                    }
                }
                
                logLeadSidebar('Get Lead Email History - No Message Found for Thread', [
                    'user_id' => $userId,
                    'lead_id' => $leadId,
                    'thread_id' => $threadId,
                    'thread_id_length' => strlen($threadId),
                    'account_id_from_link' => $accountId,
                    'account_id_type' => gettype($accountId),
                    'account_id_string' => (string)$accountId,
                    'messages_with_other_account_ids' => $otherAccountMessages,
                    'note' => 'No messages found with matching account_id. Checking if messages exist with different account_id.'
                ]);
            }
        }
    }
    
    // Sort all messages by received_date DESC (newest first)
    // Now we're sorting individual messages, not threads
    logLeadSidebar('Get Lead Email History - Before Sorting', [
        'user_id' => $userId,
        'lead_id' => $leadId,
        'total_messages' => count($threads),
        'messages_before_sort' => array_map(function($t) {
            return [
                'message_id' => $t['message_id'] ?? 'N/A',
                'thread_id' => substr($t['thread_id'], 0, 15) . '...',
                'subject' => substr($t['subject'], 0, 30),
                'received_date' => $t['received_date'] ?? 'N/A'
            ];
        }, $threads)
    ]);
    
    usort($threads, function($a, $b) {
        $dateA = strtotime($a['received_date'] ?? $a['linked_at'] ?? '1970-01-01');
        $dateB = strtotime($b['received_date'] ?? $b['linked_at'] ?? '1970-01-01');
        return $dateB - $dateA; // DESC order (newest first)
    });
    
    logLeadSidebar('Get Lead Email History - After Sorting', [
        'user_id' => $userId,
        'lead_id' => $leadId,
        'total_messages' => count($threads),
        'messages_after_sort' => array_map(function($t) {
            return [
                'message_id' => $t['message_id'] ?? 'N/A',
                'thread_id' => substr($t['thread_id'], 0, 15) . '...',
                'subject' => substr($t['subject'], 0, 30),
                'received_date' => $t['received_date'] ?? 'N/A'
            ];
        }, $threads)
    ]);
    
    logLeadSidebar('Get Lead Email History - Final Results', [
        'user_id' => $userId,
        'lead_id' => $leadId,
        'total_messages' => count($threads),
        'messages_data' => array_map(function($t) {
            return [
                'message_id' => $t['message_id'] ?? 'N/A',
                'thread_id' => substr($t['thread_id'] ?? '', 0, 15) . '...',
                'subject' => substr($t['subject'] ?? '', 0, 50),
                'received_date' => $t['received_date'] ?? 'N/A',
                'account_id' => $t['account_id'] ?? 'N/A',
                'account_id_type' => gettype($t['account_id'] ?? null),
                'is_sent' => $t['is_sent'] ?? false,
                'folder' => $t['folder'] ?? 'N/A',
                'from_email' => substr($t['from_email'] ?? '', 0, 30)
            ];
        }, $threads),
        'full_threads_data' => $threads // Include full data for detailed debugging
    ]);
    
    // Log what we're sending to the client
    logLeadSidebar('Get Lead Email History - Sending Response', [
        'user_id' => $userId,
        'lead_id' => $leadId,
        'success' => true,
        'messages_count' => count($threads),
        'response_preview' => [
            'first_message' => !empty($threads) ? [
                'message_id' => $threads[0]['message_id'] ?? 'N/A',
                'thread_id' => substr($threads[0]['thread_id'], 0, 15) . '...',
                'subject' => substr($threads[0]['subject'], 0, 30),
                'received_date' => $threads[0]['received_date'] ?? 'N/A'
            ] : 'NONE',
            'last_message' => !empty($threads) ? [
                'message_id' => $threads[count($threads)-1]['message_id'] ?? 'N/A',
                'thread_id' => substr($threads[count($threads)-1]['thread_id'], 0, 15) . '...',
                'subject' => substr($threads[count($threads)-1]['subject'], 0, 30),
                'received_date' => $threads[count($threads)-1]['received_date'] ?? 'N/A'
            ] : 'NONE'
        ],
        'note' => 'Returning ALL messages as separate entries - each will be a separate card'
    ]);
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'threads' => $threads // Note: This is now messages, not threads, but keeping key name for compatibility
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    $errorFile = $e->getFile();
    $errorLine = $e->getLine();
    $errorTrace = $e->getTraceAsString();
    
    logLeadSidebar('Get Lead Email History - Exception', [
        'user_id' => $userId ?? 'NOT SET',
        'lead_id' => $leadId ?? 'NOT SET',
        'error_message' => $errorMessage,
        'error_file' => $errorFile,
        'error_line' => $errorLine,
        'error_trace' => $errorTrace
    ]);
    
    error_log("Get Lead Email History Error: " . $errorMessage . " in " . $errorFile . ":" . $errorLine);
    
    // Also log PHP errors if any
    $phpErrors = error_get_last();
    if ($phpErrors) {
        logLeadSidebar('Get Lead Email History - PHP Error', [
            'user_id' => $userId ?? 'NOT SET',
            'lead_id' => $leadId ?? 'NOT SET',
            'php_error' => $phpErrors
        ]);
    }
    
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $errorMessage,
        'error_file' => $errorFile,
        'error_line' => $errorLine
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

ob_end_flush();
exit;

