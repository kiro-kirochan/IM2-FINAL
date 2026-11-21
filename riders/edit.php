<?php
require_once __DIR__ . '/../includes/layout.php';
$id = (int)($_GET['rider_id'] ?? $_POST['rider_id'] ?? 0);
$r = q_one('SELECT * FROM riders WHERE rider_id = ?', [$id]);
if (!$r || $r['is_deleted']) { flash('error', 'Rider not found.'); redirect('riders/'); }
$v = $r;
$err = [];
if (is_post()) {
    $v = ['rider_name' => post('rider_name'), 'vehicle' => post('vehicle')];
    if ($v['rider_name'] === '') $err['rider_name'] = 'Name is required.';
    if ($v['vehicle'] === '') $err['vehicle'] = 'Vehicle is required.';
    if (!$err) {
        q('UPDATE riders SET rider_name = ?, vehicle = ? WHERE rider_id = ?', [$v['rider_name'], $v['vehicle'], $id]);
        flash('ok', 'Rider updated.');
        redirect('riders/');
    }
}
page_start('Edit Rider', 'riders', ['sub' => $r['rider_name'], 'actions' => head_link('riders/', '← All riders', 'btn')]); ?>
<section class="card" style="max-width:520px"><form method="post" novalidate>
  <?= csrf_field() ?><input type="hidden" name="rider_id" value="<?= $id ?>">
  <label class="f"><span class="req">Rider name</span><input name="rider_name" maxlength="100" value="<?= e($v['rider_name']) ?>" autofocus><?php if (isset($err['rider_name'])): ?><span class="err"><?= e($err['rider_name']) ?></span><?php endif; ?></label>
  <label class="f"><span class="req">Vehicle</span><input name="vehicle" maxlength="50" value="<?= e($v['vehicle']) ?>"><?php if (isset($err['vehicle'])): ?><span class="err"><?= e($err['vehicle']) ?></span><?php endif; ?></label>
  <div class="form-actions"><button class="btn btn-primary">Save changes</button><a class="btn" href="<?= e(url('riders/')) ?>">Cancel</a></div>
</form></section>
<?php page_end();
