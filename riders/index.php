<?php
require_once __DIR__ . '/../includes/layout.php';
$rows = q_all('SELECT r.*, (SELECT COUNT(*) FROM deliveries d WHERE d.rider_id = r.rider_id) AS trips,
                      (SELECT COUNT(*) FROM deliveries d WHERE d.rider_id = r.rider_id AND d.delivery_status = "Dispatched") AS on_road
               FROM riders r WHERE r.is_deleted = 0 ORDER BY r.rider_name');
page_start('Riders', 'riders', ['sub' => 'Delivery staff', 'actions' => head_link('riders/add.php', '+ Add Rider')]); ?>
<div class="table-wrap"><table>
  <thead><tr><th>Rider</th><th>Vehicle</th><th class="num">Trips</th><th>Now</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><b><?= e($r['rider_name']) ?></b></td><td><?= e($r['vehicle']) ?></td><td class="num"><?= (int)$r['trips'] ?></td>
        <td><?= $r['on_road'] ? '<span class="badge b-aqua">Out on delivery</span>' : '<span class="badge b-ok">Available</span>' ?></td>
        <td><div class="row-actions"><a class="btn btn-sm" href="<?= e(url('riders/edit.php?rider_id=' . $r['rider_id'])) ?>">Edit</a>
          <?= action_form('actions/archive.php', ['entity' => 'riders', 'id' => $r['rider_id'], 'return' => 'riders/'], 'Archive', 'btn btn-sm btn-danger', 'Archive ' . $r['rider_name'] . '?') ?></div></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="5" class="empty">No riders yet.</td></tr><?php endif; ?>
</tbody></table></div>
<?php page_end();
