<?php
require_once __DIR__ . '/includes/layout.php';

$today = q_one("SELECT
    (SELECT COUNT(*) FROM orders WHERE is_deleted = 0 AND queue_date = CURDATE() AND order_status <> 'Cancelled') AS orders_today,
    (SELECT COUNT(*) FROM orders WHERE is_deleted = 0 AND order_status NOT IN ('Released','Delivered','Delivery Unsuccessful','Cancelled')) AS active,
    (SELECT COALESCE(SUM(amount_paid),0) FROM payments WHERE DATE(payment_date) = CURDATE()) AS collected,
    (SELECT COALESCE(SUM(balance),0) FROM v_order_summary WHERE is_deleted = 0 AND order_status <> 'Cancelled') AS unpaid,
    (SELECT COUNT(*) FROM deliveries WHERE delivery_status = 'Dispatched') AS on_road,
    (SELECT COALESCE(SUM(qty - qty_returned),0) FROM container_loans WHERE is_deleted = 0) AS containers_out");

$queue = q_all("SELECT * FROM v_order_summary
                WHERE is_deleted = 0 AND order_status NOT IN ('Released','Delivered','Delivery Unsuccessful','Cancelled')
                ORDER BY queue_date, queue_no LIMIT 12");
$recent = q_all("SELECT p.*, c.full_name FROM payments p
                 JOIN orders o ON o.order_no = p.order_no JOIN customers c ON c.customer_id = o.customer_id
                 ORDER BY p.payment_date DESC LIMIT 6");

page_start('Dashboard', 'dashboard', [
    'sub' => 'Today at the counter · ' . date('l, F j'),
    'actions' => head_link('orders/add.php', '+ New Order') . head_link('deliveries/', 'Delivery Board', 'btn btn-aqua'),
]); ?>

<section class="tiles">
  <a class="tile aqua" href="<?= e(url('orders/')) ?>"><span>Orders today</span><strong><?= (int)$today['orders_today'] ?></strong></a>
  <a class="tile sun" href="<?= e(url('orders/?show=active')) ?>"><span>In the queue</span><strong><?= (int)$today['active'] ?></strong></a>
  <a class="tile" href="<?= e(url('reports/?r=daily')) ?>"><span>Collected today</span><strong><?= peso($today['collected']) ?></strong></a>
  <a class="tile coral" href="<?= e(url('reports/?r=unpaid')) ?>"><span>Unpaid balances</span><strong><?= peso($today['unpaid']) ?></strong></a>
</section>

<div class="grid g2">
  <section class="card">
    <h2>Now serving <span class="muted small">oldest first</span></h2>
    <div class="table-wrap"><table>
      <thead><tr><th>Queue</th><th>Customer</th><th>Status</th><th class="num">Balance</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($queue as $o): ?>
        <tr>
          <td><span class="ticket">#<?= (int)$o['queue_no'] ?></span><br><span class="muted small"><?= e(fdate($o['queue_date'])) ?></span></td>
          <td><b><?= e($o['customer_name']) ?></b><br><span class="muted small"><?= e($o['order_type']) ?><?= $o['barangay'] && $o['order_type'] === 'Delivery' ? ' · ' . e($o['barangay']) : '' ?></span></td>
          <td><?= status_badge($o['order_status']) ?></td>
          <td class="num"><?= pay_badge($o['payment_status'], $o['balance']) ?></td>
          <td><a class="btn btn-sm" href="<?= e(url('orders/view.php?order_no=' . $o['order_no'])) ?>">Slip</a></td>
        </tr>
      <?php endforeach; if (!$queue): ?>
        <tr><td colspan="5" class="empty">Queue is clear. 🎉 No active orders.</td></tr>
      <?php endif; ?>
      </tbody></table></div>
  </section>

  <div>
    <section class="card">
      <h2>On the road & containers</h2>
      <dl class="facts">
        <dt>Deliveries out now</dt><dd><a href="<?= e(url('deliveries/')) ?>"><?= (int)$today['on_road'] ?></a></dd>
        <dt>Containers lent out</dt><dd><a href="<?= e(url('containers/')) ?>"><?= (int)$today['containers_out'] ?></a></dd>
      </dl>
    </section>
    <section class="card">
      <h2>Latest payments</h2>
      <?php foreach ($recent as $p): ?>
        <div style="display:flex;justify-content:space-between;gap:10px;padding:6px 0;border-bottom:1px dashed var(--soft-line)">
          <span><b><?= e($p['full_name']) ?></b><br><span class="muted small"><?= e($p['receipt_no']) ?> · <?= e($p['payment_mode']) ?> · <?= e(ftime($p['payment_date'])) ?></span></span>
          <b><?= peso($p['amount_paid']) ?></b>
        </div>
      <?php endforeach; if (!$recent): ?><p class="muted">No payments yet.</p><?php endif; ?>
    </section>
  </div>
</div>

<?php page_end();
