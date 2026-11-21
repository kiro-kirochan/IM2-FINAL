<?php
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/product_form.php';

$code = $_GET['code'] ?? $_POST['product_code'] ?? '';
$p = q_one('SELECT * FROM products WHERE product_code = ?', [$code]);
if (!$p || $p['is_deleted']) { flash('error', 'Product not found.'); redirect('products/'); }

$v = $p;
$err = [];
if (is_post()) {
    $v = ['product_code' => $p['product_code'], 'product_name' => post('product_name'), 'container_type' => post('container_type'),
          'service_type' => post('service_type'), 'current_unit_price' => post('current_unit_price')];
    [$clean, $err] = product_validate($v, false);
    if (!$err) {
        try {
            q('UPDATE products SET product_name = ?, container_type = ?, service_type = ?, current_unit_price = ? WHERE product_code = ?',
              [$clean['product_name'], $clean['container_type'], $clean['service_type'], $clean['current_unit_price'], $p['product_code']]);
            flash('ok', 'Product updated. Existing orders keep their old price.');
            redirect('products/');
        } catch (PDOException $e) { flash('error', db_message($e)); }
    }
}
page_start('Edit Product', 'products', ['sub' => $p['product_name'], 'actions' => head_link('products/', '← All products', 'btn')]); ?>
<section class="card" style="max-width:640px"><form method="post" novalidate>
  <?= csrf_field() ?><?php render_product_fields($v, $err, false); ?>
  <div class="form-actions"><button class="btn btn-primary">Save changes</button><a class="btn" href="<?= e(url('products/')) ?>">Cancel</a></div>
</form></section>
<?php page_end();
