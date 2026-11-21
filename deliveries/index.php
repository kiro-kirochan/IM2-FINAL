<?php
require_once __DIR__ . '/../includes/layout.php';

// Orders ready to be sent out (Delivery type, not finished, nobody on the road with them)
$ready = q_all("SELECT v.* FROM v_order_summary v
                WHERE v.is_deleted = 0 AND v.order_type = 'Delivery'
                  AND v.order_status IN ('Received','Preparing','To be Delivered')
                ORDER BY v.queue_date, v.queue_no");
$onRoad = q_all("SELECT d.*, r.rider_name, r.vehicle, v.queue_no, v.customer_name, v.contact_number, v.street, v.barangay, v.balance, v.order_total
                 FROM deliveries d JOIN riders r ON r.rider_id = d.rider_id JOIN v_order_summary v ON v.order_no = d.order_no
                 WHERE d.delivery_status = 'Dispatched' ORDER BY d.dispatch_time");
$history = q_all("SELECT d.*, r.rider_name, v.queue_no, v.customer_name, v.barangay
                  FROM deliveries d JOIN riders r ON r.rider_id = d.rider_id JOIN v_order_summary v ON v.order_no = d.order_no
                  WHERE d.delivery_status <> 'Dispatched' ORDER BY d.completion_time DESC LIMIT 15");
$riders = active_riders();

page_start('Delivery Board', 'deliveries', ['sub' => count($onRoad) . ' on the road · ' . count($ready) . ' waiting to be sent']); ?>

<section class="card">
  <h2>On the road</h2>
  <div class="lane">
  <?php foreach ($onRoad as $d): ?>
    <div class="job">
      <h3><span><span class="ticket">#<?= (int)$d['queue_no'] ?></span> <?= e($d['customer_name']) ?></span></h3>
      <p class="small"><?= e($d['street']) ?>, Brgy. <?= e($d['barangay']) ?><br>☎ <?= e($d['contact_number']) ?></p>
      <p class="small">🛵 <b><?= e($d['rider_name']) ?></b> (<?= e($d['vehicle']) ?>) · left <?= e(ftime($d['dispatch_time'])) ?></p>
      <?php if ($d['balance'] > 0): ?><p>Collect on arrival: <span class="cod"><?= peso($d['balance']) ?></span></p>
      <?php else: ?><p><span class="badge b-ok">Already paid</span></p><?php endif; ?>
      <div class="row-actions">
        <?= action_form('deliveries/update.php', ['delivery_id' => $d['delivery_id'], 'result' => 'Delivered'], 'Mark delivered', 'btn btn-sm btn-aqua', 'Mark ' . $d['customer_name'] . "'s order as delivered?") ?>
        <?= action_form('deliveries/update.php', ['delivery_id' => $d['delivery_id'], 'result' => 'Failed'], 'Failed', 'btn btn-sm btn-danger', 'Mark this delivery as unsuccessful?') ?>
        <a class="btn btn-sm" href="<?= e(url('orders/view.php?order_no=' . $d['order_no'])) ?>">Slip</a>
        <?php if ($d['balance'] > 0): ?><a class="btn btn-sm btn-sun" href="<?= e(url('orders/pay.php?order_no=' . $d['order_no'])) ?>">Collect</a><?php endif; ?>
      </div>
    </div>
  <?php endforeach; if (!$onRoad): ?><p class="muted" style="margin:0">Nobody is out right now.</p><?php endif; ?>
  </div>
</section>

<section class="card">
  <h2>Waiting to be sent</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Queue</th><th>Customer / address</th><th>Status</th><th class="num">Balance</th><th>Dispatch with</th></tr></thead><tbody>
    <?php foreach ($ready as $o): ?>
      <tr><td><span class="ticket">#<?= (int)$o['queue_no'] ?></span><br><span class="muted small"><?= e(fdate($o['queue_date'])) ?></span></td>
          <td><b><?= e($o['customer_name']) ?></b><br><span class="small muted"><?= e($o['street']) ?>, Brgy. <?= e($o['barangay']) ?></span></td>
          <td><?= status_badge($o['order_status']) ?></td><td class="num"><?= pay_badge($o['payment_status'], $o['balance']) ?></td>
          <td>
            <form method="post" action="<?= e(url('deliveries/dispatch.php')) ?>" class="row-actions">
              <?= csrf_field() ?><input type="hidden" name="order_no" value="<?= (int)$o['order_no'] ?>">
              <select name="rider_id" style="width:auto" required aria-label="Rider">
                <option value="">Choose rider…</option>
                <?php foreach ($riders as $r): ?><option value="<?= (int)$r['rider_id'] ?>"><?= e($r['rider_name']) ?><?= $r['on_road'] ? ' (' . (int)$r['on_road'] . ' out)' : '' ?></option><?php endforeach; ?>
              </select>
              <button class="btn btn-sm btn-primary">Send out</button>
            </form>
          </td></tr>
    <?php endforeach; if (!$ready): ?><tr><td colspan="5" class="empty">No delivery orders are waiting.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>

<section class="card">
  <h2>Recent trips</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Customer</th><th>Rider</th><th>Left</th><th>Finished</th><th>Result</th></tr></thead><tbody>
    <?php foreach ($history as $h): ?>
      <tr><td><span class="ticket">#<?= (int)$h['queue_no'] ?></span> <?= e($h['customer_name']) ?> <span class="muted small">· <?= e($h['barangay']) ?></span></td>
          <td><?= e($h['rider_name']) ?></td><td class="small"><?= e(fdatetime($h['dispatch_time'])) ?></td><td class="small"><?= e(fdatetime($h['completion_time'])) ?></td>
          <td><?= $h['delivery_status'] === 'Delivered' ? '<span class="badge b-ok">Delivered</span>' : '<span class="badge b-bad">Failed</span>' ?></td></tr>
    <?php endforeach; if (!$history): ?><tr><td colspan="5" class="empty">No finished trips yet.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<?php page_end();
