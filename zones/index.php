<?php
require_once __DIR__ . '/../includes/layout.php';
$rows = q_all('SELECT z.*, (SELECT COUNT(*) FROM customers c WHERE c.zone_id = z.zone_id AND c.is_deleted = 0) AS customers
               FROM delivery_zones z WHERE z.is_deleted = 0 ORDER BY z.barangay');
page_start('Barangay Fees', 'zones', ['sub' => 'Flat delivery fee per barangay', 'actions' => head_link('zones/add.php', '+ Add Barangay')]); ?>
<div class="table-wrap"><table>
  <thead><tr><th>Barangay</th><th class="num">Delivery fee</th><th class="num">Customers</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($rows as $z): ?>
    <tr><td><b><?= e($z['barangay']) ?></b></td><td class="num"><?= peso($z['delivery_fee']) ?></td><td class="num"><?= (int)$z['customers'] ?></td>
        <td><div class="row-actions"><a class="btn btn-sm" href="<?= e(url('zones/edit.php?zone_id=' . $z['zone_id'])) ?>">Edit</a>
          <?= action_form('actions/archive.php', ['entity' => 'zones', 'id' => $z['zone_id'], 'return' => 'zones/'], 'Archive', 'btn btn-sm btn-danger', 'Archive Brgy. ' . $z['barangay'] . '?') ?></div></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="4" class="empty">No barangays yet.</td></tr><?php endif; ?>
</tbody></table></div>
<p class="muted small" style="margin-top:12px">Changing a fee only affects <b>new</b> orders; each order keeps the fee it was charged.</p>
<?php page_end();
