<?php
require_once __DIR__ . '/../includes/layout.php';
$v = ['rider_name' => '', 'vehicle' => 'Motorcycle'];
$err = [];
if (is_post()) {
    $v = ['rider_name' => post('rider_name'), 'vehicle' => post('vehicle')];
    if ($v['rider_name'] === '') $err['rider_name'] = 'Name is required.';
    if ($v['vehicle'] === '') $err['vehicle'] = 'Vehicle is required.';
    if (!$err) {
        q('INSERT INTO riders (rider_name, vehicle) VALUES (?, ?)', [$v['rider_name'], $v['vehicle']]);
        flash('ok', $v['rider_name'] . ' was added.');
        redirect('riders/');
    }
}
page_start('Add Rider', 'riders', [
    'actions' => head_link('riders/', '← All riders', 'secondary outline btn-sm'),
]); ?>
<article class="card-narrow">
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <label>
      <span class="req">Rider name</span>
      <input name="rider_name" maxlength="100" value="<?= e($v['rider_name']) ?>" <?= isset($err['rider_name']) ? 'aria-invalid="true"' : '' ?> autofocus>
      <?php if (isset($err['rider_name'])): ?><small class="field-error"><?= e($err['rider_name']) ?></small><?php endif; ?>
    </label>
    <label>
      <span class="req">Vehicle</span>
      <input name="vehicle" maxlength="50" list="vehicles" value="<?= e($v['vehicle']) ?>" <?= isset($err['vehicle']) ? 'aria-invalid="true"' : '' ?>>
      <datalist id="vehicles">
        <option>Motorcycle</option>
        <option>Tricycle</option>
        <option>Bicycle</option>
        <option>Multicab</option>
      </datalist>
      <?php if (isset($err['vehicle'])): ?><small class="field-error"><?= e($err['vehicle']) ?></small><?php endif; ?>
    </label>
    <div class="form-actions">
      <button type="submit">Save rider</button>
      <a role="button" class="secondary outline" href="<?= e(url('riders/')) ?>">Cancel</a>
    </div>
  </form>
</article>
<?php page_end();
