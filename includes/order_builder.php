<?php
// Shared pieces of the Add / Edit order screens: the item builder box and the
// JavaScript data it needs.

function builder_data(array $customers, array $lines, string $type, float $fee = 0): array
{
    $products = [];
    foreach (q_all('SELECT product_code, product_name, current_unit_price FROM products') as $p) {
        $products[$p['product_code']] = ['name' => $p['product_name'], 'price' => (float)$p['current_unit_price']];
    }
    // On an existing order keep the price that was charged at the time.
    foreach ($lines as $l) {
        if (isset($l['price'], $products[$l['code']])) $products[$l['code']]['price'] = (float)$l['price'];
    }
    $custs = [];
    foreach ($customers as $c) {
        $custs[$c['customer_id']] = [
            'name'  => $c['full_name'],
            'fee'   => $c['delivery_fee'] !== null ? (float)$c['delivery_fee'] : 0,
            'ready' => !empty($c['contact_number']) && !empty($c['street']) && !empty($c['zone_id']),
        ];
    }
    return ['products' => $products, 'customers' => $custs, 'type' => $type, 'fee' => $fee,
            'lines' => array_map(fn($l) => ['code' => $l['code'], 'qty' => (int)$l['qty']], $lines)];
}

function render_item_builder(array $activeProducts): void
{ ?>
  <div class="lbl" style="margin-bottom:6px">Order items</div>
  <div class="adder">
    <select id="product" aria-label="Product">
      <?php foreach ($activeProducts as $p): ?>
        <option value="<?= e($p['product_code']) ?>"><?= e($p['product_name']) ?> — <?= peso($p['current_unit_price']) ?></option>
      <?php endforeach; ?>
    </select>
    <input id="addQty" type="number" min="1" max="999" value="1" aria-label="Quantity">
    <button type="button" id="addItem" class="btn btn-aqua">+ Add</button>
  </div>
  <ul class="lines" id="lines"></ul>
  <div class="totals">
    <div><span>Items subtotal</span><span id="subtotalText">₱0.00</span></div>
    <div id="feeRow" hidden><span>Delivery fee</span><span id="feeText">₱0.00</span></div>
    <div class="grand"><span>Order total</span><span id="grandText">₱0.00</span></div>
  </div>
<?php }
