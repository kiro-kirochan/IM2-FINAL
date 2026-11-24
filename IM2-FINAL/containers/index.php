<?php
require_once __DIR__ . '/../includes/layout.php';

$show = ($_GET['show'] ?? 'out') === 'all' ? 'all' : 'out';
$sql = 'SELECT l.*, c.full_name, c.contact_number FROM container_loans l JOIN customers c ON c.customer_id = l.customer_id WHERE l.is_deleted = 0'
     . ($show === 'out' ? ' AND l.qty > l.qty_returned' : '') . ' ORDER BY (l.qty > l.qty_returned) DESC, l.date_lent DESC';
$rows = q_all($sql);
$totals = q_all('SELECT container_type, SUM(qty - qty_returned) AS out_qty FROM container_loans WHERE is_deleted = 0 GROUP BY container_type HAVING out_qty > 0');

page_start('Containers', 'containers', [
    'sub' => 'Track borrowed station containers currently held by customers',
    'actions' => head_link('containers/add.php', '+ Lend Containers'),
]); ?>

<section class="kpi-grid">
  <?php foreach ($totals as $t): ?>
    <div class="stat-card">
      <span class="stat-label"><?= e($t['container_type']) ?> out</span>
      <strong class="stat-value"><?= (int)$t['out_qty'] ?></strong>
    </div>
  <?php endforeach; ?>
  <?php if (!$totals): ?>
    <div class="stat-card">
      <span class="stat-label">Containers out</span>
      <strong class="stat-value">0</strong>
    </div>
  <?php endif; ?>
</section>

<div class="toolbar">
  <div class="filter-pills">
    <a class="<?= $show === 'out' ? 'on' : '' ?>" href="?show=out">Not yet returned</a>
    <a class="<?= $show === 'all' ? 'on' : '' ?>" href="?show=all">All records</a>
  </div>
</div>

<article class="table-card">
  <div class="overflow-auto">
    <table>
      <thead>
        <tr>
          <th>Customer</th>
          <th>Container</th>
          <th class="num">Lent</th>
          <th class="num">Returned</th>
          <th class="num">Still out</th>
          <th>Date lent</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $l): $out = $l['qty'] - $l['qty_returned']; ?>
        <tr>
          <td>
            <b><?= e($l['full_name']) ?></b>
            <?php if ($l['contact_number']): ?><br><span class="muted small"><?= e($l['contact_number']) ?></span><?php endif; ?>
            <?php if ($l['notes']): ?><br><span class="small muted"><?= e($l['notes']) ?></span><?php endif; ?>
          </td>
          <td><?= e($l['container_type']) ?></td>
          <td class="num"><?= (int)$l['qty'] ?></td>
          <td class="num"><?= (int)$l['qty_returned'] ?></td>
          <td class="num"><?= $out > 0 ? '<span class="badge b-warn">' . $out . '</span>' : '<span class="badge b-ok">0</span>' ?></td>
          <td class="small"><?= e(fdate($l['date_lent'])) ?></td>
          <td>
            <div class="row-actions">
              <?php if ($out > 0): ?>
                <a role="button" class="btn-xs" href="<?= e(url('containers/return.php?loan_id=' . $l['loan_id'])) ?>">Return</a>
              <?php endif; ?>
              <?= action_form('actions/archive.php', ['entity' => 'containers', 'id' => $l['loan_id'], 'return' => 'containers/'], 'Archive', 'outline btn-danger-outline btn-xs', 'Archive this loan record?') ?>
            </div>
          </td>
        </tr>
      <?php endforeach; if (!$rows): ?>
        <tr><td colspan="7" class="empty">No container loan records found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</article>
<?php page_end();
