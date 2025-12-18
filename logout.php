<?php
/**
 * Logout Script
 * Destroys the user session and redirects to login page
 * Created: 2025-12-18 09:49:08 UTC
 */

// Start the session to access session variables
session_start();

// Destroy all session data
session_destroy();

// Clear the session array
$_SESSION = array();

// Delete the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Redirect to login page
header("Location: login.php");
exit();
?>
