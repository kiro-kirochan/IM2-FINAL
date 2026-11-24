<?php
require_once __DIR__ . '/../includes/layout.php';
$id = (int)($_GET['loan_id'] ?? $_POST['loan_id'] ?? 0);
$l = q_one('SELECT l.*, c.full_name FROM container_loans l JOIN customers c ON c.customer_id = l.customer_id WHERE l.loan_id = ?', [$id]);
if (!$l || $l['is_deleted']) { flash('error', 'Loan not found.'); redirect('containers/'); }
$out = $l['qty'] - $l['qty_returned'];
if ($out <= 0) { flash('info', 'All containers on this loan have already been returned.'); redirect('containers/'); }

$err = null;
$n = $out;
if (is_post()) {
    $n = (int)post('returned');
    if ($n < 1 || $n > $out) $err = "Enter a number from 1 to $out.";
    else {
        try {
            q('UPDATE container_loans SET qty_returned = qty_returned + ?, last_returned_at = NOW() WHERE loan_id = ?', [$n, $id]);
            flash('ok', "$n container(s) returned by {$l['full_name']}.");
            redirect('containers/');
        } catch (PDOException $e) { $err = db_message($e); }
    }
}
page_start('Return Containers', 'containers', [
    'sub' => $l['full_name'],
    'actions' => head_link('containers/', '← Containers', 'secondary outline btn-sm'),
]); ?>
<?php if ($err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endif; ?>

<article class="card-narrow">
  <header><h2>Loan summary</h2></header>
  <dl class="facts" style="margin-bottom:1rem">
    <dt>Container</dt><dd><?= e($l['container_type']) ?></dd>
    <dt>Lent</dt><dd><?= (int)$l['qty'] ?> on <?= e(fdate($l['date_lent'])) ?></dd>
    <dt>Still out</dt><dd><b><?= $out ?></b></dd>
  </dl>
  <hr>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="loan_id" value="<?= $id ?>">
    <label>
      <span class="req">Containers returned now</span>
      <input type="number" name="returned" min="1" max="<?= $out ?>" value="<?= $n ?>" autofocus>
    </label>
    <div class="form-actions">
      <button type="submit">Save return</button>
      <a role="button" class="secondary outline" href="<?= e(url('containers/')) ?>">Cancel</a>
    </div>
  </form>
</article>
<?php page_end();
