<?php
require_once __DIR__ . '/../includes/layout.php';

$show = ($_GET['show'] ?? 'out') === 'all' ? 'all' : 'out';
$sql = 'SELECT l.*, c.full_name, c.contact_number FROM container_loans l JOIN customers c ON c.customer_id = l.customer_id WHERE l.is_deleted = 0'
     . ($show === 'out' ? ' AND l.qty > l.qty_returned' : '') . ' ORDER BY (l.qty > l.qty_returned) DESC, l.date_lent DESC';
$rows = q_all($sql);
$totals = q_all('SELECT container_type, SUM(qty - qty_returned) AS out_qty FROM container_loans WHERE is_deleted = 0 GROUP BY container_type HAVING out_qty > 0');

page_start('Containers', 'containers', ['sub' => 'Who is still holding the station\'s gallons', 'actions' => head_link('containers/add.php', '+ Lend Containers')]); ?>
<div class="tiles" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr))">
  <?php foreach ($totals as $t): ?><div class="tile sun"><span><?= e($t['container_type']) ?> out</span><strong><?= (int)$t['out_qty'] ?></strong></div><?php endforeach; ?>
  <?php if (!$totals): ?><div class="tile aqua"><span>Containers out</span><strong>0</strong></div><?php endif; ?>
</div>
<div class="pills" style="margin-bottom:14px">
  <a class="pill <?= $show === 'out' ? 'on' : '' ?>" href="?show=out">Not yet returned</a>
  <a class="pill <?= $show === 'all' ? 'on' : '' ?>" href="?show=all">All records</a>
</div>
<div class="table-wrap"><table>
  <thead><tr><th>Customer</th><th>Container</th><th class="num">Lent</th><th class="num">Returned</th><th class="num">Still out</th><th>Date lent</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($rows as $l): $out = $l['qty'] - $l['qty_returned']; ?>
    <tr><td><b><?= e($l['full_name']) ?></b><br><span class="muted small"><?= e($l['contact_number']) ?></span><?= $l['notes'] ? '<br><span class="small">' . e($l['notes']) . '</span>' : '' ?></td>
        <td><?= e($l['container_type']) ?></td><td class="num"><?= (int)$l['qty'] ?></td><td class="num"><?= (int)$l['qty_returned'] ?></td>
        <td class="num"><?= $out > 0 ? '<span class="badge b-sun">' . $out . '</span>' : '<span class="badge b-ok">0</span>' ?></td>
        <td class="small"><?= e(fdate($l['date_lent'])) ?></td>
        <td><div class="row-actions">
          <?php if ($out > 0): ?><a class="btn btn-sm btn-aqua" href="<?= e(url('containers/return.php?loan_id=' . $l['loan_id'])) ?>">Return</a><?php endif; ?>
          <?= action_form('actions/archive.php', ['entity' => 'containers', 'id' => $l['loan_id'], 'return' => 'containers/'], 'Archive', 'btn btn-sm btn-danger', 'Archive this loan record?') ?>
        </div></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="empty">Nothing to show.</td></tr><?php endif; ?>
</tbody></table></div>
<?php page_end();
