<?php
// The Archive: everything that was "deleted" in the app. It is all still in MySQL; Restore brings it back.
require_once __DIR__ . '/../includes/layout.php';

$sections = [
    'orders' => ['Orders', "SELECT order_no AS id, CONCAT('ORD-', order_no, ' · Q#', queue_no, ' · ', customer_name, ' · ', FORMAT(order_total,2)) AS label, deleted_at FROM v_order_summary WHERE is_deleted = 1"],
    'customers' => ['Customers', "SELECT customer_id AS id, CONCAT(full_name, IFNULL(CONCAT(' · ', contact_number), '')) AS label, deleted_at FROM customers WHERE is_deleted = 1"],
    'products' => ['Products', "SELECT product_code AS id, CONCAT(product_code, ' · ', product_name) AS label, deleted_at FROM products WHERE is_deleted = 1"],
    'riders' => ['Riders', "SELECT rider_id AS id, CONCAT(rider_name, ' · ', vehicle) AS label, deleted_at FROM riders WHERE is_deleted = 1"],
    'zones' => ['Barangays', "SELECT zone_id AS id, CONCAT('Brgy. ', barangay, ' · ₱', delivery_fee) AS label, deleted_at FROM delivery_zones WHERE is_deleted = 1"],
    'containers' => ['Container loans', "SELECT l.loan_id AS id, CONCAT(c.full_name, ' · ', l.qty, ' × ', l.container_type) AS label, l.deleted_at FROM container_loans l JOIN customers c ON c.customer_id = l.customer_id WHERE l.is_deleted = 1"],
];
$total = 0;
$data = [];
foreach ($sections as $key => [$title, $sql]) { $data[$key] = q_all($sql . ' ORDER BY deleted_at DESC'); $total += count($data[$key]); }

page_start('Archive', 'trash', ['sub' => $total . ' archived record(s). Archiving hides a record in the app only; the database keeps it.']); ?>
<?php foreach ($sections as $key => [$title]): if (!$data[$key]) continue; ?>
  <section class="card">
    <h2><?= e($title) ?> <span class="badge"><?= count($data[$key]) ?></span></h2>
    <div class="table-wrap"><table><thead><tr><th>Record</th><th>Archived on</th><th></th></tr></thead><tbody>
      <?php foreach ($data[$key] as $row): ?>
        <tr><td><?= e($row['label']) ?></td><td class="small"><?= e(fdatetime($row['deleted_at'])) ?></td>
            <td><?= action_form('actions/restore.php', ['entity' => $key, 'id' => $row['id'], 'return' => 'trash/'], 'Restore', 'btn btn-sm btn-sun') ?>
                <?php if ($key === 'orders'): ?><a class="btn btn-sm" href="<?= e(url('orders/view.php?order_no=' . $row['id'])) ?>">View</a><?php endif; ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </section>
<?php endforeach; if (!$total): ?>
  <section class="card"><p class="muted" style="margin:0">Nothing archived. Use the red <b>Archive</b> buttons in Orders, Customers, Products etc.; archived records show up here and can be restored.</p></section>
<?php endif; ?>
<?php page_end();
