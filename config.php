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

// ======================
// APP CONFIG
// ======================
// Your main temp-mail domain

// IMAP Catch-All inbox settings
$IMAP_HOST = "{turbo.mywhiteserver.com:993/imap/ssl}INBOX";
$IMAP_USER = "inbox@mdshovobd.com";     // তোমার Catch-all email
$IMAP_PASS = "MD@SHOVOBD";              // Catch-all password

// ======================
// SECURITY (Optional)
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
    return htmlspecialchars(strip_tags($data));
}

date_default_timezone_set("Asia/Dhaka");
?>
