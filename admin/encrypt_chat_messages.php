<?php
/**
 * Chat Messages Encryption Migration Script
 * 
 * This script encrypts all existing unencrypted messages in:
 * - messages table (one-to-one chat)
 * - group_chat_messages table (group chat)
 * 
 * Usage: Run this script once to encrypt all existing messages in the database.
 * After running, all new messages will be automatically encrypted.
 * 
 * IMPORTANT: Backup your database before running this script!
 */

require_once(dirname(__FILE__) . '/../includes/loader.php');
require_once(dirname(__FILE__) . '/../includes/initialize.php');
require_once(dirname(__FILE__) . '/../includes/lib-initialize.php');

// Security check - only allow admins
if(!($session->isLoggedIn())){
    die('You must be logged in to run this script.');
}

$current_user = User::findById($session->userId);
if(!$current_user || (int)($current_user->accountStatus ?? 0) !== 1){
    die('Only administrators can run this script.');
}

// Set execution time limit for large databases
set_time_limit(0);
ini_set('memory_limit', '512M');

global $database;

$errors = [];
$success = [];
$stats = [
    'messages_processed' => 0,
    'messages_encrypted' => 0,
    'messages_skipped' => 0,
    'discussion_messages_processed' => 0,
    'discussion_messages_encrypted' => 0,
    'discussion_messages_skipped' => 0,
    'group_messages_processed' => 0,
    'group_messages_encrypted' => 0,
    'group_messages_skipped' => 0
];

// Helper function to check if a string is already encrypted
function isEncrypted($string) {
    // Encrypted strings are base64 encoded, so they have specific characteristics
    // Check if it looks like base64 and has a reasonable length
    if (empty($string)) {
        return false;
    }
    
    // Base64 strings only contain A-Z, a-z, 0-9, +, /, and = (padding)
    // Encrypted messages are typically longer and have a specific pattern
    if (preg_match('/^[A-Za-z0-9+\/]+=*$/', $string) && strlen($string) > 20) {
        // Try to decrypt - if it succeeds, it's encrypted
        try {
            $decrypted = decryptString($string);
            // If decryption succeeds and returns something different, it was encrypted
            return ($decrypted !== $string && !empty($decrypted));
        } catch(Exception $e) {
            // If decryption fails, it might not be encrypted
            return false;
        }
    }
    
    return false;
}

// Process one-to-one chat messages
echo "<h2>Encrypting One-to-One Chat Messages</h2>";
echo "<p>Processing messages table...</p>";

$messages_query = $database->query("SELECT id, message FROM messages WHERE Project_id = 0 ORDER BY id ASC");
if ($messages_query) {
    $batch_size = 100;
    $batch = [];
    
    while ($row = $database->fetchArray($messages_query)) {
        $message_id = (int)$row['id'];
        $message_text = $row['message'];
        
        $stats['messages_processed']++;
        
        // Skip if already encrypted
        if (isEncrypted($message_text)) {
            $stats['messages_skipped']++;
            continue;
        }
        
        // Skip empty messages
        if (empty(trim($message_text))) {
            $stats['messages_skipped']++;
            continue;
        }
        
        try {
            // Encrypt the message
            $encrypted_message = encryptString($message_text);
            $escaped_message = $database->escapeValue($encrypted_message);
            
            // Add to batch
            $batch[] = [
                'id' => $message_id,
                'encrypted' => $escaped_message
            ];
            
            // Process batch when it reaches batch_size
            if (count($batch) >= $batch_size) {
                foreach ($batch as $item) {
                    $update_sql = "UPDATE messages SET message = '{$item['encrypted']}' WHERE id = " . (int)$item['id'];
                    if ($database->query($update_sql)) {
                        $stats['messages_encrypted']++;
                    } else {
                        $errors[] = "Failed to encrypt message ID {$item['id']}: " . $database->error();
                    }
                }
                $batch = [];
                echo "Processed {$stats['messages_processed']} messages, encrypted {$stats['messages_encrypted']}...<br>";
                flush();
            }
        } catch(Exception $e) {
            $errors[] = "Error encrypting message ID $message_id: " . $e->getMessage();
        }
    }
    
    // Process remaining batch
    if (!empty($batch)) {
        foreach ($batch as $item) {
            $update_sql = "UPDATE messages SET message = '{$item['encrypted']}' WHERE id = " . (int)$item['id'];
            if ($database->query($update_sql)) {
                $stats['messages_encrypted']++;
            } else {
                $errors[] = "Failed to encrypt message ID {$item['id']}: " . $database->error();
            }
        }
    }
    
    $success[] = "One-to-one chat: Processed {$stats['messages_processed']} messages, encrypted {$stats['messages_encrypted']}, skipped {$stats['messages_skipped']} (already encrypted or empty).";
} else {
    $errors[] = "Failed to query messages table: " . $database->error();
}

// Process project discussion messages (Project_id > 0)
echo "<h2>Encrypting Discussion Messages</h2>";
echo "<p>Processing messages table for project discussions...</p>";

$discussion_query = $database->query("SELECT id, message FROM messages WHERE Project_id > 0 ORDER BY id ASC");
if ($discussion_query) {
    $batch_size = 100;
    $batch = [];
    
    while ($row = $database->fetchArray($discussion_query)) {
        $message_id = (int)$row['id'];
        $message_text = $row['message'];
        
        $stats['discussion_messages_processed']++;
        
        if (isEncrypted($message_text)) {
            $stats['discussion_messages_skipped']++;
            continue;
        }
        
        if (empty(trim($message_text))) {
            $stats['discussion_messages_skipped']++;
            continue;
        }
        
        try {
            $encrypted_message = encryptString($message_text);
            $escaped_message = $database->escapeValue($encrypted_message);
            
            $batch[] = [
                'id' => $message_id,
                'encrypted' => $escaped_message
            ];
            
            if (count($batch) >= $batch_size) {
                foreach ($batch as $item) {
                    $update_sql = "UPDATE messages SET message = '{$item['encrypted']}' WHERE id = " . (int)$item['id'];
                    if ($database->query($update_sql)) {
                        $stats['discussion_messages_encrypted']++;
                    } else {
                        $errors[] = "Failed to encrypt discussion message ID {$item['id']}: " . $database->error();
                    }
                }
                $batch = [];
                echo "Processed {$stats['discussion_messages_processed']} discussion messages, encrypted {$stats['discussion_messages_encrypted']}...<br>";
                flush();
            }
        } catch(Exception $e) {
            $errors[] = "Error encrypting discussion message ID $message_id: " . $e->getMessage();
        }
    }
    
    if (!empty($batch)) {
        foreach ($batch as $item) {
            $update_sql = "UPDATE messages SET message = '{$item['encrypted']}' WHERE id = " . (int)$item['id'];
            if ($database->query($update_sql)) {
                $stats['discussion_messages_encrypted']++;
            } else {
                $errors[] = "Failed to encrypt discussion message ID {$item['id']}: " . $database->error();
            }
        }
    }
    
    $success[] = "Discussion chat: Processed {$stats['discussion_messages_processed']} messages, encrypted {$stats['discussion_messages_encrypted']}, skipped {$stats['discussion_messages_skipped']} (already encrypted or empty).";
} else {
    $errors[] = "Failed to query discussion messages: " . $database->error();
}

// Process group chat messages
echo "<h2>Encrypting Group Chat Messages</h2>";
echo "<p>Processing group_chat_messages table...</p>";

$group_messages_query = $database->query("SELECT id, message FROM group_chat_messages ORDER BY id ASC");
if ($group_messages_query) {
    $batch_size = 100;
    $batch = [];
    
    while ($row = $database->fetchArray($group_messages_query)) {
        $message_id = (int)$row['id'];
        $message_text = $row['message'];
        
        $stats['group_messages_processed']++;
        
        // Skip if already encrypted
        if (isEncrypted($message_text)) {
            $stats['group_messages_skipped']++;
            continue;
        }
        
        // Skip empty messages
        if (empty(trim($message_text))) {
            $stats['group_messages_skipped']++;
            continue;
        }
        
        try {
            // Encrypt the message
            $encrypted_message = encryptString($message_text);
            $escaped_message = $database->escapeValue($encrypted_message);
            
            // Add to batch
            $batch[] = [
                'id' => $message_id,
                'encrypted' => $escaped_message
            ];
            
            // Process batch when it reaches batch_size
            if (count($batch) >= $batch_size) {
                foreach ($batch as $item) {
                    $update_sql = "UPDATE group_chat_messages SET message = '{$item['encrypted']}' WHERE id = " . (int)$item['id'];
                    if ($database->query($update_sql)) {
                        $stats['group_messages_encrypted']++;
                    } else {
                        $errors[] = "Failed to encrypt group message ID {$item['id']}: " . $database->error();
                    }
                }
                $batch = [];
                echo "Processed {$stats['group_messages_processed']} group messages, encrypted {$stats['group_messages_encrypted']}...<br>";
                flush();
            }
        } catch(Exception $e) {
            $errors[] = "Error encrypting group message ID $message_id: " . $e->getMessage();
        }
    }
    
    // Process remaining batch
    if (!empty($batch)) {
        foreach ($batch as $item) {
            $update_sql = "UPDATE group_chat_messages SET message = '{$item['encrypted']}' WHERE id = " . (int)$item['id'];
            if ($database->query($update_sql)) {
                $stats['group_messages_encrypted']++;
            } else {
                $errors[] = "Failed to encrypt group message ID {$item['id']}: " . $database->error();
            }
        }
    }
    
    $success[] = "Group chat: Processed {$stats['group_messages_processed']} messages, encrypted {$stats['group_messages_encrypted']}, skipped {$stats['group_messages_skipped']} (already encrypted or empty).";
} else {
    $errors[] = "Failed to query group_chat_messages table: " . $database->error();
}

// Display results
?>
<!DOCTYPE html>
<html>
<head>
    <title>Chat Messages Encryption - Results</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .success { color: green; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .stats { background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .stats h3 { margin-top: 0; }
        .stats table { width: 100%; border-collapse: collapse; }
        .stats td { padding: 8px; border-bottom: 1px solid #ccc; }
        .stats td:first-child { font-weight: bold; }
    </style>
</head>
<body>
    <h1>Chat Messages Encryption - Migration Complete</h1>
    
    <div class="stats">
        <h3>Statistics</h3>
        <table>
            <tr>
                <td>One-to-One Messages Processed:</td>
                <td><?php echo number_format($stats['messages_processed']); ?></td>
            </tr>
            <tr>
                <td>One-to-One Messages Encrypted:</td>
                <td><?php echo number_format($stats['messages_encrypted']); ?></td>
            </tr>
            <tr>
                <td>One-to-One Messages Skipped:</td>
                <td><?php echo number_format($stats['messages_skipped']); ?></td>
            </tr>
            <tr>
                <td>Discussion Messages Processed:</td>
                <td><?php echo number_format($stats['discussion_messages_processed']); ?></td>
            </tr>
            <tr>
                <td>Discussion Messages Encrypted:</td>
                <td><?php echo number_format($stats['discussion_messages_encrypted']); ?></td>
            </tr>
            <tr>
                <td>Discussion Messages Skipped:</td>
                <td><?php echo number_format($stats['discussion_messages_skipped']); ?></td>
            </tr>
            <tr>
                <td>Group Messages Processed:</td>
                <td><?php echo number_format($stats['group_messages_processed']); ?></td>
            </tr>
            <tr>
                <td>Group Messages Encrypted:</td>
                <td><?php echo number_format($stats['group_messages_encrypted']); ?></td>
            </tr>
            <tr>
                <td>Group Messages Skipped:</td>
                <td><?php echo number_format($stats['group_messages_skipped']); ?></td>
            </tr>
        </table>
    </div>
    
    <?php if (!empty($success)): ?>
        <h3>Success Messages</h3>
        <?php foreach ($success as $msg): ?>
            <div class="success"><?php echo htmlspecialchars($msg); ?></div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <h3>Errors</h3>
        <?php foreach ($errors as $error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (empty($errors)): ?>
        <div class="success">
            <strong>Migration completed successfully!</strong><br>
            All unencrypted messages have been encrypted. New messages will be automatically encrypted when sent.
        </div>
    <?php endif; ?>
    
    <p><a href="index">Back to Admin Panel</a></p>
</body>
</html>
<?php
exit;
?>

