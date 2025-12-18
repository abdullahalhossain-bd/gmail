<?php
// ======================
// DATABASE CONFIGURATION
// ======================
$DB_HOST = "turbo.mywhiteserver.com";
$DB_USER = "mdshovob";
$DB_PASS = "MD@SHOVOBD";
$DB_NAME = "mdshovob_gmail";

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// ======================
// APP CONFIG
// ======================
$MAIN_DOMAIN = "mdshovobd.com";

// IMAP Catch-All inbox settings
$IMAP_HOST = "{turbo.mywhiteserver.com:993/imap/ssl}INBOX";
$IMAP_USER = "inbox@mdshovobd.com";
$IMAP_PASS = "MD@SHOVOBD";

// ======================
// SECURITY
// ======================
$API_KEY = "my_super_secure_api_key_123";

function checkApiKey($key) {
    global $API_KEY;
    if ($key !== $API_KEY) {
        die(json_encode(["error" => "Invalid API Key"]));
    }
}

// ======================
// HELPER FUNCTIONS
// ======================
function clean($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

date_default_timezone_set("Asia/Dhaka");

// CSRF Token Generation
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF Token Verification
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>