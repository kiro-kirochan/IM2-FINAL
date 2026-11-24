<?php
require_once __DIR__ . '/../includes/layout.php';

$orderNo = (int)($_GET['order_no'] ?? $_POST['order_no'] ?? 0);
$o = q_one('SELECT * FROM v_order_summary WHERE order_no = ?', [$orderNo]);
if (!$o || $o['is_deleted']) { flash('error', 'Order not found.'); redirect('orders/'); }
if ($o['order_status'] === 'Cancelled') { flash('error', 'Cancelled orders cannot be paid.'); redirect('orders/view.php?order_no=' . $orderNo); }
if ($o['balance'] <= 0) { flash('info', order_label($orderNo) . ' is already fully paid.'); redirect('orders/view.php?order_no=' . $orderNo); }

$error = null;
$amount = number_format((float)$o['balance'], 2, '.', '');
$mode = 'Cash';

if (is_post()) {
    $amount = post('amount');
    $mode = post('mode', 'Cash');
    if (!is_numeric($amount) || (float)$amount <= 0) {
        $error = 'Enter an amount greater than zero.';
    } elseif (!in_array($mode, ['Cash', 'GCash'], true)) {
        $error = 'Choose Cash or GCash.';
    } else {
        try {
            db()->beginTransaction();
            q('CALL sp_record_payment(?, ?, ?, @receipt)', [$orderNo, (float)$amount, $mode]);
            $receipt = q_val('SELECT @receipt');
            db()->commit();
            flash('ok', "Payment of " . peso($amount) . " recorded · receipt $receipt.");
            redirect('orders/view.php?order_no=' . $orderNo . '&slip=full');
        } catch (PDOException $e) {
            if (db()->inTransaction()) db()->rollBack();
            $error = db_message($e);
        }
    }
}

page_start('Record Payment', 'orders', [
    'sub' => order_label($orderNo) . ' · ' . $o['customer_name'],
    'actions' => head_link('orders/view.php?order_no=' . $orderNo, '← Back to slip', 'secondary outline btn-sm'),
]); ?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<article class="card-narrow">
  <header><h2>Payment summary</h2></header>
  <dl class="facts" style="margin-bottom:1.1rem">
    <dt>Order total</dt><dd><?= peso($o['order_total']) ?></dd>
    <dt>Already paid</dt><dd><?= peso($o['total_paid']) ?></dd>
    <dt>Balance due</dt><dd><strong style="font-size:1.25rem;font-variant-numeric:tabular-nums"><?= peso($o['balance']) ?></strong></dd>
  </dl>
  <hr>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="order_no" value="<?= $orderNo ?>">
    <label>
      <span class="req">Amount received (₱)</span>
      <input type="number" name="amount" step="0.01" min="0.01" max="<?= e($o['balance']) ?>" value="<?= e($amount) ?>" required autofocus>
    </label>
    <label>Payment mode</label>
    <div class="segmented" role="radiogroup" aria-label="Payment mode">
      <label><input type="radio" name="mode" value="Cash" <?= $mode === 'Cash' ? 'checked' : '' ?>><span>Cash</span></label>
      <label><input type="radio" name="mode" value="GCash" <?= $mode === 'GCash' ? 'checked' : '' ?>><span>GCash</span></label>
    </div>
    <div class="form-actions">
      <button type="submit">Save payment &amp; view receipt</button>
      <a role="button" class="secondary outline" href="<?= e(url('orders/view.php?order_no=' . $orderNo)) ?>">Cancel</a>
    </div>
  </form>
</article>
<?php page_end();
