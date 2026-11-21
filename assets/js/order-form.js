// Order builder used by orders/add.php and orders/edit.php.
// window.CD = { products:{code:{name,price}}, customers:{id:{fee,ready,name}}, lines:[{code,qty}], type }
document.addEventListener('DOMContentLoaded', () => {
  const CD = window.CD;
  if (!CD) return;
  const $ = (s) => document.querySelector(s);
  const peso = (n) => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  const lines = new Map((CD.lines || []).map((l) => [l.code, Number(l.qty)]));
  const list = $('#lines');
  const form = $('#orderForm');

  const typeInputs = document.querySelectorAll('input[name=order_type]');
  const currentType = () => {
    const picked = document.querySelector('input[name=order_type]:checked');
    return picked ? picked.value : CD.type;
  };
  const customerSel = $('#customer_id');

  function subtotal() {
    let s = 0;
    lines.forEach((qty, code) => (s += qty * CD.products[code].price));
    return s;
  }
  function fee() {
    if (currentType() !== 'Delivery' || !customerSel) return CD.type === 'Delivery' && CD.fee ? CD.fee : 0;
    const c = CD.customers[customerSel.value];
    return c && c.fee ? Number(c.fee) : 0;
  }

  function render() {
    list.replaceChildren();
    form.querySelectorAll('input.item-hidden').forEach((n) => n.remove());

    lines.forEach((qty, code) => {
      const p = CD.products[code];
      const li = document.createElement('li');
      li.className = 'line';
      li.innerHTML = `<div><b></b><small class="muted"></small></div>
        <div class="ctl">
          <button type="button" class="step" data-act="minus" aria-label="less">−</button>
          <button type="button" class="step" data-act="plus" aria-label="more">+</button>
          <span class="amt"></span>
          <button type="button" class="btn btn-sm btn-ghost" data-act="rm" title="Remove">✕</button>
        </div>`;
      li.querySelector('b').textContent = p.name;
      li.querySelector('small').textContent = `${qty} × ${peso(p.price)}`;
      li.querySelector('.amt').textContent = peso(qty * p.price);
      li.querySelector('[data-act=minus]').onclick = () => { if (qty > 1) lines.set(code, qty - 1); else if (lines.size > 1) lines.delete(code); render(); };
      li.querySelector('[data-act=plus]').onclick = () => { lines.set(code, Math.min(999, qty + 1)); render(); };
      li.querySelector('[data-act=rm]').onclick = () => { if (lines.size > 1) { lines.delete(code); render(); } };
      list.append(li);

      const h = document.createElement('input');
      h.type = 'hidden'; h.className = 'item-hidden'; h.name = `items[${code}]`; h.value = qty;
      form.append(h);
    });
    totals();
  }

  function totals() {
    const sub = subtotal();
    const f = fee();
    $('#subtotalText').textContent = peso(sub);
    $('#feeRow').hidden = currentType() !== 'Delivery';
    $('#feeText').textContent = peso(f);
    $('#grandText').textContent = peso(sub + f);

    // delivery readiness warning
    const warn = $('#deliveryWarn');
    if (warn && customerSel) {
      const c = CD.customers[customerSel.value];
      warn.hidden = !(currentType() === 'Delivery' && c && !c.ready);
    }
    // payment box (add page only)
    const opt = $('#payOption');
    if (opt) {
      $('#partialBox').hidden = opt.value !== 'partial';
      $('#modeBox').hidden = opt.value === 'unpaid';
      const amt = parseFloat(($('#amount') || {}).value) || 0;
      const paid = opt.value === 'full' ? sub + f : opt.value === 'partial' ? Math.min(amt, sub + f) : 0;
      $('#balanceText').textContent = peso(Math.max(sub + f - paid, 0));
    }
  }

  $('#addItem').onclick = () => {
    const code = $('#product').value;
    const qty = Math.max(1, Math.min(999, parseInt($('#addQty').value, 10) || 1));
    lines.set(code, Math.min(999, (lines.get(code) || 0) + qty));
    $('#addQty').value = 1;
    render();
  };

  typeInputs.forEach((r) => r.addEventListener('change', totals));
  if (customerSel) customerSel.addEventListener('change', totals);
  ['#payOption', '#amount'].forEach((s) => { const n = $(s); if (n) { n.addEventListener('input', totals); n.addEventListener('change', totals); } });

  form.addEventListener('submit', (ev) => {
    if (lines.size === 0) { ev.preventDefault(); alert('Add at least one item.'); }
  });

  if (lines.size === 0 && $('#product')) lines.set($('#product').value, 1);
  render();
});
