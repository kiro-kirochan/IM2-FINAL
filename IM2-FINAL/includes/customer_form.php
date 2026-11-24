<?php
// Fields + validation shared by customers/add.php and customers/edit.php

function customer_input(): array
{
    return ['full_name' => post('full_name'), 'contact_number' => post('contact_number'),
            'street' => post('street'), 'zone_id' => post('zone_id')];
}

// returns [cleanValues, errors]
function customer_validate(array $in): array
{
    $err = [];
    if ($in['full_name'] === '') $err['full_name'] = 'Name is required.';
    $phoneErr = null;
    $phone = clean_phone($in['contact_number'], $phoneErr);
    if ($phoneErr) $err['contact_number'] = $phoneErr;
    $zone = $in['zone_id'] !== '' ? (int)$in['zone_id'] : null;
    if ($zone !== null && !q_val('SELECT 1 FROM delivery_zones WHERE zone_id = ?', [$zone])) $err['zone_id'] = 'Unknown barangay.';
    return [[
        'full_name' => $in['full_name'], 'contact_number' => $phone,
        'street' => $in['street'] !== '' ? $in['street'] : null, 'zone_id' => $zone,
    ], $err];
}

function render_customer_fields(array $v, array $err, array $zones): void
{ ?>
  <label>
    <span class="req">Full name</span>
    <input name="full_name" maxlength="100" value="<?= e($v['full_name'] ?? '') ?>" <?= isset($err['full_name']) ? 'aria-invalid="true"' : '' ?> required autofocus>
    <?php if (isset($err['full_name'])): ?><small class="field-error"><?= e($err['full_name']) ?></small><?php endif; ?>
  </label>

  <label>
    <span>Contact number <small class="muted">(required for delivery)</small></span>
    <input name="contact_number" maxlength="20" placeholder="09XX XXX XXXX" value="<?= e($v['contact_number'] ?? '') ?>" <?= isset($err['contact_number']) ? 'aria-invalid="true"' : '' ?>>
    <?php if (isset($err['contact_number'])): ?><small class="field-error"><?= e($err['contact_number']) ?></small><?php endif; ?>
  </label>

  <div class="grid">
    <label>
      <span>House no. / Street / Purok <small class="muted">(for delivery)</small></span>
      <input name="street" maxlength="100" placeholder="Purok 3, Rizal St." value="<?= e($v['street'] ?? '') ?>">
    </label>
    <label>
      <span>Barangay <small class="muted">(sets delivery fee)</small></span>
      <select name="zone_id" <?= isset($err['zone_id']) ? 'aria-invalid="true"' : '' ?>>
        <option value="">— None (walk-in only) —</option>
        <?php foreach ($zones as $z): ?>
          <option value="<?= (int)$z['zone_id'] ?>" <?= (string)($v['zone_id'] ?? '') === (string)$z['zone_id'] ? 'selected' : '' ?>>
            <?= e($z['barangay']) ?> (+<?= peso($z['delivery_fee']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($err['zone_id'])): ?><small class="field-error"><?= e($err['zone_id']) ?></small><?php endif; ?>
    </label>
  </div>
<?php }
