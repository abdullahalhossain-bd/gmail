<?php
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("Email ID not specified.");
}

$id = intval($_GET['id']);

// Fetch email from database
$stmt = $conn->prepare("SELECT email FROM temp_emails WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Email not found in database.");
}

$row = $result->fetch_assoc();
$email = $row['email'];

// ========================
// IMAP থেকে মেসেজ ফেচ - IMPROVED METHOD
// ========================
$messages = [];
$imap_error = "";
$debug_info = "";

// Try to connect to IMAP
$inbox = @imap_open($IMAP_HOST, $IMAP_USER, $IMAP_PASS);

if (!$inbox) {
    $imap_error = "Cannot connect to IMAP: " . imap_last_error();
} else {
    // Method 1: Try specific search first
    $safe_email = str_replace('"', '', $email); // Remove quotes completely
    $search_queries = [
        'TO "' . $safe_email . '"',
        'TO ' . $safe_email,
        'ALL'
    ];
    
    $found_emails = false;
    
    foreach ($search_queries as $search_query) {
        $emails = @imap_search($inbox, $search_query);
        
        if ($emails !== false && !empty($emails)) {
            $found_emails = true;
            $debug_info = "Search method: $search_query - Found " . count($emails) . " emails";
            break;
        }
    }
    
    if (!$found_emails) {
        $imap_error = "No emails found. IMAP says: " . imap_last_error();
    } else {
        rsort($emails); // সর্বশেষ মেসেজ আগে
        
        foreach ($emails as $msg_number) {
            $header = imap_headerinfo($inbox, $msg_number);
            
            if (!$header) {
                continue;
            }
            
            // Check if this email is actually for our address
            $to_match = false;
            if (isset($header->to)) {
                foreach ($header->to as $to) {
                    $to_address = isset($to->mailbox) && isset($to->host) ? $to->mailbox . '@' . $to->host : '';
                    if (stripos($to_address, $email) !== false || stripos($email, $to_address) !== false) {
                        $to_match = true;
                        break;
                    }
                }
            }
            
            // If searching ALL, skip emails not for this address
            if ($search_query === 'ALL' && !$to_match) {
                continue;
            }
            
            // Get email structure
            $structure = imap_fetchstructure($inbox, $msg_number);
            
            // Try to get the body
            $body = '';
            
            // Check if multipart
            if (isset($structure->parts) && count($structure->parts)) {
                // Multipart email
                for ($i = 0; $i < count($structure->parts); $i++) {
                    $part = $structure->parts[$i];
                    
                    // Check if it's text/plain or text/html
                    if ($part->subtype == 'PLAIN' || $part->subtype == 'HTML') {
                        $body = imap_fetchbody($inbox, $msg_number, $i + 1);
                        
                        // Decode based on encoding
                        if (isset($part->encoding)) {
                            if ($part->encoding == 3) { // Base64
                                $body = base64_decode($body);
                            } elseif ($part->encoding == 4) { // Quoted-printable
                                $body = quoted_printable_decode($body);
                            }
                        }
                        
                        if (!empty($body)) {
                            break;
                        }
                    }
                }
            } else {
                // Simple email - not multipart
                $body = imap_body($inbox, $msg_number);
                
                // Try to decode
                if (isset($structure->encoding)) {
                    if ($structure->encoding == 3) {
                        $body = base64_decode($body);
                    } elseif ($structure->encoding == 4) {
                        $body = quoted_printable_decode($body);
                    }
                }
            }
            
            // If still no body, try fetchbody with different parts
            if (empty($body)) {
                $body = imap_fetchbody($inbox, $msg_number, 1);
                if (empty($body)) {
                    $body = imap_fetchbody($inbox, $msg_number, 2);
                }
                
                // Try decoding
                if (base64_decode($body, true) !== false && base64_encode(base64_decode($body, true)) === $body) {
                    $body = base64_decode($body);
                } else {
                    $body = quoted_printable_decode($body);
                }
            }
            
            // Get from address
            $from = 'Unknown';
            if (isset($header->from)) {
                foreach ($header->from as $from_obj) {
                    $from = isset($from_obj->mailbox) && isset($from_obj->host) 
                        ? $from_obj->mailbox . '@' . $from_obj->host 
                        : (isset($from_obj->personal) ? $from_obj->personal : 'Unknown');
                    break;
                }
            }
            
            $messages[] = [
                'subject' => isset($header->subject) ? $header->subject : 'No Subject',
                'from'    => $from,
                'date'    => isset($header->date) ? $header->date : 'Unknown Date',
                'body'    => $body ?: '(Empty body)',
                'msg_number' => $msg_number
            ];
        }
        
        // Update recent_message in database
        if (!empty($messages)) {
            $recent = substr($messages[0]['subject'], 0, 50);
            $update_stmt = $conn->prepare("UPDATE temp_emails SET recent_message = ? WHERE id = ?");
            $update_stmt->bind_param("si", $recent, $id);
            $update_stmt->execute();
        }
    }
    
    imap_close($inbox);
}

// Auto-refresh every 30 seconds
$auto_refresh = isset($_GET['refresh']) ? true : false;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($auto_refresh): ?>
    <meta http-equiv="refresh" content="30">
    <?php endif; ?>
    <title>Inbox: <?= htmlspecialchars($email) ?></title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background: #f4f4f4; 
            margin: 0; 
            padding: 0; 
        }
        .header {
            background: white;
            padding: 15px 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .container { 
            max-width: 900px; 
            margin: 20px auto; 
            background: #fff; 
            padding: 20px; 
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .email-address {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            word-break: break-all;
        }
        .controls {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
        }
        .btn-back {
            background: #007bff;
            color: white;
        }
        .btn-back:hover {
            background: #0056b3;
        }
        .btn-refresh {
            background: #28a745;
            color: white;
        }
        .btn-refresh:hover {
            background: #218838;
        }
        .btn-test {
            background: #17a2b8;
            color: white;
        }
        .btn-test:hover {
            background: #138496;
        }
        .msg-box { 
            padding: 20px; 
            margin-bottom: 15px; 
            border: 1px solid #ddd; 
            border-radius: 8px; 
            background: #fafafa; 
        }
        .msg-header { 
            font-weight: bold; 
            margin-bottom: 10px; 
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        .msg-header div {
            margin: 5px 0;
        }
        .msg-body { 
            margin-top: 15px; 
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.6;
            max-height: 400px;
            overflow-y: auto;
        }
        .no-messages {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .debug {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 12px;
        }
        hr {
            border: none;
            border-top: 1px solid #e0e0e0;
            margin: 20px 0;
        }
        .test-section {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .test-section code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
<div class="header">
    <div class="email-address"><?= htmlspecialchars($email) ?></div>
    <div class="controls">
        <a href="home.php" class="btn btn-back">← Back</a>
        <a href="view_email.php?id=<?= $id ?>&refresh=1" class="btn btn-refresh">🔄 Auto-Refresh</a>
        <a href="test_imap.php" class="btn btn-test" target="_blank">🔧 Test IMAP</a>
    </div>
</div>

<div class="container">
    <h2>Inbox</h2>
    
    <div class="test-section">
        <strong>📧 To receive emails:</strong><br>
        Send a test email to: <code><?= htmlspecialchars($email) ?></code><br>
        <small>Make sure your catch-all email (<code><?= htmlspecialchars($IMAP_USER) ?></code>) is properly configured.</small>
    </div>
    
    <?php if ($debug_info): ?>
        <div class="debug">
            <strong>Debug:</strong> <?= htmlspecialchars($debug_info) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($imap_error): ?>
        <div class="error">
            <strong>Connection Error:</strong> <?= htmlspecialchars($imap_error) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($auto_refresh): ?>
        <div class="info">
            ⏱️ Auto-refresh enabled. Page will reload every 30 seconds.
        </div>
    <?php endif; ?>
    
    <hr>
    
    <?php if (empty($messages) && empty($imap_error)): ?>
        <div class="no-messages">
            <p>📭 No messages found for this email.</p>
            <p>Send an email to <strong><?= htmlspecialchars($email) ?></strong> to test!</p>
        </div>
    <?php elseif (empty($messages) && !empty($imap_error)): ?>
        <div class="no-messages">
            <p>⚠️ Could not retrieve messages due to connection error.</p>
            <p>Please check your IMAP settings and try again.</p>
        </div>
    <?php else: ?>
        <p><strong><?= count($messages) ?></strong> message(s) found</p>
        <?php foreach ($messages as $msg): ?>
            <div class="msg-box">
                <div class="msg-header">
                    <div><strong>From:</strong> <?= htmlspecialchars($msg['from']) ?></div>
                    <div><strong>Subject:</strong> <?= htmlspecialchars($msg['subject']) ?></div>
                    <div><strong>Date:</strong> <?= htmlspecialchars($msg['date']) ?></div>
                </div>
                <div class="msg-body">
                    <?= nl2br(htmlspecialchars($msg['body'])) ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>