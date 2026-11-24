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

page_start('Delivery Board', 'deliveries', [
    'sub' => count($onRoad) . ' on the road · ' . count($ready) . ' waiting to be dispatched',
]); ?>

<article>
  <header>
    <h2>On the road</h2>
    <span class="badge b-info"><?= count($onRoad) ?> active</span>
  </header>
  <?php if ($onRoad): ?>
    <div class="delivery-grid">
      <?php foreach ($onRoad as $d): ?>
        <article class="delivery-card">
          <h3>
            <span class="ticket">#<?= (int)$d['queue_no'] ?></span>
            <span><?= e($d['customer_name']) ?></span>
          </h3>
          <p class="muted">
            <?= e($d['street']) ?>, Brgy. <?= e($d['barangay']) ?><br>
            <?= e($d['contact_number']) ?>
          </p>
          <p>
            <b><?= e($d['rider_name']) ?></b> <span class="muted">(<?= e($d['vehicle']) ?>) · left <?= e(ftime($d['dispatch_time'])) ?></span>
          </p>
          <?php if ($d['balance'] > 0): ?>
            <p>Collect on arrival: <span class="cod-amount"><?= peso($d['balance']) ?></span></p>
          <?php else: ?>
            <p><span class="badge b-ok">Already paid</span></p>
          <?php endif; ?>
          <div class="row-actions" style="margin-top:0.65rem">
            <?= action_form('deliveries/update.php', ['delivery_id' => $d['delivery_id'], 'result' => 'Delivered'], 'Mark delivered', 'btn-xs', 'Mark ' . $d['customer_name'] . "'s order as delivered?") ?>
            <?= action_form('deliveries/update.php', ['delivery_id' => $d['delivery_id'], 'result' => 'Failed'], 'Failed', 'outline btn-danger-outline btn-xs', 'Mark this delivery as unsuccessful?') ?>
            <a role="button" class="secondary outline btn-xs" href="<?= e(url('orders/view.php?order_no=' . $d['order_no'])) ?>">Slip</a>
            <?php if ($d['balance'] > 0): ?>
              <a role="button" class="secondary outline btn-xs" href="<?= e(url('orders/pay.php?order_no=' . $d['order_no'])) ?>">Collect</a>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="muted" style="margin-bottom:0">No riders are out on delivery right now.</p>
  <?php endif; ?>
</article>

<article class="table-card">
  <header>
    <h2>Waiting to be dispatched</h2>
    <span class="badge"><?= count($ready) ?></span>
  </header>
  <div class="overflow-auto">
    <table>
      <thead>
        <tr>
          <th>Queue</th>
          <th>Customer &amp; address</th>
          <th>Status</th>
          <th class="num">Payment</th>
          <th>Assign rider</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($ready as $o): ?>
        <tr>
          <td>
            <span class="ticket">#<?= (int)$o['queue_no'] ?></span><br>
            <span class="muted small"><?= e(fdate($o['queue_date'])) ?></span>
          </td>
          <td>
            <b><?= e($o['customer_name']) ?></b><br>
            <span class="small muted"><?= e($o['street']) ?>, Brgy. <?= e($o['barangay']) ?></span>
          </td>
          <td><?= status_badge($o['order_status']) ?></td>
          <td class="num"><?= pay_badge($o['payment_status'], $o['balance']) ?></td>
          <td>
            <form method="post" action="<?= e(url('deliveries/dispatch.php')) ?>" class="row-actions" style="margin-bottom:0">
              <?= csrf_field() ?>
              <input type="hidden" name="order_no" value="<?= (int)$o['order_no'] ?>">
              <select name="rider_id" style="width:auto;margin-bottom:0;font-size:0.8125rem;padding-top:0.25rem;padding-bottom:0.25rem" required aria-label="Rider">
                <option value="">Choose rider…</option>
                <?php foreach ($riders as $r): ?>
                  <option value="<?= (int)$r['rider_id'] ?>"><?= e($r['rider_name']) ?><?= $r['on_road'] ? ' (' . (int)$r['on_road'] . ' out)' : '' ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn-xs">Send out</button>
            </form>
          </td>
        </tr>
      <?php endforeach; if (!$ready): ?>
        <tr><td colspan="5" class="empty">No delivery orders are waiting to be dispatched.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</article>

<article class="table-card">
  <header><h2>Recent trips</h2></header>
  <div class="overflow-auto">
    <table>
      <thead>
        <tr>
          <th>Customer</th>
          <th>Rider</th>
          <th>Dispatched</th>
          <th>Completed</th>
          <th>Result</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($history as $h): ?>
        <tr>
          <td><span class="ticket">#<?= (int)$h['queue_no'] ?></span> <b><?= e($h['customer_name']) ?></b> <span class="muted small">· <?= e($h['barangay']) ?></span></td>
          <td><?= e($h['rider_name']) ?></td>
          <td class="small"><?= e(fdatetime($h['dispatch_time'])) ?></td>
          <td class="small"><?= e(fdatetime($h['completion_time'])) ?></td>
          <td><?= $h['delivery_status'] === 'Delivered' ? '<span class="badge b-ok">Delivered</span>' : '<span class="badge b-bad">Failed</span>' ?></td>
        </tr>
      <?php endforeach; if (!$history): ?>
        <tr><td colspan="5" class="empty">No finished trips yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</article>
<?php page_end();
