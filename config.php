<?php
/**
 * Application Configuration File
 * 
 * This file contains all configuration settings for the Gmail application.
 * Sensitive data should be stored in environment variables for security.
 * 
 * @package Gmail Application
 * @version 1.0.0
 * @author Abdullah Al Hossain
 * @date 2025-12-18
 */

// ============================================================================
// ENVIRONMENT VARIABLE LOADING
// ============================================================================

// Load environment variables from .env file if it exists
if (file_exists(__DIR__ . '/.env')) {
    $env_file = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env_file as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse key=value pairs
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                $value = substr($value, 1, -1);
            }
            
            // Set environment variable
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Determine application environment
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('APP_DEBUG', getenv('APP_DEBUG') === 'true' || getenv('APP_DEBUG') === '1');

// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================

$config['database'] = [
    'driver' => getenv('DB_DRIVER') ?: 'mysql',
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => (int)(getenv('DB_PORT') ?: 3306),
    'username' => getenv('DB_USERNAME') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'database' => getenv('DB_DATABASE') ?: 'gmail_app',
    'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    'collation' => getenv('DB_COLLATION') ?: 'utf8mb4_unicode_ci',
    'prefix' => getenv('DB_PREFIX') ?: '',
    
    // Connection options
    'options' => [
        'persistent' => false,
        'pool_size' => 10,
        'timeout' => 30,
        'ssl' => [
            'enabled' => getenv('DB_SSL_ENABLED') === 'true',
            'verify_certificate' => getenv('DB_SSL_VERIFY') === 'true',
            'ca_path' => getenv('DB_SSL_CA_PATH') ?: '',
        ],
    ],
];

// ============================================================================
// IMAP SETTINGS
// ============================================================================

$config['imap'] = [
    // IMAP Server Configuration
    'server' => getenv('IMAP_SERVER') ?: 'imap.gmail.com',
    'port' => (int)(getenv('IMAP_PORT') ?: 993),
    'protocol' => getenv('IMAP_PROTOCOL') ?: 'imap',
    'encryption' => getenv('IMAP_ENCRYPTION') ?: 'ssl', // 'ssl', 'tls', or 'none'
    
    // Authentication
    'username' => getenv('IMAP_USERNAME') ?: '',
    'password' => getenv('IMAP_PASSWORD') ?: '',
    'use_oauth2' => getenv('IMAP_USE_OAUTH2') === 'true',
    'oauth2_token' => getenv('IMAP_OAUTH2_TOKEN') ?: '',
    
    // Connection Settings
    'timeout' => (int)(getenv('IMAP_TIMEOUT') ?: 30),
    'validate_certificate' => getenv('IMAP_VALIDATE_CERT') !== 'false',
    'disable_alerts' => getenv('IMAP_DISABLE_ALERTS') === 'true',
    
    // Mailbox Configuration
    'mailboxes' => [
        'INBOX',
        '[Gmail]/Sent Mail',
        '[Gmail]/Drafts',
        '[Gmail]/Trash',
        '[Gmail]/Spam',
    ],
    'sync_interval' => (int)(getenv('IMAP_SYNC_INTERVAL') ?: 300), // seconds
    'batch_size' => (int)(getenv('IMAP_BATCH_SIZE') ?: 50),
];

// ============================================================================
// SMTP SETTINGS (For sending emails)
// ============================================================================

$config['smtp'] = [
    'server' => getenv('SMTP_SERVER') ?: 'smtp.gmail.com',
    'port' => (int)(getenv('SMTP_PORT') ?: 587),
    'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls', // 'tls', 'ssl', or 'none'
    'username' => getenv('SMTP_USERNAME') ?: '',
    'password' => getenv('SMTP_PASSWORD') ?: '',
    'from_name' => getenv('SMTP_FROM_NAME') ?: 'Gmail App',
    'from_email' => getenv('SMTP_FROM_EMAIL') ?: 'noreply@gmail.local',
    'timeout' => (int)(getenv('SMTP_TIMEOUT') ?: 30),
];

// ============================================================================
// APPLICATION CONFIGURATION
// ============================================================================

$config['app'] = [
    // Application Details
    'name' => getenv('APP_NAME') ?: 'Gmail Application',
    'version' => '1.0.0',
    'url' => getenv('APP_URL') ?: 'http://localhost',
    'timezone' => getenv('APP_TIMEZONE') ?: 'UTC',
    
    // Paths
    'base_path' => __DIR__,
    'storage_path' => __DIR__ . '/storage',
    'logs_path' => __DIR__ . '/storage/logs',
    'cache_path' => __DIR__ . '/storage/cache',
    'uploads_path' => __DIR__ . '/public/uploads',
    'temp_path' => sys_get_temp_dir(),
    
    // Locale and Language
    'default_locale' => getenv('APP_LOCALE') ?: 'en_US',
    'supported_locales' => ['en_US', 'es_ES', 'fr_FR', 'de_DE'],
    'fallback_locale' => 'en_US',
    
    // Display Settings
    'pagination_limit' => (int)(getenv('PAGINATION_LIMIT') ?: 25),
    'items_per_page' => (int)(getenv('ITEMS_PER_PAGE') ?: 50),
    'max_upload_size' => (int)(getenv('MAX_UPLOAD_SIZE') ?: 10485760), // 10MB in bytes
];

// ============================================================================
// SECURITY SETTINGS
// ============================================================================

$config['security'] = [
    // Password Requirements
    'password_min_length' => (int)(getenv('PASSWORD_MIN_LENGTH') ?: 8),
    'password_require_uppercase' => getenv('PASSWORD_REQUIRE_UPPERCASE') !== 'false',
    'password_require_numbers' => getenv('PASSWORD_REQUIRE_NUMBERS') !== 'false',
    'password_require_special' => getenv('PASSWORD_REQUIRE_SPECIAL') !== 'false',
    'password_expiry_days' => (int)(getenv('PASSWORD_EXPIRY_DAYS') ?: 90),
    
    // Session Configuration
    'session_name' => getenv('SESSION_NAME') ?: 'GMAIL_SESS',
    'session_lifetime' => (int)(getenv('SESSION_LIFETIME') ?: 3600), // seconds
    'session_path' => getenv('SESSION_PATH') ?: '/tmp',
    'session_secure' => getenv('SESSION_SECURE') === 'true',
    'session_httponly' => getenv('SESSION_HTTPONLY') !== 'false',
    'session_samesite' => getenv('SESSION_SAMESITE') ?: 'Lax', // 'Strict', 'Lax', 'None'
    
    // CSRF Protection
    'csrf_enabled' => getenv('CSRF_ENABLED') !== 'false',
    'csrf_token_length' => (int)(getenv('CSRF_TOKEN_LENGTH') ?: 32),
    'csrf_token_lifetime' => (int)(getenv('CSRF_TOKEN_LIFETIME') ?: 3600),
    
    // Rate Limiting
    'rate_limit_enabled' => getenv('RATE_LIMIT_ENABLED') !== 'false',
    'rate_limit_requests' => (int)(getenv('RATE_LIMIT_REQUESTS') ?: 100),
    'rate_limit_window' => (int)(getenv('RATE_LIMIT_WINDOW') ?: 60), // seconds
    
    // Encryption
    'encryption_algorithm' => getenv('ENCRYPTION_ALGORITHM') ?: 'AES-256-CBC',
    'encryption_key' => getenv('ENCRYPTION_KEY') ?: '',
    
    // CORS Configuration
    'cors_enabled' => getenv('CORS_ENABLED') === 'true',
    'cors_allowed_origins' => explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: '*'),
    'cors_allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
    'cors_allow_credentials' => getenv('CORS_ALLOW_CREDENTIALS') === 'true',
    
    // Two-Factor Authentication
    'two_factor_enabled' => getenv('TWO_FACTOR_ENABLED') === 'true',
    'two_factor_methods' => ['email', 'authenticator', 'sms'],
    
    // IP Whitelist/Blacklist
    'ip_whitelist_enabled' => getenv('IP_WHITELIST_ENABLED') === 'false',
    'ip_whitelist' => explode(',', getenv('IP_WHITELIST') ?: ''),
    'ip_blacklist_enabled' => getenv('IP_BLACKLIST_ENABLED') === 'false',
    'ip_blacklist' => explode(',', getenv('IP_BLACKLIST') ?: ''),
];

// ============================================================================
// NOTIFICATION SETTINGS
// ============================================================================

$config['notifications'] = [
    // Email Notifications
    'email_enabled' => getenv('EMAIL_NOTIFICATIONS_ENABLED') !== 'false',
    'email_driver' => getenv('MAIL_DRIVER') ?: 'smtp',
    'email_queue_enabled' => getenv('EMAIL_QUEUE_ENABLED') === 'true',
    'email_queue_max_retry' => (int)(getenv('EMAIL_QUEUE_MAX_RETRY') ?: 5),
    
    // In-App Notifications
    'in_app_enabled' => getenv('IN_APP_NOTIFICATIONS_ENABLED') !== 'false',
    'in_app_retention_days' => (int)(getenv('IN_APP_RETENTION_DAYS') ?: 30),
    
    // Push Notifications
    'push_enabled' => getenv('PUSH_NOTIFICATIONS_ENABLED') === 'true',
    'push_service' => getenv('PUSH_SERVICE') ?: 'fcm', // Firebase Cloud Messaging
    'push_api_key' => getenv('PUSH_API_KEY') ?: '',
    'push_api_secret' => getenv('PUSH_API_SECRET') ?: '',
    
    // SMS Notifications
    'sms_enabled' => getenv('SMS_NOTIFICATIONS_ENABLED') === 'true',
    'sms_service' => getenv('SMS_SERVICE') ?: 'twilio', // 'twilio', 'aws_sns', etc.
    'sms_account_sid' => getenv('SMS_ACCOUNT_SID') ?: '',
    'sms_auth_token' => getenv('SMS_AUTH_TOKEN') ?: '',
    'sms_from_number' => getenv('SMS_FROM_NUMBER') ?: '',
    
    // Slack Notifications
    'slack_enabled' => getenv('SLACK_NOTIFICATIONS_ENABLED') === 'true',
    'slack_webhook_url' => getenv('SLACK_WEBHOOK_URL') ?: '',
    'slack_channel' => getenv('SLACK_CHANNEL') ?: '#notifications',
    
    // Webhook Notifications
    'webhook_enabled' => getenv('WEBHOOK_NOTIFICATIONS_ENABLED') === 'true',
    'webhook_max_retries' => (int)(getenv('WEBHOOK_MAX_RETRIES') ?: 3),
    'webhook_timeout' => (int)(getenv('WEBHOOK_TIMEOUT') ?: 30),
    
    // Notification Events
    'notify_on_new_email' => getenv('NOTIFY_ON_NEW_EMAIL') !== 'false',
    'notify_on_error' => getenv('NOTIFY_ON_ERROR') !== 'false',
    'notify_on_sync_complete' => getenv('NOTIFY_ON_SYNC_COMPLETE') === 'true',
    'notify_on_login' => getenv('NOTIFY_ON_LOGIN') === 'true',
    'notify_on_failed_login' => getenv('NOTIFY_ON_FAILED_LOGIN') !== 'false',
    
    // Batch Notifications
    'batch_notifications' => getenv('BATCH_NOTIFICATIONS') === 'true',
    'batch_interval' => (int)(getenv('BATCH_INTERVAL') ?: 300), // seconds
];

// ============================================================================
// LOGGING CONFIGURATION
// ============================================================================

$config['logging'] = [
    'enabled' => getenv('LOGGING_ENABLED') !== 'false',
    'level' => getenv('LOG_LEVEL') ?: 'info', // 'debug', 'info', 'warning', 'error'
    'format' => getenv('LOG_FORMAT') ?: 'json', // 'json', 'text'
    'max_file_size' => (int)(getenv('LOG_MAX_FILE_SIZE') ?: 10485760), // 10MB
    'max_files' => (int)(getenv('LOG_MAX_FILES') ?: 10),
    'channels' => [
        'default' => 'stack',
        'stack' => ['file', 'error_file'],
        'file' => [
            'driver' => 'file',
            'path' => $config['app']['logs_path'] . '/app.log',
            'level' => 'debug',
        ],
        'error_file' => [
            'driver' => 'file',
            'path' => $config['app']['logs_path'] . '/error.log',
            'level' => 'error',
        ],
    ],
];

// ============================================================================
// CACHING CONFIGURATION
// ============================================================================

$config['cache'] = [
    'default' => getenv('CACHE_DRIVER') ?: 'file',
    'ttl' => (int)(getenv('CACHE_TTL') ?: 3600),
    
    'drivers' => [
        'file' => [
            'path' => $config['app']['cache_path'],
        ],
        'redis' => [
            'host' => getenv('REDIS_HOST') ?: 'localhost',
            'port' => (int)(getenv('REDIS_PORT') ?: 6379),
            'password' => getenv('REDIS_PASSWORD') ?: '',
            'database' => (int)(getenv('REDIS_DATABASE') ?: 0),
        ],
        'memcached' => [
            'servers' => explode(',', getenv('MEMCACHED_SERVERS') ?: 'localhost:11211'),
        ],
    ],
];

// ============================================================================
// QUEUE CONFIGURATION
// ============================================================================

$config['queue'] = [
    'default' => getenv('QUEUE_DRIVER') ?: 'database',
    'failed_queue_table' => 'failed_jobs',
    
    'drivers' => [
        'database' => [
            'connection' => 'default',
            'table' => 'jobs',
        ],
        'redis' => [
            'connection' => 'default',
        ],
    ],
];

// ============================================================================
// API CONFIGURATION
// ============================================================================

$config['api'] = [
    'enabled' => getenv('API_ENABLED') !== 'false',
    'version' => getenv('API_VERSION') ?: 'v1',
    'base_url' => getenv('API_BASE_URL') ?: '/api',
    'rate_limit' => (int)(getenv('API_RATE_LIMIT') ?: 1000),
    'rate_limit_window' => (int)(getenv('API_RATE_LIMIT_WINDOW') ?: 3600),
    'authentication' => getenv('API_AUTHENTICATION') ?: 'bearer', // 'bearer', 'api_key', 'basic'
];

// ============================================================================
// ERROR HANDLING
// ============================================================================

$config['errors'] = [
    'display_errors' => APP_DEBUG,
    'log_errors' => true,
    'error_log_path' => $config['app']['logs_path'] . '/php_errors.log',
];

// Set error reporting based on environment
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Get a configuration value
 * 
 * @param string $key Configuration key (e.g., 'database.host')
 * @param mixed $default Default value if key doesn't exist
 * @return mixed Configuration value
 */
function config($key = null, $default = null)
{
    global $config;
    
    if ($key === null) {
        return $config;
    }
    
    $keys = explode('.', $key);
    $value = $config;
    
    foreach ($keys as $k) {
        if (is_array($value) && isset($value[$k])) {
            $value = $value[$k];
        } else {
            return $default;
        }
    }
    
    return $value;
}

/**
 * Get an environment variable
 * 
 * @param string $key Environment variable name
 * @param mixed $default Default value
 * @return mixed Environment variable value
 */
function env($key, $default = null)
{
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

// ============================================================================
// RETURN CONFIGURATION
// ============================================================================

return $config;
