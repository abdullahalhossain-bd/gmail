<?php
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$results = [];
$step = 1;

// Step 1: Test IMAP connection
$results[] = "=== STEP 1: Testing IMAP Connection ===";
$results[] = "Host: " . $IMAP_HOST;
$results[] = "User: " . $IMAP_USER;
$results[] = "Password: " . str_repeat("*", strlen($IMAP_PASS));

$inbox = @imap_open($IMAP_HOST, $IMAP_USER, $IMAP_PASS);

if (!$inbox) {
    $results[] = "❌ FAILED: " . imap_last_error();
    $results[] = "\nPossible issues:";
    $results[] = "1. IMAP credentials are incorrect";
    $results[] = "2. IMAP is not enabled on the server";
    $results[] = "3. Port 993 is blocked";
    $results[] = "4. SSL certificate issues";
} else {
    $results[] = "✅ SUCCESS: Connected to IMAP server!";
    $step++;
    
    // Step 2: Get mailbox info
    $results[] = "\n=== STEP 2: Mailbox Information ===";
    $check = imap_check($inbox);
    $results[] = "Total messages: " . $check->Nmsgs;
    $results[] = "Recent messages: " . $check->Recent;
    $results[] = "Unread messages: " . $check->Unseen;
    $results[] = "Mailbox: " . $check->Mailbox;
    
    if ($check->Nmsgs > 0) {
        $step++;
        
        // Step 3: List recent emails
        $results[] = "\n=== STEP 3: Recent Emails (Last 5) ===";
        $emails = imap_search($inbox, 'ALL');
        
        if ($emails) {
            rsort($emails);
            $count = 0;
            
            foreach ($emails as $msg_num) {
                if ($count >= 5) break;
                
                $header = imap_headerinfo($inbox, $msg_num);
                
                $to = 'N/A';
                if (isset($header->to)) {
                    foreach ($header->to as $to_obj) {
                        $to = isset($to_obj->mailbox) && isset($to_obj->host) 
                            ? $to_obj->mailbox . '@' . $to_obj->host 
                            : 'Unknown';
                        break;
                    }
                }
                
                $from = 'N/A';
                if (isset($header->from)) {
                    foreach ($header->from as $from_obj) {
                        $from = isset($from_obj->mailbox) && isset($from_obj->host) 
                            ? $from_obj->mailbox . '@' . $from_obj->host 
                            : 'Unknown';
                        break;
                    }
                }
                
                $results[] = "\nEmail #" . ($count + 1) . ":";
                $results[] = "  To: " . $to;
                $results[] = "  From: " . $from;
                $results[] = "  Subject: " . (isset($header->subject) ? $header->subject : 'No Subject');
                $results[] = "  Date: " . (isset($header->date) ? $header->date : 'Unknown');
                
                $count++;
            }
            $step++;
        } else {
            $results[] = "No emails found or search failed: " . imap_last_error();
        }
        
        // Step 4: Test specific search
        $results[] = "\n=== STEP 4: Testing Email Search ===";
        $test_searches = [
            'ALL',
            'RECENT',
            'UNSEEN'
        ];
        
        foreach ($test_searches as $search_term) {
            $search_result = @imap_search($inbox, $search_term);
            if ($search_result) {
                $results[] = "Search '$search_term': Found " . count($search_result) . " emails";
            } else {
                $results[] = "Search '$search_term': No results or failed - " . imap_last_error();
            }
        }
        
    } else {
        $results[] = "\n⚠️ No emails in mailbox. Send a test email to: " . $IMAP_USER;
    }
    
    // Get your temp emails and test searches
    $results[] = "\n=== STEP 5: Testing Your Temp Emails ===";
    $stmt = $conn->prepare("SELECT email FROM temp_emails LIMIT 3");
    $stmt->execute();
    $temp_result = $stmt->get_result();
    
    if ($temp_result->num_rows > 0) {
        while ($row = $temp_result->fetch_assoc()) {
            $test_email = $row['email'];
            $search = @imap_search($inbox, 'TO "' . $test_email . '"');
            
            if ($search) {
                $results[] = "✅ Email '$test_email': Found " . count($search) . " message(s)";
            } else {
                $results[] = "❌ Email '$test_email': No messages found";
            }
        }
    } else {
        $results[] = "No temp emails created yet.";
    }
    
    imap_close($inbox);
}

// Check PHP IMAP extension
$results[] = "\n=== PHP IMAP Extension ===";
if (function_exists('imap_open')) {
    $results[] = "✅ PHP IMAP extension is installed";
} else {
    $results[] = "❌ PHP IMAP extension is NOT installed!";
    $results[] = "Contact your hosting provider to enable it.";
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMAP Connection Test</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #00ff00;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #000;
            padding: 20px;
            border-radius: 5px;
            border: 1px solid #333;
        }
        h1 {
            color: #00ff00;
            border-bottom: 2px solid #00ff00;
            padding-bottom: 10px;
        }
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.6;
        }
        .success {
            color: #00ff00;
        }
        .error {
            color: #ff0000;
        }
        .warning {
            color: #ffff00;
        }
        .btn {
            background: #00ff00;
            color: #000;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            font-weight: bold;
        }
        .btn:hover {
            background: #00cc00;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 IMAP Connection Test Results</h1>
    <pre><?php
foreach ($results as $result) {
    if (strpos($result, '✅') !== false || strpos($result, 'SUCCESS') !== false) {
        echo '<span class="success">' . htmlspecialchars($result) . '</span>' . "\n";
    } elseif (strpos($result, '❌') !== false || strpos($result, 'FAILED') !== false) {
        echo '<span class="error">' . htmlspecialchars($result) . '</span>' . "\n";
    } elseif (strpos($result, '⚠️') !== false) {
        echo '<span class="warning">' . htmlspecialchars($result) . '</span>' . "\n";
    } else {
        echo htmlspecialchars($result) . "\n";
    }
}
    ?></pre>
    
    <a href="home.php" class="btn">← Back to Home</a>
    <a href="test_imap.php" class="btn">🔄 Refresh Test</a>
</div>
</body>
</html>