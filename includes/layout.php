<?php
// Shared page frame: page_start() ... page content ... page_end()
require_once __DIR__ . '/helpers.php';

const NAV = [
    ['index.php',   'dashboard',  'Dashboard'],
    ['orders/',     'orders',     'Orders'],
    ['deliveries/', 'deliveries', 'Deliveries'],
    ['customers/',  'customers',  'Customers'],
    ['containers/', 'containers', 'Containers'],
    ['reports/',    'reports',    'Reports'],
];
const NAV_SETUP = [
    ['products/', 'products', 'Products'],
    ['riders/',   'riders',   'Riders'],
    ['zones/',    'zones',    'Barangay Fees'],
    ['trash/',    'trash',    'Archive'],
];

function page_start(string $title, string $active = '', array $opts = []): void
{
    $cfg = config();
    $pending = (int)q_val("SELECT COUNT(*) FROM orders WHERE is_deleted = 0 AND order_date >= CURDATE()
                           AND order_status NOT IN ('Released','Delivered','Delivery Unsuccessful','Cancelled')");
    $setupKeys = array_column(NAV_SETUP, 1);
    $inSetup = in_array($active, $setupKeys, true);
    ?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · ClearDrop</title>
  <link rel="stylesheet" href="<?= e(url('assets/css/pico.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
  <script defer src="<?= e(url('assets/js/app.js')) ?>"></script>
  <?php foreach (($opts['scripts'] ?? []) as $s): ?>
    <script defer src="<?= e(url($s)) ?>"></script>
  <?php endforeach; ?>
</head>
<body>
<header class="topbar no-print">
  <nav class="container" aria-label="Main navigation">
    <ul>
      <li>
        <a class="brand" href="<?= e(url('index.php')) ?>">
          <strong>ClearDrop</strong>
          <small><?= e($cfg['station_addr']) ?></small>
        </a>
      </li>
    </ul>
    <ul>
      <?php foreach (NAV as [$href, $key, $label]): ?>
        <li>
          <a class="nav-link" href="<?= e(url($href)) ?>" <?= $active === $key ? 'aria-current="page"' : '' ?>>
            <?= e($label) ?>
            <?php if ($key === 'orders' && $pending): ?><span class="nav-count"><?= $pending ?></span><?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
      <li>
        <details class="dropdown">
          <summary class="<?= $inSetup ? 'is-active' : '' ?>">Setup</summary>
          <ul>
            <?php foreach (NAV_SETUP as [$href, $key, $label]): ?>
              <li>
                <a href="<?= e(url($href)) ?>" <?= $active === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
              </li>
            <?php endforeach; ?>
          </ul>
        </details>
      </li>
    </ul>
  </nav>
</header>

<main class="container main-content">
  <header class="page-header">
    <hgroup>
      <h1><?= e($title) ?></h1>
      <?php if (!empty($opts['sub'])): ?><p><?= e($opts['sub']) ?></p><?php endif; ?>
    </hgroup>
    <?php if (!empty($opts['actions'])): ?>
      <div class="header-actions no-print"><?= $opts['actions'] ?></div>
    <?php endif; ?>
  </header>
  <?php foreach (take_flashes() as [$type, $msg]): ?>
    <div class="flash flash-<?= e($type) ?>" role="status"><?= e($msg) ?></div>
  <?php endforeach; ?>
<?php
}

function page_end(): void
{
    echo "\n</main>\n</body>\n</html>\n";
}

// Header button helper
function head_link(string $path, string $label, string $class = 'btn-sm'): string
{
    return '<a role="button" class="' . e($class) . '" href="' . e(url($path)) . '">' . e($label) . '</a>';
}
