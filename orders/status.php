<?php
require_once __DIR__ . '/../includes/layout.php';

$orderNo = (int)($_GET['order_no'] ?? $_POST['order_no'] ?? 0);
$o = q_one('SELECT * FROM v_order_summary WHERE order_no = ?', [$orderNo]);
if (!$o || $o['is_deleted']) { flash('error', 'Order not found.'); redirect('orders/'); }

$isDelivery = $o['order_type'] === 'Delivery';
$locked = in_array($o['order_status'], ['Released', 'Delivered', 'Cancelled'], true);
// Delivery trips are controlled from the Delivery Board (so the rider record always matches).
$options = $isDelivery
    ? ['Received', 'Preparing', 'To be Delivered', 'Cancelled']
    : STATUS_WALKIN;
$error = null;

if (!$locked && is_post()) {
    $new = post('status');
    if (!in_array($new, $options, true)) {
        $error = 'That status is not allowed here.';
    } elseif ($new === $o['order_status']) {
        flash('info', 'Status unchanged.');
        redirect('orders/view.php?order_no=' . $orderNo);
    } else {
        try {
            q('UPDATE orders SET order_status = ? WHERE order_no = ?', [$new, $orderNo]);
            flash('ok', order_label($orderNo) . ' is now “' . $new . '”.');
            redirect('orders/view.php?order_no=' . $orderNo);
        } catch (PDOException $e) {
            $error = db_message($e);
        }
    }
}

page_start('Change Status', 'orders', [
    'sub' => order_label($orderNo) . ' · ' . $o['customer_name'],
    'actions' => head_link('orders/', '← All orders', 'secondary outline btn-sm'),
]); ?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<article class="card-narrow">
  <header>
    <h2>Order status</h2>
    <?= status_badge($o['order_status']) ?>
  </header>

  <?php if ($locked): ?>
    <div class="callout" style="margin-bottom:0">
      This order is finished (<b><?= e($o['order_status']) ?></b>), so its status is locked. You can archive it from the Orders list if you no longer want to display it.
    </div>
  <?php else: ?>
    <?php if ($isDelivery): ?>
      <div class="callout">
        Dispatching an order or marking it delivered/failed is handled on the <a href="<?= e(url('deliveries/')) ?>">Delivery Board</a> so the rider and trip times are recorded.
      </div>
    <?php endif; ?>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="order_no" value="<?= $orderNo ?>">
      <label>
        <span>New status</span>
        <select name="status">
          <?php foreach ($options as $s): ?><option <?= $s === $o['order_status'] ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
        </select>
        <small class="muted">“Cancelled” stops further payments and cannot be reopened.</small>
      </label>
      <div class="form-actions">
        <button type="submit">Update status</button>
        <a role="button" class="secondary outline" href="<?= e(url('orders/view.php?order_no=' . $orderNo)) ?>">Cancel</a>
      </div>
    </form>
  <?php endif; ?>
</article>
<?php page_end();
