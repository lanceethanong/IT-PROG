<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

$sessionLifetime = 60 * 60 * 24 * 30; // 30 days for server-side session GC window.
ini_set('session.gc_maxlifetime', (string) $sessionLifetime);

$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'; // Detect if the request is over HTTPS for secure cookie setting.
$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
$scriptDir = str_replace('\\', '/', dirname($scriptName));
$basePath = ($scriptDir === '/' || $scriptDir === '.') ? '' : rtrim($scriptDir, '/');
$cookiePath = $basePath === '' ? '/' : str_replace(' ', '%20', rtrim($basePath, '/') . '/');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $cookiePath,
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => $isHttps,
]);

if (session_status() !== PHP_SESSION_ACTIVE) { // Checks if there is a current session
    session_start();
}

// Helper functions for consistent path and request handling 
function base_path(): string
{
    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $dir = str_replace('\\', '/', dirname($scriptName));
    if ($dir === '/' || $dir === '.') {
        return '';
    }

    return rtrim($dir, '/');
}

function app_url(string $path = '/'): string
{
    $base = base_path();
    $normalized = '/' . ltrim($path, '/');
    if ($normalized === '//') {
        $normalized = '/';
    }

    return $base . $normalized;
}

function cookie_path(): string
{
    $base = base_path();
    if ($base === '') {
        return '/';
    }

    // Cookie paths cannot contain raw spaces.
    return str_replace(' ', '%20', rtrim($base, '/') . '/');
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function request_path(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return '/';
    }


    $path = rawurldecode($path);

    $base = rawurldecode(base_path());
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base)) ?: '/';
    }

    return rtrim($path, '/') ?: '/';
}

// Returns the HTTP method of the request, defaulting to 'GET' if not set.
function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function request_query(string $key, ?string $default = null): ?string
{
    return isset($_GET[$key]) ? (string) $_GET[$key] : $default;
}

function request_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_PRETTY_PRINT);
    exit;
}

function no_content_response(): never
{
    http_response_code(204);
    exit;
}

function redirect_to(string $path): never
{
    header('Location: ' . app_url($path));
    exit;
}

function render_view(string $viewFile, array $vars = []): never // Renders PHP view files 
{
    extract($vars, EXTR_SKIP);
    ob_start();
    include __DIR__ . '/../views/' . $viewFile . '.php';
    $content = (string) ob_get_clean();

    $base = app_url('/');
    $content = str_replace(['href="/', 'src="/', 'action="/'], ['href="' . $base, 'src="' . $base, 'action="' . $base], $content);

    echo $content;
    exit;
}

function route_not_found(): never
{
    http_response_code(404);
    echo '404 Not Found';
    exit;
}

function server_error(string $message = 'Server error'): never
{
    http_response_code(500);
    echo $message;
    exit;
}

function normalize_role_for_path(string $role): string
{
    return stripos($role, 'technician') !== false ? 'technician' : 'student'; // normalizes paths based on roles 
}

// Functions to enforce authentication and authorization rules in route handlers.
function require_login(): void
{
    if (empty($_SESSION['user'])) { // No logged in user
        redirect_to('/login');
    }
}

function require_role(string $role): void
{
    require_login();
    $actual = (string) ($_SESSION['user']['role'] ?? ''); // Requires each user to have a role 
    if ($actual !== $role) {
        redirect_to('/login');
    }
}

function bypass_login_if_session_exists(): void 
{
    if (empty($_SESSION['user'])) { //Checks if there is a current logged in user 
        return; //if none return to login page
    }

    $username = rawurlencode((string) $_SESSION['user']['username']);
    $role = (string) $_SESSION['user']['role'];

    if ($role === 'Admin') {
        redirect_to('/admin');
    }

    $pathRole = normalize_role_for_path($role);
    redirect_to('/dashboard/' . $pathRole . '?username=' . $username);
}

function status_class(string $status): string
{
    return match ($status) { // Colors of each status on the dashboard
        'Cancelled' => 'red',
        'Completed' => 'green',
        'In Progress' => 'yellow',
        'Scheduled' => 'blue',
        default => '',
    };
}

//Helper function to format dates to
function format_manila_date(string $date): string
{
    if ($date === '') {
        return '';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
        return $date;
    }

    $dt = new DateTime($date);
    $dt->setTimezone(new DateTimeZone('Asia/Manila'));
    return $dt->format('Y-m-d');
}

//Get user id from session
function session_user_id(): string
{
    return (string) ($_SESSION['user']['id'] ?? '');
}
