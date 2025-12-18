<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";
echo "========= IMAP DEBUG START =========\n\n";

// ===================
// CONFIG
// ===================
$IMAP_HOST = "{turbo.mywhiteserver.com:993/imap/ssl}INBOX";
$IMAP_USER = "inbox@mdshovobd.com";     // তোমার Catch-all email
$IMAP_PASS = "MD@SHOVOBD";              // Catch-all password
echo "HOST: $IMAP_HOST\n";
echo "USER: $IMAP_USER\n\n";


// ===================
// 1. CONNECTION TEST
// ===================
echo "1) Trying IMAP Connection...\n";

$inbox = @imap_open($IMAP_HOST, $IMAP_USER, $IMAP_PASS);

if (!$inbox) {
    echo "❌ IMAP Connection Failed!\n";
    echo "Error: " . imap_last_error() . "\n\n";
    exit("STOPPED!");
} else {
    echo "✅ Connected Successfully!\n\n";
}


// ===================
// 2. CHECK MAILBOX INFO
// ===================
$check = imap_check($inbox);
if ($check) {
    echo "Mailbox Info:\n";
    print_r($check);
} else {
    echo "❌ Failed to fetch mailbox info.\n";
    echo imap_last_error() . "\n";
}
echo "\n";


// ===================
// 3. LIST MAILBOXES
// ===================
echo "2) Listing Mailboxes...\n";

$mailboxes = imap_list($inbox, "{imap.gmail.com:993/imap/ssl}", "*");

if ($mailboxes === false) {
    echo "❌ Unable to list mailboxes.\n";
    echo imap_last_error() . "\n";
} else {
    print_r($mailboxes);
}
echo "\n";


// ===================
// 4. SEARCH TEST
// ===================
echo "3) Searching for ALL messages...\n";

$emails = imap_search($inbox, "ALL");

if (!$emails) {
    echo "❌ No messages found OR search failed.\n";
    echo "Error: " . imap_last_error() . "\n";
} else {
    echo "✅ Total Messages Found: " . count($emails) . "\n";
}
echo "\n";


// ===================
// 5. FETCH LAST MESSAGE TEST
// ===================
if (!empty($emails)) {
    rsort($emails);
    $msg = $emails[0];

    echo "4) Fetching latest message (#$msg)...\n";

    $overview = imap_fetch_overview($inbox, $msg, 0);

    print_r($overview);

    echo "\nBody Preview:\n";
    $body = imap_fetchbody($inbox, $msg, 1);
    echo substr($body, 0, 300); 
    echo "\n\n";
} else {
    echo "⚠️ No message to fetch.\n\n";
}

// ===================
// END
// ===================
imap_close($inbox);
echo "========= IMAP DEBUG END =========\n";
echo "</pre>";
