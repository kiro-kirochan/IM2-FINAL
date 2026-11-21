<?php
session_start();
require_once __DIR__ . '/db.php';
date_default_timezone_set('Asia/Manila');

// ---- URLs -----------------------------------------------------------
// Works whether the project sits at http://localhost/cleardrop or at the root.
function base_url(): string
{
    static $b = null;
    if ($b !== null) return $b;
    $root = str_replace('\\', '/', realpath(dirname(__DIR__)));
    $doc  = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $b = ($doc && strncasecmp($root, $doc, strlen($doc)) === 0) ? substr($root, strlen($doc)) : '';
    return $b;
}
function url(string $path = ''): string { return base_url() . '/' . ltrim($path, '/'); }
function redirect(string $path): never { header('Location: ' . url($path)); exit; }

// ---- Output ---------------------------------------------------------
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function peso($n): string { return '₱' . number_format((float)$n, 2); }
function fdate($d): string { return $d ? date('M j, Y', strtotime($d)) : '—'; }
function fdatetime($d): string { return $d ? date('M j, Y g:i A', strtotime($d)) : '—'; }
function ftime($d): string { return $d ? date('g:i A', strtotime($d)) : '—'; }

function order_label($no): string { return 'ORD-' . $no; }
function stub_label($no): string { return $no ? 'STUB-' . str_pad((string)$no, 4, '0', STR_PAD_LEFT) : 'Pending'; }

// ---- Business constants (mirror the CHECK constraints) ---------------
const STATUS_WALKIN   = ['Received', 'Preparing', 'Ready for Pickup', 'Released', 'Cancelled'];
const STATUS_DELIVERY = ['Received', 'Preparing', 'To be Delivered', 'Out for Delivery', 'Delivered', 'Delivery Unsuccessful', 'Cancelled'];
const STATUS_FINAL    = ['Released', 'Delivered', 'Delivery Unsuccessful', 'Cancelled'];
const CONTAINER_TYPES = ['Slim', 'Round', 'Small Bottle'];
const SERVICE_TYPES   = ['Refill Only', 'Buy Container', 'Refill + New Container'];

function status_badge(string $s): string
{
    $cls = match (true) {
        in_array($s, ['Released', 'Delivered'], true) => 'b-ok',
        $s === 'Cancelled', $s === 'Delivery Unsuccessful' => 'b-bad',
        in_array($s, ['Out for Delivery', 'Ready for Pickup', 'To be Delivered'], true) => 'b-aqua',
        default => 'b-sun',
    };
    return '<span class="badge ' . $cls . '">' . e($s) . '</span>';
}
function pay_badge(string $status, $balance = null): string
{
    $map = ['Paid' => ['b-ok', 'Paid'], 'Partial' => ['b-sun', 'Partial'], 'Unpaid' => ['b-bad', 'Unpaid']];
    [$cls, $txt] = $map[$status] ?? ['b-sun', $status];
    $extra = ($balance !== null && $status !== 'Paid') ? ' · ' . peso($balance) : '';
    return '<span class="badge ' . $cls . '">' . e($txt . $extra) . '</span>';
}

// ---- Flash messages -------------------------------------------------
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function take_flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

// ---- CSRF (every POST form carries this token) -----------------------
function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}
function csrf_field(): string { return '<input type="hidden" name="_token" value="' . csrf_token() . '">'; }
function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('POST only'); }
    if (!hash_equals(csrf_token(), $_POST['_token'] ?? '')) { http_response_code(419); exit('Session expired. Go back and reload the page.'); }
}
function is_post(): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return false;
    if (!hash_equals(csrf_token(), $_POST['_token'] ?? '')) { flash('error', 'Session expired. Please try again.'); return false; }
    return true;
}

// ---- Input cleaning -------------------------------------------------
function post(string $k, string $default = ''): string { return trim((string)($_POST[$k] ?? $default)); }

// Philippine mobile numbers: 09XXXXXXXXX or +639XXXXXXXXX (spaces/dashes ignored)
function clean_phone(string $raw, ?string &$error = null): ?string
{
    $p = preg_replace('/[\s\-()]/', '', $raw);
    if ($p === '') return null;
    if (!preg_match('/^(\+?63|0)9\d{9}$/', $p)) {
        $error = 'Contact number must look like 09171234567.';
        return null;
    }
    return '0' . substr(preg_replace('/^(\+?63|0)/', '', $p), 0);
}

// ---- Soft delete / restore (calls the stored procedure) ---------------
const ENTITIES = ['customers', 'products', 'orders', 'riders', 'zones', 'containers'];

function set_deleted(string $entity, string $id, int $flag): bool
{
    if (!in_array($entity, ENTITIES, true)) return false;
    try {
        q('CALL sp_set_deleted(?, ?, ?)', [$entity, $id, $flag]);
        return true;
    } catch (PDOException $e) {
        flash('error', db_message($e));
        return false;
    }
}

// Small POST form used for Archive / Restore buttons
function action_form(string $path, array $fields, string $label, string $class = 'btn btn-sm', ?string $confirm = null): string
{
    $h = '<form method="post" action="' . e(url($path)) . '" class="inline"'
       . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field();
    foreach ($fields as $k => $v) {
        $h .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    return $h . '<button class="' . e($class) . '">' . e($label) . '</button></form>';
}

// ---- Lookup lists used by several pages ------------------------------
function zones(bool $withArchived = false): array
{
    return q_all('SELECT * FROM delivery_zones ' . ($withArchived ? '' : 'WHERE is_deleted = 0 ') . 'ORDER BY barangay');
}
function active_customers(): array
{
    return q_all('SELECT c.*, z.barangay, z.delivery_fee FROM customers c
                  LEFT JOIN delivery_zones z ON z.zone_id = c.zone_id
                  WHERE c.is_deleted = 0 ORDER BY c.is_system DESC, c.full_name');
}
function active_products(): array
{
    return q_all('SELECT * FROM products WHERE is_deleted = 0 ORDER BY FIELD(service_type, \'Refill Only\', \'Refill + New Container\', \'Buy Container\'), product_name');
}
function active_riders(): array
{
    return q_all('SELECT r.*, (SELECT COUNT(*) FROM deliveries d WHERE d.rider_id = r.rider_id AND d.delivery_status = "Dispatched") AS on_road
                  FROM riders r WHERE r.is_deleted = 0 ORDER BY rider_name');
}
