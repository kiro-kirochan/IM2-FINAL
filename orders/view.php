<?php
require_once __DIR__ . '/../includes/layout.php';

$orderNo = (int)($_GET['order_no'] ?? 0);
$o = q_one('SELECT * FROM v_order_summary WHERE order_no = ?', [$orderNo]);
if (!$o) { flash('error', 'Order not found.'); redirect('orders/'); }

$items = q_all('SELECT oi.*, p.product_name FROM order_items oi JOIN products p ON p.product_code = oi.product_code
                WHERE oi.order_no = ? AND oi.is_removed = 0 ORDER BY p.product_name', [$orderNo]);
$payments = q_all('SELECT * FROM payments WHERE order_no = ? ORDER BY payment_date, payment_id', [$orderNo]);
$delivery = $o['order_type'] === 'Delivery'
    ? q_one('SELECT d.*, r.rider_name, r.vehicle FROM deliveries d JOIN riders r ON r.rider_id = d.rider_id WHERE d.order_no = ? ORDER BY d.delivery_id DESC LIMIT 1', [$orderNo])
    : null;
$zoneRow = $o['order_type'] === 'Delivery' ? q_one('SELECT street, barangay FROM v_order_summary WHERE order_no = ?', [$orderNo]) : null;

// balance remaining right after each payment (Business Rule 15)
$running = 0;
foreach ($payments as &$p) { $running += $p['amount_paid']; $p['balance_after'] = max($o['order_total'] - $running, 0); }
unset($p);
$last = $payments ? end($payments) : null;

$slip = ($_GET['slip'] ?? ($o['total_paid'] > 0 ? 'full' : 'stub')) === 'stub' ? 'stub' : 'full';
$paid = $o['balance'] <= 0 && $o['order_total'] > 0;
$itemCount = array_sum(array_column($items, 'qty'));
$editable = in_array($o['order_status'], ['Received', 'Preparing'], true) && !$o['is_deleted'];

// plain-text version for the Copy button
$txt = [config()['station_name'], "QUEUE #{$o['queue_no']} | " . order_label($orderNo) . ' | ' . stub_label($o['stub_no']), "Customer: {$o['customer_name']} ({$o['order_type']})"];
if ($zoneRow) $txt[] = "Address: {$zoneRow['street']}, Brgy. {$zoneRow['barangay']}";
foreach ($items as $i) $txt[] = "{$i['qty']}x {$i['product_name']} - " . peso($i['qty'] * $i['unit_price_charged']);
if ($o['delivery_fee_charged'] > 0) $txt[] = 'Delivery fee - ' . peso($o['delivery_fee_charged']);
$txt[] = 'Total: ' . peso($o['order_total']) . ' | Paid: ' . peso($o['total_paid']) . ' | Balance: ' . peso($o['balance']);
$txt[] = $paid ? 'PAID IN FULL (' . ($last['receipt_no'] ?? '') . ')' : 'CLAIM STUB ONLY - NOT PROOF OF PAYMENT';

page_start(order_label($orderNo), 'orders', [
    'sub' => $o['order_type'] . ' · ' . fdatetime($o['order_date']) . ($o['is_deleted'] ? ' · ARCHIVED' : ''),
    'actions' => head_link('orders/', '← All orders', 'btn'),
]); ?>

<div class="grid g2e">
  <div>
    <section class="card">
      <h2>Order <?= status_badge($o['order_status']) ?> <?= pay_badge($o['payment_status'], $o['balance']) ?></h2>
      <dl class="facts">
        <dt>Customer</dt><dd><a href="<?= e(url('customers/view.php?customer_id=' . $o['customer_id'])) ?>"><?= e($o['customer_name']) ?></a><?= $o['contact_number'] ? ' · ' . e($o['contact_number']) : '' ?></dd>
        <?php if ($zoneRow): ?><dt>Deliver to</dt><dd><?= e($zoneRow['street']) ?>, Brgy. <?= e($zoneRow['barangay']) ?></dd><?php endif; ?>
        <?php if ($delivery): ?><dt>Rider</dt><dd><?= e($delivery['rider_name']) ?> (<?= e($delivery['vehicle']) ?>) · <?= e($delivery['delivery_status']) ?></dd><?php endif; ?>
        <?php if ($o['notes']): ?><dt>Notes</dt><dd><?= e($o['notes']) ?></dd><?php endif; ?>
        <dt>Total</dt><dd><?= peso($o['order_total']) ?></dd>
        <dt>Paid / Balance</dt><dd><?= peso($o['total_paid']) ?> / <b><?= peso($o['balance']) ?></b></dd>
      </dl>
      <div class="form-actions no-print" style="margin-top:14px">
        <?php if ($o['balance'] > 0 && $o['order_status'] !== 'Cancelled' && !$o['is_deleted']): ?>
          <a class="btn btn-aqua" href="<?= e(url('orders/pay.php?order_no=' . $orderNo)) ?>">Record payment</a><?php endif; ?>
        <?php if ($editable): ?><a class="btn" href="<?= e(url('orders/edit.php?order_no=' . $orderNo)) ?>">Edit order</a><?php endif; ?>
        <a class="btn" href="<?= e(url('orders/status.php?order_no=' . $orderNo)) ?>">Change status</a>
        <?php if ($o['is_deleted']): ?>
          <?= action_form('actions/restore.php', ['entity' => 'orders', 'id' => $orderNo, 'return' => 'orders/view.php?order_no=' . $orderNo], 'Restore', 'btn btn-sun') ?>
        <?php endif; ?>
      </div>
    </section>

    <section class="card">
      <h2>Payments</h2>
      <?php if ($payments): ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Receipt</th><th>Paid on</th><th>Mode</th><th class="num">Amount</th><th class="num">Balance after</th></tr></thead>
        <tbody><?php foreach ($payments as $p): ?>
          <tr><td><b><?= e($p['receipt_no']) ?></b></td><td class="small"><?= e(fdatetime($p['payment_date'])) ?></td><td><?= e($p['payment_mode']) ?></td>
              <td class="num"><?= peso($p['amount_paid']) ?></td><td class="num"><?= peso($p['balance_after']) ?></td></tr>
        <?php endforeach; ?></tbody></table></div>
      <?php else: ?><p class="muted" style="margin:0">No payment yet — only the claim stub has been issued.</p><?php endif; ?>
    </section>
  </div>

  <aside class="tray">
    <div class="toolbar no-print" style="justify-content:space-between">
      <div class="pills">
        <a class="pill <?= $slip === 'full' ? 'on' : '' ?>" href="?order_no=<?= $orderNo ?>&slip=full">Receipt + Claim tab</a>
        <a class="pill <?= $slip === 'stub' ? 'on' : '' ?>" href="?order_no=<?= $orderNo ?>&slip=stub">Claim stub only</a>
      </div>
    </div>

    <article class="paper" id="receipt">
      <div class="c"><span class="t"><?= e(config()['station_name']) ?></span><span><?= e(config()['station_addr']) ?></span><br>
        <span class="q">QUEUE #<?= (int)$o['queue_no'] ?></span></div>
      <hr>
      <div class="r"><span>Order No.</span><b><?= e(order_label($orderNo)) ?></b></div>
      <div class="r"><span>Stub No.</span><span><?= e(stub_label($o['stub_no'])) ?></span></div>
      <?php if ($slip === 'full' && $last): ?>
        <div class="r"><span>Receipt No.</span><b><?= e($last['receipt_no']) ?></b></div>
        <div class="r"><span>Paid on</span><span><?= e(fdatetime($last['payment_date'])) ?></span></div>
      <?php endif; ?>
      <div class="r"><span>Order date</span><span><?= e(fdatetime($o['order_date'])) ?></span></div>
      <div class="r"><span>Type</span><span><?= e($o['order_type']) ?></span></div>
      <div class="r"><span>Customer</span><span><?= e($o['customer_name']) ?></span></div>
      <?php if ($zoneRow): ?><div class="r"><span>Address</span><span><?= e($zoneRow['street']) ?>, Brgy. <?= e($zoneRow['barangay']) ?></span></div><?php endif; ?>
      <hr>
      <div class="r" style="color:#6b6753"><span>ITEM</span><span>AMOUNT</span></div>
      <?php foreach ($items as $i): ?>
        <div class="r"><span><?= e($i['product_name']) ?></span><b><?= peso($i['qty'] * $i['unit_price_charged']) ?></b></div>
        <div class="sub-i"><?= (int)$i['qty'] ?> × <?= peso($i['unit_price_charged']) ?></div>
      <?php endforeach; ?>
      <?php if ($o['order_type'] === 'Delivery'): ?><div class="r"><span>Delivery fee</span><span><?= peso($o['delivery_fee_charged']) ?></span></div><?php endif; ?>
      <hr>
      <div class="r tot"><span>TOTAL</span><span><?= peso($o['order_total']) ?></span></div>
      <?php if ($slip === 'full'): ?>
        <?php if ($last): ?><div class="r"><span>This payment (<?= e($last['payment_mode']) ?>)</span><span><?= peso($last['amount_paid']) ?></span></div><?php endif; ?>
        <div class="r"><span>Total paid</span><span><?= peso($o['total_paid']) ?></span></div>
        <div class="r tot"><span>Remaining balance</span><span><?= peso($o['balance']) ?></span></div>
      <?php endif; ?>
      <?php if ($paid): ?>
        <div class="paid-stamp">PAID IN FULL</div>
      <?php else: ?>
        <div class="warn">UNPAID — CLAIM STUB ONLY<small>Not valid as proof of payment.</small></div>
      <?php endif; ?>

      <?php if ($slip === 'full'): ?>
      <div class="tear"><span>✂ CUSTOMER CLAIM TAB</span></div>
      <div class="claim">
        <div class="top"><span>QUEUE #<?= (int)$o['queue_no'] ?></span><span><?= e(stub_label($o['stub_no'])) ?></span></div>
        <div class="r"><span>Customer</span><b><?= e($o['customer_name']) ?></b></div>
        <div class="r"><span>Containers</span><b><?= (int)$itemCount ?> item<?= $itemCount == 1 ? '' : 's' ?></b></div>
        <div class="r"><span>Status</span><b><?= $paid ? 'PAID IN FULL' : 'UNPAID · ' . peso($o['balance']) . ' DUE' ?></b></div>
      </div>
      <?php endif; ?>
      <p class="foot">Present this tab when collecting or receiving containers.</p>
    </article>

    <div class="form-actions no-print" style="justify-content:center;margin-top:16px">
      <button class="btn btn-sun" data-print>Print thermal slip</button>
      <button class="btn" data-copy="#slipText">Copy as text</button>
    </div>
    <textarea id="slipText" hidden><?= e(implode("\n", $txt)) ?></textarea>
  </aside>
</div>
<?php page_end();
