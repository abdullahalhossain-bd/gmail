<?php
/**
 * IMAP Email Receiver and Database Storage
 * 
 * This script connects to Gmail via IMAP, retrieves emails,
 * and stores them in the database.
 * 
 * @author Abdullah Al Hossain
 * @date 2025-12-18
 */

// Database configuration
$db_host = 'localhost';
$db_username = 'root';
$db_password = '';
$db_name = 'gmail_db';

// Gmail IMAP configuration
$imap_host = '{imap.gmail.com:993/imap/ssl}';
$gmail_email = 'your_email@gmail.com';
$gmail_password = 'your_app_password'; // Use App Password, not regular password

/**
 * Connect to the database
 */
function connectDatabase() {
    global $db_host, $db_username, $db_password, $db_name;
    
    try {
        $pdo = new PDO(
            "mysql:host=$db_host;dbname=$db_name",
            $db_username,
            $db_password,
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection error: " . $e->getMessage());
    }
}

/**
 * Connect to Gmail via IMAP
 */
function connectIMAP($imap_host, $email, $password) {
    $mailbox = @imap_open($imap_host . 'INBOX', $email, $password);
    
    if (!$mailbox) {
        die("IMAP connection error: " . imap_last_error());
    }
    
    return $mailbox;
}

/**
 * Decode MIME encoded text
 */
function decodeMimeStr($mimeStr) {
    $mimeStr = imap_mime_header_decode($mimeStr);
    $stringStr = '';
    
    foreach ($mimeStr as $obj) {
        if ($obj->charset == 'default') {
            $stringStr .= $obj->text;
        } else {
            $stringStr .= iconv($obj->charset, 'utf-8', $obj->text);
        }
    }
    
    return $stringStr;
}

/**
 * Get email body (plain text or HTML)
 */
function getEmailBody($mailbox, $messageId) {
    $body = '';
    
    // Try to get plain text first
    $plainBody = imap_fetchbody($mailbox, $messageId, '1');
    if (!empty($plainBody)) {
        $body = $plainBody;
    } else {
        // If no plain text, try HTML
        $htmlBody = imap_fetchbody($mailbox, $messageId, '1.1');
        if (!empty($htmlBody)) {
            $body = $htmlBody;
            // Strip HTML tags
            $body = strip_tags($body);
        }
    }
    
    // Decode quoted-printable or base64
    $body = quoted_printable_decode($body);
    
    return trim($body);
}

/**
 * Store email in database
 */
function storeEmail($pdo, $from, $to, $subject, $body, $date, $messageId) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO emails (from_email, to_email, subject, body, received_date, message_id, created_at)
            VALUES (:from_email, :to_email, :subject, :body, :received_date, :message_id, NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW()
        ");
        
        $stmt->bindParam(':from_email', $from);
        $stmt->bindParam(':to_email', $to);
        $stmt->bindParam(':subject', $subject);
        $stmt->bindParam(':body', $body);
        $stmt->bindParam(':received_date', $date);
        $stmt->bindParam(':message_id', $messageId);
        
        $stmt->execute();
        return true;
    } catch (PDOException $e) {
        echo "Error storing email: " . $e->getMessage() . "\n";
        return false;
    }
}

/**
 * Receive and store emails
 */
function receiveEmails() {
    global $imap_host, $gmail_email, $gmail_password;
    
    // Connect to database and IMAP
    $pdo = connectDatabase();
    $mailbox = connectIMAP($imap_host, $gmail_email, $gmail_password);
    
    // Get all emails
    $emails = imap_search($mailbox, 'ALL');
    $count = 0;
    $stored = 0;
    
    if ($emails) {
        // Process emails in reverse order (newest first)
        rsort($emails);
        
        foreach ($emails as $email_id) {
            $count++;
            
            // Get email header
            $header = imap_headerinfo($mailbox, $email_id);
            
            // Extract email details
            $from = isset($header->from[0]->mailbox, $header->from[0]->host) 
                ? $header->from[0]->mailbox . '@' . $header->from[0]->host 
                : 'unknown@unknown.com';
            
            $to = isset($header->to[0]->mailbox, $header->to[0]->host) 
                ? $header->to[0]->mailbox . '@' . $header->to[0]->host 
                : $gmail_email;
            
            $subject = isset($header->subject) 
                ? decodeMimeStr($header->subject) 
                : '(No Subject)';
            
            $date = isset($header->date) 
                ? $header->date 
                : date('Y-m-d H:i:s');
            
            $messageId = isset($header->message_id) 
                ? $header->message_id 
                : uniqid();
            
            // Get email body
            $body = getEmailBody($mailbox, $email_id);
            
            // Store email in database
            if (storeEmail($pdo, $from, $to, $subject, $body, $date, $messageId)) {
                $stored++;
                echo "Stored: $subject from $from\n";
            }
        }
        
        echo "\n--- Summary ---\n";
        echo "Total emails processed: $count\n";
        echo "Successfully stored: $stored\n";
    } else {
        echo "No emails found in INBOX\n";
    }
    
    // Close IMAP connection
    imap_close($mailbox);
    
    return true;
}

/**
 * Main execution
 */
if (php_sapi_name() === 'cli') {
    echo "Starting email receive process...\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n";
    echo "---\n\n";
    
    receiveEmails();
    
    echo "\n---\nProcess completed!\n";
} else {
    // If accessed via web, require authentication
    if (isset($_GET['action']) && $_GET['action'] === 'receive') {
        echo "Starting email receive process...\n";
        receiveEmails();
    } else {
        echo '<p>Access denied. Run this script from command line or pass ?action=receive</p>';
    }
}
?>
