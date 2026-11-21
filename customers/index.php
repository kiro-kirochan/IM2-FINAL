<?php
require_once __DIR__ . '/../includes/layout.php';

$rows = q_all("SELECT c.*, z.barangay,
    (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.customer_id AND o.is_deleted = 0) AS orders_count,
    (SELECT COALESCE(SUM(v.balance),0) FROM v_order_summary v WHERE v.customer_id = c.customer_id AND v.is_deleted = 0 AND v.order_status <> 'Cancelled') AS owes,
    (SELECT COALESCE(SUM(l.qty - l.qty_returned),0) FROM container_loans l WHERE l.customer_id = c.customer_id AND l.is_deleted = 0) AS containers
  FROM customers c LEFT JOIN delivery_zones z ON z.zone_id = c.zone_id
  WHERE c.is_deleted = 0 ORDER BY c.is_system DESC, c.full_name");

page_start('Customers', 'customers', ['sub' => count($rows) . ' customer(s)', 'actions' => head_link('customers/add.php', '+ Add Customer')]); ?>
<div class="toolbar"><input type="search" placeholder="Search name, phone, street, barangay…" data-filter="#custTable" autofocus></div>
<div class="table-wrap"><table id="custTable">
  <thead><tr><th>Name</th><th>Contact</th><th>Address</th><th class="num">Orders</th><th class="num">Owes</th><th class="num">Containers held</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $c): ?>
    <tr data-search="<?= e(strtolower($c['full_name'] . ' ' . $c['contact_number'] . ' ' . $c['street'] . ' ' . $c['barangay'])) ?>">
      <td><b><?= e($c['full_name']) ?></b><?= $c['is_system'] ? ' <span class="badge">built-in</span>' : '' ?></td>
      <td><?= e($c['contact_number'] ?: '—') ?></td>
      <td><?= $c['street'] ? e($c['street']) . ', Brgy. ' . e($c['barangay']) : '<span class="muted">—</span>' ?></td>
      <td class="num"><?= (int)$c['orders_count'] ?></td>
      <td class="num"><?= $c['owes'] > 0 ? '<span class="badge b-bad">' . peso($c['owes']) . '</span>' : '—' ?></td>
      <td class="num"><?= $c['containers'] > 0 ? '<span class="badge b-sun">' . (int)$c['containers'] . '</span>' : '—' ?></td>
      <td><div class="row-actions">
        <a class="btn btn-sm" href="<?= e(url('customers/view.php?customer_id=' . $c['customer_id'])) ?>">View</a>
        <?php if (!$c['is_system']): ?>
          <a class="btn btn-sm" href="<?= e(url('customers/edit.php?customer_id=' . $c['customer_id'])) ?>">Edit</a>
          <?= action_form('actions/archive.php', ['entity' => 'customers', 'id' => $c['customer_id'], 'return' => 'customers/'], 'Archive', 'btn btn-sm btn-danger',
                'Archive ' . $c['full_name'] . '? Their order history stays in the database.') ?>
        <?php endif; ?>
      </div></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php page_end();
