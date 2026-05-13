<?php
/**
 * index.php
 * Entry point for the Library Management System REST API.
 * All requests are routed here via .htaccess rewriting.
 */

declare(strict_types=1);

// ── Bootstrap ─────────────────────────────────────────────────────────────────
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/utils/JWT.php';
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/utils/Validator.php';
require_once __DIR__ . '/middleware/Auth.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/BookController.php';
require_once __DIR__ . '/controllers/CategoryController.php';
require_once __DIR__ . '/controllers/LoanController.php';
require_once __DIR__ . '/controllers/ReviewController.php';
require_once __DIR__ . '/controllers/StatsController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/routes/Router.php';

// ── CORS ──────────────────────────────────────────────────────────────────────
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '*';
$allowed = ALLOWED_ORIGINS;

if ($allowed === '*') {
    header('Access-Control-Allow-Origin: *');
} elseif (in_array($origin, explode(',', $allowed), true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Handle pre-flight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Simple rate limiting using APCu (optional, degrades gracefully) ───────────
if (function_exists('apcu_fetch')) {
    $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = "rl:$ip";
    $hits = (int) apcu_fetch($key);
    if ($hits > 100) {
        Response::error('Too many requests. Please try again later.', 429);
    }
    apcu_add($key, 0, 900);   // Create with 15-min TTL
    apcu_inc($key);
}

// ── Error handling ────────────────────────────────────────────────────────────
set_exception_handler(function (Throwable $e) {
    $msg = APP_ENV === 'development' ? $e->getMessage() : 'Internal server error.';
    Response::error($msg, 500);
});

// ── Route definitions ─────────────────────────────────────────────────────────
$router = new Router();
$auth   = new AuthController();
$books  = new BookController();
$cats   = new CategoryController();
$loans  = new LoanController();
$rev    = new ReviewController();
$stats  = new StatsController();
$users  = new UserController();

// Health check
$router->get('/api/health', fn() => Response::success('Library API is running.', 200, [
    'version'   => API_VERSION,
    'timestamp' => date('c'),
]));

// API root
$router->get('/api', fn() => Response::success('Library Management System API', 200, [
    'version'   => API_VERSION,
    'endpoints' => [
        'auth'       => '/api/auth',
        'books'      => '/api/books',
        'categories' => '/api/categories',
        'loans'      => '/api/loans',
        'reviews'    => '/api/reviews',
        'stats'      => '/api/stats',
        'users'      => '/api/users',
    ],
]));

// ── Auth ──────────────────────────────────────────────────────────────────────
$router->post('/api/auth/register', fn($p, $b) => $auth->register($b));
$router->post('/api/auth/login',    fn($p, $b) => $auth->login($b));
$router->get('/api/auth/me',        fn()        => $auth->me());

// ── Books ─────────────────────────────────────────────────────────────────────
$router->get('/api/books',           fn()        => $books->index());
$router->get('/api/books/:id',       fn($p)      => $books->show((int)$p['id']));
$router->post('/api/books',          fn($p, $b)  => $books->create($b));
$router->put('/api/books/:id',       fn($p, $b)  => $books->update((int)$p['id'], $b));
$router->delete('/api/books/:id',    fn($p)      => $books->delete((int)$p['id']));

// ── Categories ────────────────────────────────────────────────────────────────
$router->get('/api/categories',          fn()       => $cats->index());
$router->get('/api/categories/:id',      fn($p)     => $cats->show((int)$p['id']));
$router->post('/api/categories',         fn($p,$b)  => $cats->create($b));
$router->put('/api/categories/:id',      fn($p,$b)  => $cats->update((int)$p['id'], $b));
$router->delete('/api/categories/:id',   fn($p)     => $cats->delete((int)$p['id']));

// ── Loans ─────────────────────────────────────────────────────────────────────
$router->get('/api/loans',               fn()       => $loans->index());
$router->get('/api/loans/:id',           fn($p)     => $loans->show((int)$p['id']));
$router->post('/api/loans',              fn($p,$b)  => $loans->create($b));
$router->patch('/api/loans/:id/return',  fn($p)     => $loans->returnBook((int)$p['id']));

// ── Reviews ───────────────────────────────────────────────────────────────────
$router->get('/api/reviews/book/:book_id', fn($p)    => $rev->byBook((int)$p['book_id']));
$router->post('/api/reviews',              fn($p,$b) => $rev->create($b));
$router->delete('/api/reviews/:id',        fn($p)    => $rev->delete((int)$p['id']));

// ── Stats ─────────────────────────────────────────────────────────────────────
$router->get('/api/stats/overview', fn() => $stats->overview());
$router->get('/api/stats/overdue',  fn() => $stats->overdue());

// ── Users ─────────────────────────────────────────────────────────────────────
$router->get('/api/users',        fn()       => $users->index());
$router->get('/api/users/:id',    fn($p)     => $users->show((int)$p['id']));
$router->put('/api/users/:id',    fn($p,$b)  => $users->update((int)$p['id'], $b));
$router->delete('/api/users/:id', fn($p)     => $users->delete((int)$p['id']));

// ── Dispatch ──────────────────────────────────────────────────────────────────
$router->dispatch();
