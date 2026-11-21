<?php
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/order_builder.php';

$customers = active_customers();
$products  = active_products();
$walkinId  = (int)(q_val('SELECT customer_id FROM customers WHERE is_system = 1 AND is_deleted = 0 LIMIT 1') ?? 0);

$old = ['order_type' => 'Walk-in', 'customer_id' => $walkinId, 'notes' => '', 'pay_option' => 'unpaid', 'mode' => 'Cash', 'amount' => ''];
$lines = [];
$error = null;

if (is_post()) {
    $old = array_merge($old, [
        'order_type' => post('order_type'), 'customer_id' => (int)post('customer_id'), 'notes' => post('notes'),
        'pay_option' => post('pay_option', 'unpaid'), 'mode' => post('mode', 'Cash'), 'amount' => post('amount'),
    ]);
    $valid = array_column($products, 'product_code');
    foreach ((array)($_POST['items'] ?? []) as $code => $qty) {
        $qty = (int)$qty;
        if (in_array($code, $valid, true) && $qty >= 1 && $qty <= 999) $lines[] = ['code' => $code, 'qty' => $qty];
    }

    if (!in_array($old['order_type'], ['Walk-in', 'Delivery'], true)) $error = 'Choose Walk-in or Delivery.';
    elseif (!$old['customer_id']) $error = 'Choose a customer.';
    elseif (!$lines) $error = 'Add at least one item.';
    elseif (!in_array($old['pay_option'], ['unpaid', 'full', 'partial'], true) || !in_array($old['mode'], ['Cash', 'GCash'], true)) $error = 'Invalid payment option.';
    elseif ($old['pay_option'] === 'partial' && !((float)$old['amount'] > 0)) $error = 'Enter the amount received for a partial payment.';

    if (!$error) {
        try {
            db()->beginTransaction();
            q('INSERT INTO orders (customer_id, order_type, notes) VALUES (?, ?, ?)',
              [$old['customer_id'], $old['order_type'], $old['notes'] !== '' ? $old['notes'] : null]);
            $orderNo = (int)db()->lastInsertId();
            foreach ($lines as $l) {
                // price is copied from PRODUCTS by trigger trg_items_bi (0 is just a placeholder)
                q('INSERT INTO order_items (order_no, product_code, qty, unit_price_charged) VALUES (?, ?, ?, 0)', [$orderNo, $l['code'], $l['qty']]);
            }
            $receipt = null;
            if ($old['pay_option'] !== 'unpaid') {
                $total = (float)q_val('SELECT order_total FROM v_order_summary WHERE order_no = ?', [$orderNo]);
                $amt = $old['pay_option'] === 'full' ? $total : (float)$old['amount'];
                q('CALL sp_record_payment(?, ?, ?, @receipt)', [$orderNo, $amt, $old['mode']]);
                $receipt = q_val('SELECT @receipt');
            }
            db()->commit();
            flash('ok', 'Saved ' . order_label($orderNo) . ($receipt ? " · receipt $receipt." : '. Claim stub issued (not proof of payment).'));
            redirect('orders/view.php?order_no=' . $orderNo);
        } catch (PDOException $e) {
            if (db()->inTransaction()) db()->rollBack();
            $error = db_message($e);
        }
    }
}

$CD = builder_data($customers, $lines, $old['order_type']);
page_start('New Order', 'orders', ['sub' => 'Pick the customer, add items, then take payment or issue an unpaid claim stub.',
    'scripts' => ['assets/js/order-form.js'], 'actions' => head_link('orders/', '← All orders', 'btn')]); ?>
<script>window.CD = <?= json_encode($CD, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>

<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<form method="post" id="orderForm" novalidate>
  <?= csrf_field() ?>
  <div class="grid g2">
    <section class="card">
      <h2>Who and how</h2>
      <div class="lbl" style="margin-bottom:6px">Order type</div>
      <div class="choice">
        <label><input type="radio" name="order_type" value="Walk-in" <?= $old['order_type'] === 'Walk-in' ? 'checked' : '' ?>><span>Walk-in</span></label>
        <label><input type="radio" name="order_type" value="Delivery" <?= $old['order_type'] === 'Delivery' ? 'checked' : '' ?>><span>Delivery</span></label>
      </div>

      <label class="f"><span class="req">Customer</span>
        <select name="customer_id" id="customer_id">
          <?php foreach ($customers as $c): ?>
            <option value="<?= (int)$c['customer_id'] ?>" <?= (int)$old['customer_id'] === (int)$c['customer_id'] ? 'selected' : '' ?>>
              <?= e($c['full_name']) ?><?= $c['barangay'] ? ' — ' . e($c['barangay']) : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <small>Not listed? <a href="<?= e(url('customers/add.php')) ?>" target="_blank">Add a customer</a>, then reload this page.</small>
      </label>
      <div class="note-box" id="deliveryWarn" hidden>
        ⚠ This customer has no contact number, street or barangay yet. Delivery needs all three —
        <a href="<?= e(url('customers/')) ?>" target="_blank">update the customer</a> first.
      </div>

      <label class="f"><span>Notes <small>(landmark, gate color, special request)</small></span>
        <input name="notes" maxlength="255" value="<?= e($old['notes']) ?>"></label>

      <h2 style="margin-top:18px">Payment</h2>
      <div class="cols-2">
        <label class="f"><span>Payment</span>
          <select name="pay_option" id="payOption">
            <option value="unpaid"  <?= $old['pay_option'] === 'unpaid'  ? 'selected' : '' ?>>Unpaid — issue claim stub</option>
            <option value="full"    <?= $old['pay_option'] === 'full'    ? 'selected' : '' ?>>Paid in full</option>
            <option value="partial" <?= $old['pay_option'] === 'partial' ? 'selected' : '' ?>>Partial payment</option>
          </select></label>
        <label class="f" id="modeBox"><span>Payment mode</span>
          <select name="mode"><option <?= $old['mode'] === 'Cash' ? 'selected' : '' ?>>Cash</option><option <?= $old['mode'] === 'GCash' ? 'selected' : '' ?>>GCash</option></select></label>
      </div>
      <div class="cols-2" id="partialBox" hidden>
        <label class="f"><span>Amount received (₱)</span><input type="number" step="0.01" min="0.01" name="amount" id="amount" value="<?= e($old['amount']) ?>"></label>
        <div class="f"><span class="lbl">Remaining balance</span><b id="balanceText" style="font:700 22px var(--head)">₱0.00</b></div>
      </div>
      <p class="muted small" id="balanceLine" style="margin:0">An unpaid order prints a claim stub that clearly says <b>“not proof of payment”</b>.</p>
    </section>

    <section class="card">
      <h2>Items</h2>
      <?php render_item_builder($products); ?>
      <div class="form-actions" style="margin-top:16px">
        <button class="btn btn-primary">Save order &amp; issue slip</button>
        <a class="btn" href="<?= e(url('orders/')) ?>">Cancel</a>
      </div>
    </section>
  </div>
</form>
<?php page_end();
