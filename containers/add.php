<?php
require_once __DIR__ . '/../includes/layout.php';
$customers = array_values(array_filter(active_customers(), fn($c) => !$c['is_system']));
$v = ['customer_id' => '', 'container_type' => 'Slim', 'qty' => 1, 'notes' => ''];
$err = null;
if (is_post()) {
    $v = ['customer_id' => (int)post('customer_id'), 'container_type' => post('container_type'), 'qty' => (int)post('qty'), 'notes' => post('notes')];
    if (!in_array($v['customer_id'], array_column($customers, 'customer_id'), true)) $err = 'Choose a registered customer (we need to know who holds the container).';
    elseif (!in_array($v['container_type'], CONTAINER_TYPES, true)) $err = 'Choose a container type.';
    elseif ($v['qty'] < 1 || $v['qty'] > 99) $err = 'Quantity must be between 1 and 99.';
    else {
        try {
            q('INSERT INTO container_loans (customer_id, container_type, qty, notes) VALUES (?, ?, ?, ?)',
              [$v['customer_id'], $v['container_type'], $v['qty'], $v['notes'] !== '' ? $v['notes'] : null]);
            flash('ok', 'Loan recorded.');
            redirect('containers/');
        } catch (PDOException $e) { $err = db_message($e); }
    }
}
page_start('Lend Containers', 'containers', [
    'actions' => head_link('containers/', '← Containers', 'secondary outline btn-sm'),
]); ?>
<?php if ($err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endif; ?>

<article class="card-narrow">
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <label>
      <span class="req">Customer</span>
      <select name="customer_id" required>
        <option value="">Choose customer…</option>
        <?php foreach ($customers as $c): ?>
          <option value="<?= (int)$c['customer_id'] ?>" <?= (int)$v['customer_id'] === (int)$c['customer_id'] ? 'selected' : '' ?>><?= e($c['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="grid">
      <label>
        <span class="req">Container type</span>
        <select name="container_type">
          <?php foreach (CONTAINER_TYPES as $t): ?>
            <option <?= $v['container_type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        <span class="req">Quantity</span>
        <input type="number" name="qty" min="1" max="99" value="<?= (int)$v['qty'] ?>">
      </label>
    </div>
    <label>
      <span>Notes</span>
      <input name="notes" maxlength="255" value="<?= e($v['notes']) ?>">
    </label>
    <div class="form-actions">
      <button type="submit">Record loan</button>
      <a role="button" class="secondary outline" href="<?= e(url('containers/')) ?>">Cancel</a>
    </div>
  </form>
</article>
<?php page_end();
