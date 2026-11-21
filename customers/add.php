<?php
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/customer_form.php';

$v = ['full_name' => '', 'contact_number' => '', 'street' => '', 'zone_id' => ''];
$err = [];

if (is_post()) {
    $v = customer_input();
    [$clean, $err] = customer_validate($v);
    if (!$err) {
        try {
            q('INSERT INTO customers (full_name, contact_number, street, zone_id) VALUES (:full_name, :contact_number, :street, :zone_id)', $clean);
            flash('ok', $clean['full_name'] . ' was added.');
            redirect(post('next') === 'order' ? 'orders/add.php' : 'customers/');
        } catch (PDOException $e) { flash('error', db_message($e)); }
    }
}

page_start('Add Customer', 'customers', ['sub' => 'Name is enough for walk-ins. Delivery customers also need a contact number, street and barangay.',
    'actions' => head_link('customers/', '← All customers', 'btn')]); ?>
<section class="card" style="max-width:640px">
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <?php render_customer_fields($v, $err, zones()); ?>
    <div class="form-actions">
      <button class="btn btn-primary">Save customer</button>
      <button class="btn btn-aqua" name="next" value="order">Save &amp; start an order</button>
      <a class="btn" href="<?= e(url('customers/')) ?>">Cancel</a>
    </div>
  </form>
</section>
<?php page_end();
