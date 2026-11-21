<?php
// Shared page frame: page_start() ... page content ... page_end()
require_once __DIR__ . '/helpers.php';

const NAV = [
    ['index.php',      'dashboard',  'Dashboard',  '◉'],
    ['orders/',        'orders',     'Orders',     '▤'],
    ['deliveries/',    'deliveries', 'Deliveries', '➜'],
    ['customers/',     'customers',  'Customers',  '☺'],
    ['products/',      'products',   'Products',   '◆'],
    ['containers/',    'containers', 'Containers', '◍'],
    ['reports/',       'reports',    'Reports',    '▥'],
];
const NAV_SETUP = [
    ['riders/', 'riders', 'Riders',        '⚑'],
    ['zones/',  'zones',  'Barangay Fees', '⌖'],
    ['trash/',  'trash',  'Archive',       '▣'],
];

function page_start(string $title, string $active = '', array $opts = []): void
{
    $cfg = config();
    $pending = (int)q_val("SELECT COUNT(*) FROM orders WHERE is_deleted = 0 AND order_date >= CURDATE()
                           AND order_status NOT IN ('Released','Delivered','Delivery Unsuccessful','Cancelled')");
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · ClearDrop</title>
  <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
  <script defer src="<?= e(url('assets/js/app.js')) ?>"></script>
  <?php foreach (($opts['scripts'] ?? []) as $s): ?>
    <script defer src="<?= e(url($s)) ?>"></script>
  <?php endforeach; ?>
</head>
<body>
<div class="shell">
  <aside class="side">
    <a class="logo" href="<?= e(url('index.php')) ?>">
      <span class="logo-drop" aria-hidden="true"></span>
      <span><b>ClearDrop</b><small>Refilling Station</small></span>
    </a>
    <nav>
      <?php foreach (NAV as [$href, $key, $label, $icon]): ?>
        <a href="<?= e(url($href)) ?>" class="<?= $active === $key ? 'on' : '' ?>">
          <i><?= $icon ?></i><?= e($label) ?>
          <?php if ($key === 'orders' && $pending): ?><em class="count"><?= $pending ?></em><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <p class="nav-sep">Setup</p>
      <?php foreach (NAV_SETUP as [$href, $key, $label, $icon]): ?>
        <a href="<?= e(url($href)) ?>" class="<?= $active === $key ? 'on' : '' ?>"><i><?= $icon ?></i><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot"><?= e($cfg['station_addr']) ?><br><?= date('D, M j, Y') ?></div>
  </aside>

  <main class="main">
    <header class="page-head">
      <div>
        <h1><?= e($title) ?></h1>
        <?php if (!empty($opts['sub'])): ?><p class="sub"><?= e($opts['sub']) ?></p><?php endif; ?>
      </div>
      <div class="head-actions"><?= $opts['actions'] ?? '' ?></div>
    </header>
    <?php foreach (take_flashes() as [$type, $msg]): ?>
      <div class="flash flash-<?= e($type) ?>" role="status"><?= e($msg) ?></div>
    <?php endforeach; ?>
<?php
}

function page_end(): void
{
    echo "\n  </main>\n</div>\n</body>\n</html>\n";
}

// Header button helper
function head_link(string $path, string $label, string $class = 'btn btn-primary'): string
{
    return '<a class="' . e($class) . '" href="' . e(url($path)) . '">' . e($label) . '</a>';
}
