<?php
/**
 * config/config.php
 * Central configuration. Edit these values for your deployment.
 * In production, consider loading from environment variables or a .env file.
 */

// ── Database ──────────────────────────────────────────────────────────────────
define('DB_HOST',     getenv('DB_HOST')     ?: 'localhost');
define('DB_PORT',     getenv('DB_PORT')     ?: '3306');
define('DB_NAME',     getenv('DB_NAME')     ?: 'library_db');
define('DB_USER',     getenv('DB_USER')     ?: 'root');
define('DB_PASS',     getenv('DB_PASS')     ?: '');
define('DB_CHARSET',  'utf8mb4');

// ── JWT ───────────────────────────────────────────────────────────────────────
// Change JWT_SECRET to a long random string in production
define('JWT_SECRET',  getenv('JWT_SECRET')  ?: 'change_this_to_a_long_random_secret_key');
define('JWT_EXPIRY',  60 * 60 * 24);          // 24 hours in seconds

// ── App ───────────────────────────────────────────────────────────────────────
define('APP_ENV',     getenv('APP_ENV')     ?: 'development');
define('API_VERSION', 'v1');

// ── CORS — comma-separated allowed origins, or * for all ─────────────────────
define('ALLOWED_ORIGINS', getenv('ALLOWED_ORIGINS') ?: '*');
