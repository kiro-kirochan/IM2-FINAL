<?php
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/customer_form.php';

$id = (int)($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0);
$c = q_one('SELECT * FROM customers WHERE customer_id = ?', [$id]);
if (!$c || $c['is_deleted']) { flash('error', 'Customer not found.'); redirect('customers/'); }
if ($c['is_system']) { flash('error', 'The built-in Walk-in Customer cannot be edited.'); redirect('customers/'); }

$v = $c;
$err = [];
if (is_post()) {
    $v = customer_input();
    [$clean, $err] = customer_validate($v);
    if (!$err) {
        try {
            q('UPDATE customers SET full_name = :full_name, contact_number = :contact_number, street = :street, zone_id = :zone_id WHERE customer_id = :id',
              $clean + ['id' => $id]);
            flash('ok', 'Customer updated.');
            redirect('customers/view.php?customer_id=' . $id);
        } catch (PDOException $e) { flash('error', db_message($e)); }
    }
}

page_start('Edit Customer', 'customers', ['sub' => $c['full_name'], 'actions' => head_link('customers/', '← All customers', 'btn')]); ?>
<section class="card" style="max-width:640px">
  <form method="post" novalidate>
    <?= csrf_field() ?><input type="hidden" name="customer_id" value="<?= $id ?>">
    <?php render_customer_fields($v, $err, zones()); ?>
    <p class="note-box">Changing the barangay only affects <b>new</b> delivery orders — old orders keep the fee they were charged.</p>
    <div class="form-actions"><button class="btn btn-primary">Save changes</button>
      <a class="btn" href="<?= e(url('customers/')) ?>">Cancel</a></div>
  </form>
</section>
<?php page_end();
