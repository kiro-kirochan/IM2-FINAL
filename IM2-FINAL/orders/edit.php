<?php
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/order_builder.php';

$orderNo = (int)($_GET['order_no'] ?? $_POST['order_no'] ?? 0);
$o = q_one('SELECT * FROM v_order_summary WHERE order_no = ?', [$orderNo]);
if (!$o || $o['is_deleted']) { flash('error', 'Order not found.'); redirect('orders/'); }
if (!in_array($o['order_status'], ['Received', 'Preparing'], true)) {
    flash('error', order_label($orderNo) . ' is already ' . $o['order_status'] . ' and can no longer be edited.');
    redirect('orders/view.php?order_no=' . $orderNo);
}

$customers = active_customers();
if (!in_array($o['customer_id'], array_column($customers, 'customer_id'))) {
    $customers[] = q_one('SELECT c.*, z.barangay, z.delivery_fee FROM customers c LEFT JOIN delivery_zones z ON z.zone_id = c.zone_id WHERE customer_id = ?', [$o['customer_id']]);
}
$products = active_products();
$existing = [];
foreach (q_all('SELECT product_code, qty, unit_price_charged, is_removed FROM order_items WHERE order_no = ?', [$orderNo]) as $r) $existing[$r['product_code']] = $r;

$lines = [];
foreach ($existing as $code => $r) if (!$r['is_removed']) $lines[] = ['code' => $code, 'qty' => $r['qty'], 'price' => $r['unit_price_charged']];
$error = null;
$form = ['customer_id' => $o['customer_id'], 'notes' => (string)$o['notes']];

if (is_post()) {
    $form = ['customer_id' => (int)post('customer_id'), 'notes' => post('notes')];
    $submitted = [];
    $validCodes = array_column(q_all('SELECT product_code FROM products'), 'product_code');
    foreach ((array)($_POST['items'] ?? []) as $code => $qty) {
        $qty = (int)$qty;
        if (in_array($code, $validCodes, true) && $qty >= 1 && $qty <= 999) $submitted[$code] = $qty;
    }
    if (!$submitted) $error = 'An order must contain at least one item.';

    if (!$error) {
        try {
            db()->beginTransaction();
            q('UPDATE orders SET customer_id = ?, notes = ? WHERE order_no = ?', [$form['customer_id'], $form['notes'] !== '' ? $form['notes'] : null, $orderNo]);
            foreach ($submitted as $code => $qty) {          // add / change first …
                if (isset($existing[$code])) {
                    if ($existing[$code]['is_removed'] || (int)$existing[$code]['qty'] !== $qty) {
                        q('UPDATE order_items SET qty = ?, is_removed = 0 WHERE order_no = ? AND product_code = ?', [$qty, $orderNo, $code]);
                    }
                } else {
                    q('INSERT INTO order_items (order_no, product_code, qty, unit_price_charged) VALUES (?, ?, ?, 0)', [$orderNo, $code, $qty]);
                }
            }
            foreach ($existing as $code => $r) {              // … then soft-remove what was taken out
                if (!$r['is_removed'] && !isset($submitted[$code])) {
                    q('UPDATE order_items SET is_removed = 1 WHERE order_no = ? AND product_code = ?', [$orderNo, $code]);
                }
            }
            $now = q_one('SELECT order_total, total_paid FROM v_order_summary WHERE order_no = ?', [$orderNo]);
            if ((float)$now['total_paid'] > (float)$now['order_total']) {
                throw new RuntimeException('The new total (' . peso($now['order_total']) . ') is lower than what was already paid (' . peso($now['total_paid']) . ').');
            }
            db()->commit();
            flash('ok', 'Saved changes to ' . order_label($orderNo) . '.');
            redirect('orders/view.php?order_no=' . $orderNo);
        } catch (PDOException $e) {
            if (db()->inTransaction()) db()->rollBack();
            $error = db_message($e);
        } catch (RuntimeException $e) {
            if (db()->inTransaction()) db()->rollBack();
            $error = $e->getMessage();
        }
        $lines = array_map(fn($c, $q) => ['code' => $c, 'qty' => $q], array_keys($submitted), $submitted);
    }
}

$CD = builder_data($customers, $lines, $o['order_type']);
page_start('Edit ' . order_label($orderNo), 'orders', [
    'sub' => $o['order_type'] . ' order · Queue #' . $o['queue_no'] . ' · ' . $o['order_status'],
    'scripts' => ['assets/js/order-form.js'],
    'actions' => head_link('orders/view.php?order_no=' . $orderNo, '← Back to slip', 'secondary outline btn-sm'),
]); ?>
<script>window.CD = <?= json_encode($CD, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<form method="post" id="orderForm" novalidate>
  <?= csrf_field() ?><input type="hidden" name="order_no" value="<?= $orderNo ?>">
  <div class="grid-split">
    <article>
      <header><h2>Order details</h2></header>
      <div class="callout">
        Order type (<b><?= e($o['order_type']) ?></b>) and recorded payments cannot be modified here. Record additional payments from the <b>Pay</b> page. Removed items stay in the database marked as removed.
      </div>
      <label>
        <span class="req">Customer</span>
        <select name="customer_id" id="customer_id">
          <?php foreach ($customers as $c): ?>
            <option value="<?= (int)$c['customer_id'] ?>" <?= (int)$form['customer_id'] === (int)$c['customer_id'] ? 'selected' : '' ?>>
              <?= e($c['full_name']) ?><?= $c['barangay'] ? ' — ' . e($c['barangay']) : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="callout callout-warn" id="deliveryWarn" hidden>
        This customer lacks a contact number, street, or barangay, which delivery requires.
      </div>
      <label>
        <span>Notes</span>
        <input name="notes" maxlength="255" value="<?= e($form['notes']) ?>">
      </label>
      <dl class="facts">
        <dt>Already paid</dt><dd><?= peso($o['total_paid']) ?></dd>
        <dt>Claim stub</dt><dd><?= e(stub_label($o['stub_no'])) ?></dd>
      </dl>
    </article>

    <article>
      <header><h2>Order items</h2></header>
      <?php render_item_builder($products); ?>
      <div class="form-actions" style="margin-top:1rem">
        <button type="submit">Save changes</button>
        <a role="button" class="secondary outline" href="<?= e(url('orders/view.php?order_no=' . $orderNo)) ?>">Cancel</a>
      </div>
    </article>
  </div>
</form>
<?php page_end();
