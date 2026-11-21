<?php
require_once __DIR__ . '/../includes/layout.php';

$rows = q_all('SELECT p.*, (SELECT COALESCE(SUM(oi.qty),0) FROM order_items oi WHERE oi.product_code = p.product_code AND oi.is_removed = 0) AS sold
               FROM products p WHERE p.is_deleted = 0 ORDER BY p.service_type, p.product_name');
page_start('Products', 'products', ['sub' => 'Refills and containers. Price changes never alter past orders.', 'actions' => head_link('products/add.php', '+ Add Product')]); ?>
<div class="table-wrap"><table>
  <thead><tr><th>Code</th><th>Product</th><th>Container</th><th>Service</th><th class="num">Price</th><th class="num">Units sold</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $p): ?>
    <tr><td><span class="ticket"><?= e($p['product_code']) ?></span></td><td><b><?= e($p['product_name']) ?></b></td>
        <td><?= e($p['container_type']) ?></td><td><?= e($p['service_type']) ?></td>
        <td class="num"><b><?= peso($p['current_unit_price']) ?></b></td><td class="num"><?= (int)$p['sold'] ?></td>
        <td><div class="row-actions">
          <a class="btn btn-sm" href="<?= e(url('products/edit.php?code=' . urlencode($p['product_code']))) ?>">Edit</a>
          <?= action_form('actions/archive.php', ['entity' => 'products', 'id' => $p['product_code'], 'return' => 'products/'], 'Archive', 'btn btn-sm btn-danger',
              'Archive ' . $p['product_name'] . '? It disappears from the order form; old orders keep it.') ?>
        </div></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="empty">No products.</td></tr><?php endif; ?>
  </tbody></table></div>
<?php page_end();
