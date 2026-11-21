<?php
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/product_form.php';

$v = ['product_code' => '', 'product_name' => '', 'container_type' => 'Slim', 'service_type' => 'Refill Only', 'current_unit_price' => ''];
$err = [];
if (is_post()) {
    $v = [
        'product_code' => post('product_code'), 'product_name' => post('product_name'), 'container_type' => post('container_type'),
        'service_type' => post('service_type'), 'current_unit_price' => post('current_unit_price'),
    ];
    [$clean, $err] = product_validate($v, true);
    if (!$err) {
        try {
            q('INSERT INTO products (product_code, product_name, container_type, service_type, current_unit_price)
               VALUES (:product_code, :product_name, :container_type, :service_type, :current_unit_price)', $clean);
            flash('ok', $clean['product_name'] . ' was added.');
            redirect('products/');
        } catch (PDOException $e) { flash('error', db_message($e)); }
    }
}
page_start('Add Product', 'products', ['actions' => head_link('products/', '← All products', 'btn')]); ?>
<section class="card" style="max-width:640px"><form method="post" novalidate>
  <?= csrf_field() ?><?php render_product_fields($v, $err, true); ?>
  <div class="form-actions"><button class="btn btn-primary">Save product</button><a class="btn" href="<?= e(url('products/')) ?>">Cancel</a></div>
</form></section>
<?php page_end();
