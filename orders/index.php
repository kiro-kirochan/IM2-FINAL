<?php
require_once __DIR__ . '/../includes/layout.php';

$show = $_GET['show'] ?? 'all';
$where = ['is_deleted = 0'];
switch ($show) {
    case 'today':    $where[] = 'queue_date = CURDATE()'; break;
    case 'active':   $where[] = "order_status NOT IN ('Released','Delivered','Delivery Unsuccessful','Cancelled')"; break;
    case 'unpaid':   $where[] = "balance > 0 AND order_status <> 'Cancelled'"; break;
    case 'delivery': $where[] = "order_type = 'Delivery'"; break;
    case 'walkin':   $where[] = "order_type = 'Walk-in'"; break;
}
$orders = q_all('SELECT * FROM v_order_summary WHERE ' . implode(' AND ', $where) . ' ORDER BY order_date DESC, order_no DESC LIMIT 300');

// item summary per order (one query, not one per row)
$itemRows = q_all('SELECT oi.order_no, oi.qty, p.product_name FROM order_items oi
                   JOIN products p ON p.product_code = oi.product_code WHERE oi.is_removed = 0');
$items = [];
foreach ($itemRows as $r) $items[$r['order_no']][] = $r['qty'] . '× ' . $r['product_name'];

$filters = ['all' => 'All', 'today' => 'Today', 'active' => 'Active', 'unpaid' => 'Unpaid', 'delivery' => 'Delivery', 'walkin' => 'Walk-in'];

page_start('Orders', 'orders', [
    'sub' => count($orders) . ' order(s) shown',
    'actions' => head_link('orders/add.php', '+ New Order'),
]); ?>

<div class="toolbar">
  <input type="search" placeholder="Search order #, queue #, name, phone, barangay…" data-filter="#orderTable" autofocus>
  <div class="pills">
    <?php foreach ($filters as $k => $label): ?>
      <a class="pill <?= $show === $k ? 'on' : '' ?>" href="?show=<?= e($k) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="table-wrap"><table id="orderTable">
  <thead><tr><th>Queue / Order</th><th>Customer</th><th>Items</th><th>Status</th><th class="num">Total</th><th>Payment</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($orders as $o):
      $search = strtolower(implode(' ', [order_label($o['order_no']), 'q' . $o['queue_no'], $o['customer_name'], $o['contact_number'], $o['barangay'], $o['order_type']])); ?>
    <tr data-search="<?= e($search) ?>">
      <td><span class="ticket">#<?= (int)$o['queue_no'] ?></span> <b><?= e(order_label($o['order_no'])) ?></b>
          <br><span class="muted small"><?= e(fdatetime($o['order_date'])) ?></span></td>
      <td><b><?= e($o['customer_name']) ?></b><br>
          <span class="badge"><?= e($o['order_type']) ?><?= $o['order_type'] === 'Delivery' ? ' · ' . e($o['barangay']) : '' ?></span>
          <span class="muted small"><?= e($o['contact_number']) ?></span></td>
      <td class="small"><?= e(implode(', ', $items[$o['order_no']] ?? [])) ?></td>
      <td><?= status_badge($o['order_status']) ?></td>
      <td class="num"><b><?= peso($o['order_total']) ?></b></td>
      <td><?= pay_badge($o['payment_status'], $o['balance']) ?></td>
      <td><div class="row-actions">
        <a class="btn btn-sm" href="<?= e(url('orders/view.php?order_no=' . $o['order_no'])) ?>">View</a>
        <?php if (in_array($o['order_status'], ['Received', 'Preparing'], true)): ?>
          <a class="btn btn-sm" href="<?= e(url('orders/edit.php?order_no=' . $o['order_no'])) ?>">Edit</a>
        <?php endif; ?>
        <?php if ($o['balance'] > 0 && $o['order_status'] !== 'Cancelled'): ?>
          <a class="btn btn-sm btn-aqua" href="<?= e(url('orders/pay.php?order_no=' . $o['order_no'])) ?>">Pay</a>
        <?php endif; ?>
        <a class="btn btn-sm" href="<?= e(url('orders/status.php?order_no=' . $o['order_no'])) ?>">Status</a>
        <?= action_form('actions/archive.php', ['entity' => 'orders', 'id' => $o['order_no'], 'return' => 'orders/'], 'Archive', 'btn btn-sm btn-danger',
              'Archive ' . order_label($o['order_no']) . '? It is hidden from the app but kept in the database. You can restore it from the Archive page.') ?>
      </div></td>
    </tr>
  <?php endforeach; if (!$orders): ?>
    <tr><td colspan="7" class="empty">No orders match this filter.</td></tr>
  <?php endif; ?>
  </tbody></table></div>

<?php page_end();
