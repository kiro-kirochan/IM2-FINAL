<?php
// One shared PDO connection for every page.
function config(): array
{
    static $c = null;
    return $c ??= require __DIR__ . '/config.php';
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $c = config();
    try {
        $pdo = new PDO(
            "mysql:host={$c['db_host']};port={$c['db_port']};dbname={$c['db_name']};charset=utf8mb4",
            $c['db_user'],
            $c['db_pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $e) {
        http_response_code(500);
        echo '<!doctype html><html lang="en" data-theme="light"><head><meta charset="utf-8"><title>Database Error · ClearDrop</title>'
           . '<style>body{font:15px/1.6 system-ui,sans-serif;background:#f8fafc;color:#1e293b;margin:0;padding:48px 16px}'
           . '.box{max-width:540px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:24px 28px}'
           . 'h2{margin:0 0 12px;color:#0f172a}code{background:#f1f5f9;padding:2px 6px;border-radius:4px}</style></head><body><div class="box">'
           . '<h2>Cannot reach the database</h2>'
           . '<p>1. Start <b>Apache</b> and <b>MySQL</b> in XAMPP.<br>'
           . '2. In phpMyAdmin, import <code>database/cleardrop.sql</code>.<br>'
           . '3. Check <code>includes/config.php</code> (or your <code>config.local.php</code>).</p>'
           . '<p style="color:#64748b;font-size:13px;margin-bottom:0">' . htmlspecialchars($e->getMessage()) . '</p></div></body></html>';
        exit;
    }
    return $pdo;
}

// Small query helpers -------------------------------------------------
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}
function q_one(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}
function q_val(string $sql, array $params = [])
{
    $r = q($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}

// Turns a PDOException into a sentence a cashier can read.
// Our triggers/procedures raise SQLSTATE 45000 with a friendly message.
function db_message(PDOException $e): string
{
    $info = $e->errorInfo ?? [];
    if (($info[0] ?? '') === '45000') {
        return $info[2] ?? 'Not allowed.';
    }
    $code = (int)($info[1] ?? 0);
    if ($code === 1062) return 'That value already exists.';
    if ($code === 1452) return 'That record points to something that does not exist.';
    if ($code === 4025 || $code === 3819) return 'A value is outside what the database allows (check quantities, prices and status).';
    return 'Database error. Please check your input and try again.';
}
