<?php
require_once __DIR__ . '/../includes/layout.php';
$v = ['barangay' => '', 'delivery_fee' => ''];
$err = [];
if (is_post()) {
    $v = ['barangay' => post('barangay'), 'delivery_fee' => post('delivery_fee')];
    if ($v['barangay'] === '') $err['barangay'] = 'Barangay name is required.';
    if (!is_numeric($v['delivery_fee']) || (float)$v['delivery_fee'] < 0) $err['delivery_fee'] = 'Enter a fee of 0 or more.';
    if (!$err) {
        try {
            q('INSERT INTO delivery_zones (barangay, delivery_fee) VALUES (?, ?)', [$v['barangay'], (float)$v['delivery_fee']]);
            flash('ok', 'Brgy. ' . $v['barangay'] . ' added.');
            redirect('zones/');
        } catch (PDOException $e) { $err['barangay'] = db_message($e); }
    }
}
page_start('Add Barangay', 'zones', [
    'actions' => head_link('zones/', '← All barangays', 'secondary outline btn-sm'),
]); ?>
<article class="card-narrow">
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <label>
      <span class="req">Barangay</span>
      <input name="barangay" maxlength="100" value="<?= e($v['barangay']) ?>" <?= isset($err['barangay']) ? 'aria-invalid="true"' : '' ?> autofocus>
      <?php if (isset($err['barangay'])): ?><small class="field-error"><?= e($err['barangay']) ?></small><?php endif; ?>
    </label>
    <label>
      <span class="req">Delivery fee (₱)</span>
      <input type="number" step="0.01" min="0" name="delivery_fee" value="<?= e($v['delivery_fee']) ?>" <?= isset($err['delivery_fee']) ? 'aria-invalid="true"' : '' ?>>
      <?php if (isset($err['delivery_fee'])): ?><small class="field-error"><?= e($err['delivery_fee']) ?></small><?php endif; ?>
    </label>
    <div class="form-actions">
      <button type="submit">Save barangay</button>
      <a role="button" class="secondary outline" href="<?= e(url('zones/')) ?>">Cancel</a>
    </div>
  </form>
</article>
<?php page_end();
