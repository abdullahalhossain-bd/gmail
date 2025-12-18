<?php
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// ========== SEARCH ===========
$search = "";
if (isset($_GET['search'])) {
    $search = clean($_GET['search']);
}

// Fetch emails with prepared statement
$stmt = $conn->prepare("SELECT * FROM temp_emails WHERE email LIKE ? ORDER BY id DESC");
$searchParam = "%$search%";
$stmt->bind_param("s", $searchParam);
$stmt->execute();
$result = $stmt->get_result();

// ========== AUTO EMAIL GENERATOR ===========
function generateEmail() {
    global $MAIN_DOMAIN;
    $rand = substr(md5(uniqid(mt_rand(), true)), 0, 8);
    return $rand . "@" . $MAIN_DOMAIN;
}

// When user presses NEXT in dialog
if (isset($_POST['add_email'])) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die("Invalid request");
    }
    
    $email = clean($_POST['generated_email']);
    
    // Check if email already exists
    $check_stmt = $conn->prepare("SELECT id FROM temp_emails WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO temp_emails (email, recent_message) VALUES (?, '')");
        $stmt->bind_param("s", $email);
        $stmt->execute();
    }
    
    header("Location: home.php");
    exit;
}

// DELETE email
if (isset($_GET['delete'])) {
    // CSRF Protection for GET requests
    if (!isset($_GET['token']) || !verifyCSRFToken($_GET['token'])) {
        die("Invalid request");
    }
    
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM temp_emails WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    header("Location: home.php");
    exit;
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Temp Email</title>
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
        }
        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }
        .logout-btn:hover {
            background: #c82333;
        }
        .search-box {
            padding: 15px 20px;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .search-box input {
            width: 100%; 
            padding: 12px;
            border: 1px solid #ccc; 
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 16px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .item {
            background: white; 
            margin: 10px 0;
            padding: 15px; 
            border-radius: 10px;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
            position: relative;
        }
        .email { 
            font-size: 18px; 
            font-weight: bold; 
            color: #333;
        }
        .recent { 
            color: #666; 
            margin-top: 5px; 
            font-size: 14px;
        }
        .delete {
            position: absolute;
            top: 15px;
            right: 15px;
            color: red; 
            cursor: pointer;
            font-size: 20px;
            text-decoration: none;
        }
        .delete:hover {
            color: darkred;
        }
        .item a.email-link {
            text-decoration: none;
            color: inherit;
            display: block;
            padding-right: 40px;
        }
        .add-btn {
            position: fixed; 
            bottom: 20px; 
            right: 20px;
            background: #007bff; 
            color: white;
            width: 60px; 
            height: 60px; 
            border-radius: 50%;
            font-size: 35px; 
            text-align: center;
            line-height: 55px; 
            cursor: pointer;
            box-shadow: 0 3px 8px rgba(0,0,0,0.3);
            user-select: none;
        }
        .add-btn:hover {
            background: #0056b3;
        }
        .dialog-bg {
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex; 
            align-items: center; 
            justify-content: center;
            z-index: 1000;
        }
        .dialog-box {
            background: white; 
            padding: 30px; 
            width: 90%;
            max-width: 400px;
            border-radius: 10px; 
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .dialog-box h3 {
            margin-top: 0;
        }
        .email-container {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .dialog-box .generated-email {
            flex: 1;
            word-break: break-all;
            font-family: monospace;
            font-size: 16px;
            text-align: left;
        }
        .copy-btn {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            white-space: nowrap;
            transition: background 0.3s;
        }
        .copy-btn:hover {
            background: #138496;
        }
        .copy-btn.copied {
            background: #28a745;
        }
        button {
            padding: 12px 30px; 
            background: #28a745;
            color: white; 
            border: none; 
            border-radius: 5px;
            cursor: pointer; 
            margin: 5px;
            font-size: 16px;
        }
        button:hover {
            background: #218838;
        }
        .btn-cancel {
            background: #6c757d;
        }
        .btn-cancel:hover {
            background: #5a6268;
        }
        .no-emails {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        /* Notification Styles */
        .notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 2000;
            max-width: 400px;
        }
        .notification {
            background: white;
            border-left: 4px solid #007bff;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideIn 0.3s ease-out;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .notification:hover {
            transform: translateX(-5px);
        }
        .notification.otp {
            border-left-color: #28a745;
        }
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .notification-title {
            font-weight: bold;
            color: #333;
            font-size: 14px;
        }
        .notification-time {
            font-size: 12px;
            color: #999;
        }
        .notification-email {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
        }
        .notification-subject {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }
        .notification-otp {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
            font-family: monospace;
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            text-align: center;
            letter-spacing: 3px;
        }
        .notification-close {
            background: none;
            border: none;
            color: #999;
            font-size: 20px;
            cursor: pointer;
            padding: 0;
            margin: 0;
            width: 20px;
            height: 20px;
            line-height: 20px;
        }
        .notification-close:hover {
            color: #333;
            background: none;
        }
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
        .notification.removing {
            animation: slideOut 0.3s ease-out forwards;
        }
    </style>
</head>
<body>
<!-- Notification Container -->
<div class="notification-container" id="notificationContainer"></div>

<!-- HEADER -->
<div class="header">
    <h2 style="margin: 0;">Temp Email System</h2>
    <a href="logout.php" class="logout-btn">Logout</a>
</div>

<!-- SEARCH BAR -->
<div class="search-box">
    <form method="GET">
        <input type="text" name="search" placeholder="Search email..." value="<?= htmlspecialchars($search) ?>">
    </form>
</div>

<!-- EMAIL LIST -->
<div class="container">
    <?php if ($result->num_rows === 0): ?>
        <div class="no-emails">
            <p>No emails found. Click the + button to create one!</p>
        </div>
    <?php else: ?>
        <?php while ($row = $result->fetch_assoc()): ?>
        <div class="item">
            <a class="delete" href="home.php?delete=<?= $row['id'] ?>&token=<?= urlencode($csrf_token) ?>" onclick="return confirm('Delete this email?')">🗑️</a>
            <a href="view_email.php?id=<?= $row['id'] ?>" class="email-link">
                <div class="email"><?= htmlspecialchars($row['email']) ?></div>
                <div class="recent"><?= htmlspecialchars($row['recent_message'] ?: "No messages yet") ?></div>
            </a>
        </div>
        <?php endwhile; ?>
    <?php endif; ?>
</div>

<!-- Floating + Button -->
<div class="add-btn" onclick="showDialog()">+</div>

<!-- Dialog (hidden by default) -->
<div id="dialog" style="display:none;">
    <div class="dialog-bg" onclick="hideDialog(event)">
        <div class="dialog-box" onclick="event.stopPropagation()">
            <h3>Create New Email</h3>
            <?php $generated = generateEmail(); ?>
            <div class="email-container">
                <div class="generated-email" id="generatedEmail"><?= htmlspecialchars($generated) ?></div>
                <button type="button" class="copy-btn" onclick="copyEmail(event)">Copy</button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="generated_email" value="<?= htmlspecialchars($generated) ?>">
                <button type="submit" name="add_email">Create Email</button>
                <button type="button" class="btn-cancel" onclick="hideDialog(event)">Cancel</button>
            </form>
        </div>
    </div>
</div>

<script>
// Dialog functions
function showDialog() {
    document.getElementById("dialog").style.display = "block";
    document.body.style.overflow = "hidden";
}

function hideDialog(event) {
    event.preventDefault();
    document.getElementById("dialog").style.display = "none";
    document.body.style.overflow = "auto";
}

// Copy email function
function copyEmail(event) {
    event.preventDefault();
    const emailText = document.getElementById("generatedEmail").textContent;
    const copyBtn = event.target;
    
    navigator.clipboard.writeText(emailText).then(() => {
        const originalText = copyBtn.textContent;
        copyBtn.textContent = "Copied!";
        copyBtn.classList.add("copied");
        
        setTimeout(() => {
            copyBtn.textContent = originalText;
            copyBtn.classList.remove("copied");
        }, 2000);
    }).catch(err => {
        console.error('Failed to copy:', err);
        alert('Failed to copy email');
    });
}

// Notification System
let lastCheckedId = 0;
let notificationSound = null;

// Request notification permission on page load
if ("Notification" in window && Notification.permission === "default") {
    Notification.requestPermission();
}

// Function to extract OTP from message
function extractOTP(text) {
    // Common OTP patterns: 4-8 digits, often preceded by keywords
    const patterns = [
        /\b(\d{4,8})\b.*(?:OTP|code|verification|token|pin)/i,
        /(?:OTP|code|verification|token|pin)[:\s]*(\d{4,8})/i,
        /\b(\d{6})\b/,  // 6-digit numbers are commonly OTPs
        /\b(\d{4})\b/   // 4-digit codes
    ];
    
    for (let pattern of patterns) {
        const match = text.match(pattern);
        if (match) {
            return match[1];
        }
    }
    return null;
}

// Function to show notification
function showNotification(email, subject, message, emailId) {
    const container = document.getElementById('notificationContainer');
    const notification = document.createElement('div');
    const otp = extractOTP(subject + ' ' + message);
    
    notification.className = 'notification' + (otp ? ' otp' : '');
    
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    
    notification.innerHTML = `
        <div class="notification-header">
            <span class="notification-title">${otp ? '🔐 New OTP Received' : '📧 New Email'}</span>
            <button class="notification-close" onclick="closeNotification(this)">×</button>
        </div>
        <div class="notification-email">${escapeHtml(email)}</div>
        <div class="notification-subject">${escapeHtml(subject.substring(0, 50))}${subject.length > 50 ? '...' : ''}</div>
        ${otp ? `<div class="notification-otp">${otp}</div>` : ''}
        <div class="notification-time">${timeString}</div>
    `;
    
    notification.onclick = function(e) {
        if (e.target.className !== 'notification-close') {
            window.location.href = 'view_email.php?id=' + emailId;
        }
    };
    
    container.insertBefore(notification, container.firstChild);
    
    // Browser notification if permission granted
    if ("Notification" in window && Notification.permission === "granted" && document.hidden) {
        new Notification(otp ? 'New OTP Received' : 'New Email', {
            body: otp ? `OTP: ${otp}\n${email}` : `${email}\n${subject}`,
            icon: '/favicon.ico',
            tag: 'email-' + emailId
        });
    }
    
    // Play sound
    playNotificationSound();
    
    // Auto-remove after 10 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.classList.add('removing');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }
    }, 10000);
}

function closeNotification(btn) {
    const notification = btn.closest('.notification');
    notification.classList.add('removing');
    setTimeout(() => {
        notification.remove();
    }, 300);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Play notification sound
function playNotificationSound() {
    // Create audio context for beep sound
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.value = 800;
        oscillator.type = 'sine';
        
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.5);
    } catch (e) {
        console.log('Audio not supported');
    }
}

// Check for new emails
function checkNewEmails() {
    fetch('check_new_emails.php?last_id=' + lastCheckedId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.emails && data.emails.length > 0) {
                data.emails.forEach(email => {
                    showNotification(email.to_email, email.subject, email.message, email.email_id);
                    lastCheckedId = Math.max(lastCheckedId, email.id);
                });
            }
        })
        .catch(error => console.error('Error checking emails:', error));
}

// Check every 5 seconds
setInterval(checkNewEmails, 5000);

// Initial check
setTimeout(checkNewEmails, 1000);
</script>
</body>
</html>