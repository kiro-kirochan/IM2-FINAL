// Small helpers shared by every page (no libraries needed).
document.addEventListener('DOMContentLoaded', () => {
  // 1. <form data-confirm="Are you sure?"> asks before submitting
  document.querySelectorAll('form[data-confirm]').forEach((f) => {
    f.addEventListener('submit', (ev) => {
      if (!confirm(f.dataset.confirm)) ev.preventDefault();
    });
  });

  // 2. <input data-filter="#tableId"> live-filters table rows as you type
  document.querySelectorAll('input[data-filter]').forEach((input) => {
    const rows = () => document.querySelectorAll(input.dataset.filter + ' tbody tr[data-search]');
    input.addEventListener('input', () => {
      const needle = input.value.trim().toLowerCase();
      rows().forEach((tr) => {
        tr.hidden = needle !== '' && !tr.dataset.search.includes(needle);
      });
    });
  });

  // 3. <button data-print> prints the page
  document.querySelectorAll('[data-print]').forEach((b) => b.addEventListener('click', () => window.print()));

  // 4. <button data-copy="#id"> copies the text of an element
  document.querySelectorAll('[data-copy]').forEach((b) => {
    b.addEventListener('click', async () => {
      const src = document.querySelector(b.dataset.copy);
      if (!src) return;
      try {
        await navigator.clipboard.writeText(src.value || src.innerText);
        const old = b.textContent;
        b.textContent = 'Copied!';
        setTimeout(() => (b.textContent = old), 1200);
      } catch {
        alert('Clipboard blocked by the browser.');
      }
    });
  });

  // 5. Auto-hide success messages
  document.querySelectorAll('.flash-ok').forEach((f) => setTimeout(() => (f.style.display = 'none'), 6000));
});
