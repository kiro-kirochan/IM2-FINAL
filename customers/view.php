<?php
require_once __DIR__ . '/../includes/layout.php';

$id = (int)($_GET['customer_id'] ?? 0);
$c = q_one('SELECT c.*, z.barangay, z.delivery_fee FROM customers c LEFT JOIN delivery_zones z ON z.zone_id = c.zone_id WHERE customer_id = ?', [$id]);
if (!$c) { flash('error', 'Customer not found.'); redirect('customers/'); }

$orders = q_all('SELECT * FROM v_order_summary WHERE customer_id = ? AND is_deleted = 0 ORDER BY order_date DESC', [$id]);
$loans  = q_all('SELECT * FROM container_loans WHERE customer_id = ? AND is_deleted = 0 ORDER BY date_lent DESC', [$id]);
$owes = array_sum(array_map(fn($o) => $o['order_status'] === 'Cancelled' ? 0 : $o['balance'], $orders));

page_start($c['full_name'], 'customers', [
    'sub' => $c['is_deleted'] ? 'ARCHIVED customer' : 'Customer profile',
    'actions' => head_link('customers/', '← All customers', 'btn')
        . (!$c['is_system'] && !$c['is_deleted'] ? head_link('customers/edit.php?customer_id=' . $id, 'Edit', 'btn btn-sun') : '')
        . (!$c['is_deleted'] ? head_link('orders/add.php', '+ New order', 'btn btn-primary') : ''),
]); ?>
<div class="grid g2e">
  <section class="card">
    <h2>Details</h2>
    <dl class="facts">
      <dt>Contact</dt><dd><?= e($c['contact_number'] ?: '—') ?></dd>
      <dt>Address</dt><dd><?= $c['street'] ? e($c['street']) . ', Brgy. ' . e($c['barangay']) : '—' ?></dd>
      <dt>Delivery fee</dt><dd><?= $c['delivery_fee'] !== null ? peso($c['delivery_fee']) : '—' ?></dd>
      <dt>Customer since</dt><dd><?= e(fdate($c['created_at'])) ?></dd>
      <dt>Unpaid balance</dt><dd><?= $owes > 0 ? '<span class="badge b-bad">' . peso($owes) . '</span>' : 'None' ?></dd>
    </dl>
  </section>
  <section class="card">
    <h2>Borrowed containers</h2>
    <?php if ($loans): foreach ($loans as $l): $out = $l['qty'] - $l['qty_returned']; ?>
      <p style="margin:4px 0"><b><?= (int)$l['qty'] ?> × <?= e($l['container_type']) ?></b> · lent <?= e(fdate($l['date_lent'])) ?> —
        <?= $out > 0 ? '<span class="badge b-sun">' . $out . ' not returned</span>' : '<span class="badge b-ok">returned</span>' ?></p>
    <?php endforeach; else: ?><p class="muted" style="margin:0">No container loans.</p><?php endif; ?>
  </section>
</div>

<section class="card">
  <h2>Order history</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Order</th><th>Type</th><th>Status</th><th class="num">Total</th><th>Payment</th><th></th></tr></thead>
    <tbody><?php foreach ($orders as $o): ?>
      <tr><td><span class="ticket">#<?= (int)$o['queue_no'] ?></span> <b><?= e(order_label($o['order_no'])) ?></b> <span class="muted small"><?= e(fdate($o['order_date'])) ?></span></td>
          <td><?= e($o['order_type']) ?></td><td><?= status_badge($o['order_status']) ?></td>
          <td class="num"><?= peso($o['order_total']) ?></td><td><?= pay_badge($o['payment_status'], $o['balance']) ?></td>
          <td><a class="btn btn-sm" href="<?= e(url('orders/view.php?order_no=' . $o['order_no'])) ?>">Slip</a></td></tr>
    <?php endforeach; if (!$orders): ?><tr><td colspan="6" class="empty">No orders yet.</td></tr><?php endif; ?></tbody></table></div>
</section>
<?php page_end();
