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
page_start('Add Rider', 'riders', ['actions' => head_link('riders/', '← All riders', 'btn')]); ?>
<section class="card" style="max-width:520px"><form method="post" novalidate>
  <?= csrf_field() ?>
  <label class="f"><span class="req">Rider name</span><input name="rider_name" maxlength="100" value="<?= e($v['rider_name']) ?>" autofocus><?php if (isset($err['rider_name'])): ?><span class="err"><?= e($err['rider_name']) ?></span><?php endif; ?></label>
  <label class="f"><span class="req">Vehicle</span><input name="vehicle" maxlength="50" list="vehicles" value="<?= e($v['vehicle']) ?>">
    <datalist id="vehicles"><option>Motorcycle</option><option>Tricycle</option><option>Bicycle</option><option>Multicab</option></datalist><?php if (isset($err['vehicle'])): ?><span class="err"><?= e($err['vehicle']) ?></span><?php endif; ?></label>
  <div class="form-actions"><button class="btn btn-primary">Save rider</button><a class="btn" href="<?= e(url('riders/')) ?>">Cancel</a></div>
</form></section>
<?php page_end();
