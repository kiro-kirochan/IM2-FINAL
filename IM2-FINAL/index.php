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
    'actions' => head_link('orders/add.php', '+ New Order') . head_link('deliveries/', 'Delivery Board', 'secondary outline btn-sm'),
]); ?>

<section class="kpi-grid">
  <a class="stat-card" href="<?= e(url('orders/')) ?>">
    <span class="stat-label">Orders today</span>
    <strong class="stat-value"><?= (int)$today['orders_today'] ?></strong>
  </a>
  <a class="stat-card" href="<?= e(url('orders/?show=active')) ?>">
    <span class="stat-label">In the queue</span>
    <strong class="stat-value"><?= (int)$today['active'] ?></strong>
  </a>
  <a class="stat-card" href="<?= e(url('reports/?r=daily')) ?>">
    <span class="stat-label">Collected today</span>
    <strong class="stat-value"><?= peso($today['collected']) ?></strong>
  </a>
  <a class="stat-card" href="<?= e(url('reports/?r=unpaid')) ?>">
    <span class="stat-label">Unpaid balances</span>
    <strong class="stat-value <?= (float)$today['unpaid'] > 0 ? 'is-alert' : '' ?>"><?= peso($today['unpaid']) ?></strong>
  </a>
</section>

<div class="grid-main-aside">
  <article class="table-card">
    <header>
      <h2>Now serving <small class="muted">· oldest first</small></h2>
    </header>
    <div class="overflow-auto">
      <table>
        <thead>
          <tr>
            <th>Queue</th>
            <th>Customer</th>
            <th>Status</th>
            <th class="num">Payment</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($queue as $o): ?>
          <tr>
            <td>
              <span class="ticket">#<?= (int)$o['queue_no'] ?></span><br>
              <span class="muted small"><?= e(fdate($o['queue_date'])) ?></span>
            </td>
            <td>
              <b><?= e($o['customer_name']) ?></b><br>
              <span class="muted small"><?= e($o['order_type']) ?><?= $o['barangay'] && $o['order_type'] === 'Delivery' ? ' · ' . e($o['barangay']) : '' ?></span>
            </td>
            <td><?= status_badge($o['order_status']) ?></td>
            <td class="num"><?= pay_badge($o['payment_status'], $o['balance']) ?></td>
            <td class="num"><a role="button" class="secondary outline btn-xs" href="<?= e(url('orders/view.php?order_no=' . $o['order_no'])) ?>">Slip</a></td>
          </tr>
        <?php endforeach; if (!$queue): ?>
          <tr><td colspan="5" class="empty">Queue is clear — no active orders right now.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </article>

  <div>
    <article>
      <header><h2>Operations overview</h2></header>
      <dl class="facts">
        <dt>Deliveries out now</dt>
        <dd><a href="<?= e(url('deliveries/')) ?>"><?= (int)$today['on_road'] ?> active</a></dd>
        <dt>Containers lent out</dt>
        <dd><a href="<?= e(url('containers/')) ?>"><?= (int)$today['containers_out'] ?> unreturned</a></dd>
      </dl>
    </article>

    <article>
      <header><h2>Latest payments</h2></header>
      <?php foreach ($recent as $p): ?>
        <div class="payment-row">
          <div>
            <b><?= e($p['full_name']) ?></b><br>
            <span class="muted small"><?= e($p['receipt_no']) ?> · <?= e($p['payment_mode']) ?> · <?= e(ftime($p['payment_date'])) ?></span>
          </div>
          <b><?= peso($p['amount_paid']) ?></b>
        </div>
      <?php endforeach; if (!$recent): ?>
        <p class="muted" style="margin-bottom:0">No payments recorded yet.</p>
      <?php endif; ?>
    </article>
  </div>
</div>

<?php page_end();
