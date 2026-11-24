<?php
function product_validate(array $in, bool $isNew): array
{
    $err = [];
    $code = strtoupper(trim($in['product_code'] ?? ''));
    if ($isNew) {
        if (!preg_match('/^[A-Z0-9\-]{2,20}$/', $code)) $err['product_code'] = 'Use 2–20 letters, numbers or dashes (e.g. SLM-RF).';
        elseif (q_val('SELECT 1 FROM products WHERE product_code = ?', [$code])) $err['product_code'] = 'That code is already used.';
    }
    $name = trim($in['product_name'] ?? '');
    if ($name === '') $err['product_name'] = 'Name is required.';
    if (!in_array($in['container_type'] ?? '', CONTAINER_TYPES, true)) $err['container_type'] = 'Choose a container type.';
    if (!in_array($in['service_type'] ?? '', SERVICE_TYPES, true)) $err['service_type'] = 'Choose a service type.';
    $price = $in['current_unit_price'] ?? '';
    if (!is_numeric($price) || (float)$price < 0) $err['current_unit_price'] = 'Enter a price of 0 or more.';
    return [['product_code' => $code, 'product_name' => $name, 'container_type' => $in['container_type'] ?? '',
             'service_type' => $in['service_type'] ?? '', 'current_unit_price' => is_numeric($price) ? (float)$price : 0], $err];
}

function render_product_fields(array $v, array $err, bool $isNew): void
{ ?>
  <div class="grid">
    <label>
      <span class="req">Product code</span>
      <input name="product_code" maxlength="20" value="<?= e($v['product_code'] ?? '') ?>" <?= $isNew ? 'required autofocus' : 'readonly' ?> <?= isset($err['product_code']) ? 'aria-invalid="true"' : '' ?> style="text-transform:uppercase">
      <?php if (!$isNew): ?><small class="muted">The code cannot change because past orders reference it.</small><?php endif; ?>
      <?php if (isset($err['product_code'])): ?><small class="field-error"><?= e($err['product_code']) ?></small><?php endif; ?>
    </label>
    <label>
      <span class="req">Price per unit (₱)</span>
      <input type="number" step="0.01" min="0" name="current_unit_price" value="<?= e($v['current_unit_price'] ?? '') ?>" <?= isset($err['current_unit_price']) ? 'aria-invalid="true"' : '' ?> required>
      <?php if (isset($err['current_unit_price'])): ?><small class="field-error"><?= e($err['current_unit_price']) ?></small><?php endif; ?>
    </label>
  </div>

  <label>
    <span class="req">Product name</span>
    <input name="product_name" maxlength="100" value="<?= e($v['product_name'] ?? '') ?>" <?= isset($err['product_name']) ? 'aria-invalid="true"' : '' ?> required <?= $isNew ? '' : 'autofocus' ?>>
    <?php if (isset($err['product_name'])): ?><small class="field-error"><?= e($err['product_name']) ?></small><?php endif; ?>
  </label>

  <div class="grid">
    <label>
      <span class="req">Container</span>
      <select name="container_type">
        <?php foreach (CONTAINER_TYPES as $t): ?>
          <option <?= ($v['container_type'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      <span class="req">Service</span>
      <select name="service_type">
        <?php foreach (SERVICE_TYPES as $t): ?>
          <option <?= ($v['service_type'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
<?php }
