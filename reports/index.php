<?php
require_once __DIR__ . '/../includes/layout.php';

$tabs = ['daily' => 'Daily sales', 'products' => 'Product sales', 'unpaid' => 'Unpaid balances', 'riders' => 'Rider trips'];
$r = $_GET['r'] ?? 'daily';
if (!isset($tabs[$r])) $r = 'daily';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-d', strtotime('-30 days'));
$to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');

page_start('Reports', 'reports', ['sub' => 'Numbers straight from the database', 'actions' => '<button class="btn no-print" data-print>Print</button>']); ?>

<div class="toolbar">
  <div class="pills">
    <?php foreach ($tabs as $k => $label): ?>
      <a class="pill <?= $r === $k ? 'on' : '' ?>" href="?r=<?= e($k) ?>&from=<?= e($from) ?>&to=<?= e($to) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
  <?php if ($r !== 'unpaid'): ?>
  <form method="get" class="row-actions" style="margin-left:auto">
    <input type="hidden" name="r" value="<?= e($r) ?>">
    <input type="date" name="from" value="<?= e($from) ?>" style="width:auto"> <span>to</span>
    <input type="date" name="to" value="<?= e($to) ?>" style="width:auto">
    <button class="btn btn-sm">Apply</button>
  </form>
  <?php endif; ?>
</div>

<?php if ($r === 'daily'):
    $rows = q_all('SELECT * FROM v_daily_sales WHERE sales_date BETWEEN ? AND ? ORDER BY sales_date DESC', [$from, $to]);
    $sum = array_sum(array_column($rows, 'total_collected')); ?>
  <section class="card"><h2>Money collected per day <span class="badge b-ok"><?= peso($sum) ?> total</span></h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Date</th><th class="num">Payments</th><th class="num">Cash</th><th class="num">GCash</th><th class="num">Total</th></tr></thead><tbody>
    <?php foreach ($rows as $d): ?>
      <tr><td><b><?= e(fdate($d['sales_date'])) ?></b></td><td class="num"><?= (int)$d['payments_count'] ?></td><td class="num"><?= peso($d['cash_total']) ?></td><td class="num"><?= peso($d['gcash_total']) ?></td><td class="num"><b><?= peso($d['total_collected']) ?></b></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="5" class="empty">No payments in this period.</td></tr><?php endif; ?>
  </tbody></table></div></section>

<?php elseif ($r === 'products'):
    $rows = q_all("SELECT p.product_code, p.product_name, SUM(oi.qty) AS units, SUM(oi.qty * oi.unit_price_charged) AS amount
                   FROM order_items oi JOIN products p ON p.product_code = oi.product_code JOIN orders o ON o.order_no = oi.order_no
                   WHERE oi.is_removed = 0 AND o.is_deleted = 0 AND o.order_status <> 'Cancelled' AND DATE(o.order_date) BETWEEN ? AND ?
                   GROUP BY p.product_code, p.product_name ORDER BY amount DESC", [$from, $to]); ?>
  <section class="card"><h2>Units and sales per product</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Code</th><th>Product</th><th class="num">Units sold</th><th class="num">Sales (ordered)</th></tr></thead><tbody>
    <?php foreach ($rows as $p): ?>
      <tr><td><span class="ticket"><?= e($p['product_code']) ?></span></td><td><b><?= e($p['product_name']) ?></b></td><td class="num"><?= (int)$p['units'] ?></td><td class="num"><b><?= peso($p['amount']) ?></b></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="4" class="empty">No orders in this period.</td></tr><?php endif; ?>
  </tbody></table></div></section>

<?php elseif ($r === 'unpaid'):
    $rows = q_all("SELECT * FROM v_order_summary WHERE balance > 0 AND order_status <> 'Cancelled' AND is_deleted = 0 ORDER BY balance DESC");
    $sum = array_sum(array_column($rows, 'balance')); ?>
  <section class="card"><h2>Orders with a balance <span class="badge b-bad"><?= peso($sum) ?> outstanding</span></h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Order</th><th>Customer</th><th>Contact</th><th class="num">Total</th><th class="num">Paid</th><th class="num">Balance</th><th></th></tr></thead><tbody>
    <?php foreach ($rows as $o): ?>
      <tr><td><span class="ticket">#<?= (int)$o['queue_no'] ?></span> <b><?= e(order_label($o['order_no'])) ?></b><br><span class="muted small"><?= e(fdate($o['order_date'])) ?></span></td>
          <td><b><?= e($o['customer_name']) ?></b></td><td><?= e($o['contact_number'] ?: '—') ?></td>
          <td class="num"><?= peso($o['order_total']) ?></td><td class="num"><?= peso($o['total_paid']) ?></td><td class="num"><b><?= peso($o['balance']) ?></b></td>
          <td><a class="btn btn-sm btn-aqua" href="<?= e(url('orders/pay.php?order_no=' . $o['order_no'])) ?>">Pay</a></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="empty">Everything is paid. 🎉</td></tr><?php endif; ?>
  </tbody></table></div></section>

<?php else:
    $rows = q_all("SELECT r.rider_name, COUNT(d.delivery_id) AS trips,
                          SUM(d.delivery_status = 'Delivered') AS ok, SUM(d.delivery_status = 'Failed') AS failed,
                          SUM(d.delivery_status = 'Dispatched') AS out_now,
                          ROUND(AVG(TIMESTAMPDIFF(MINUTE, d.dispatch_time, d.completion_time)), 0) AS avg_min
                   FROM riders r LEFT JOIN deliveries d ON d.rider_id = r.rider_id AND DATE(d.dispatch_time) BETWEEN ? AND ?
                   WHERE r.is_deleted = 0 GROUP BY r.rider_id, r.rider_name ORDER BY trips DESC", [$from, $to]); ?>
  <section class="card"><h2>Trips per rider</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Rider</th><th class="num">Trips</th><th class="num">Delivered</th><th class="num">Failed</th><th class="num">Out now</th><th class="num">Avg. minutes</th></tr></thead><tbody>
    <?php foreach ($rows as $x): ?>
      <tr><td><b><?= e($x['rider_name']) ?></b></td><td class="num"><?= (int)$x['trips'] ?></td><td class="num"><?= (int)$x['ok'] ?></td><td class="num"><?= (int)$x['failed'] ?></td><td class="num"><?= (int)$x['out_now'] ?></td><td class="num"><?= $x['avg_min'] !== null ? (int)$x['avg_min'] : '—' ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div></section>
<?php endif; ?>
<?php page_end();
