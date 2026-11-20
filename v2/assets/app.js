/* BanHang Premium progressive enhancements.
   Server owns all prices, stock, permissions and state changes. */
(() => {
  'use strict';

  const toggleButtons = document.querySelectorAll('[data-menu-toggle]');
  for (const button of toggleButtons) {
    button.addEventListener('click', () => {
      const customerNav = document.getElementById('mobile-nav');
      const adminNav = document.getElementById('admin-sidebar');
      if (customerNav) customerNav.classList.toggle('open');
      if (adminNav) adminNav.classList.toggle('open');
      button.setAttribute('aria-expanded', String(
        Boolean(customerNav?.classList.contains('open') || adminNav?.classList.contains('open'))
      ));
    });
  }

  // Destructive server actions require a second deliberate interaction.
  document.querySelectorAll('[data-confirm]').forEach(button => {
    button.addEventListener('click', event => {
      const message = button.getAttribute('data-confirm');
      if (message && !window.confirm(message)) event.preventDefault();
    });
  });

  // Quantity inputs are only a UX convenience. PHP revalidates them against stock.
  document.querySelectorAll('input[type="number"][min][max]').forEach(input => {
    input.addEventListener('change', () => {
      const num = Number.parseInt(input.value, 10);
      const min = Number.parseInt(input.min, 10);
      const max = Number.parseInt(input.max, 10);
      if (!Number.isFinite(num)) return;
      if (Number.isFinite(min) && num < min) input.value = String(min);
      if (Number.isFinite(max) && num > max) input.value = String(max);
    });
  });

  // Prevent accidental duplicate submissions. Does not replace server-side idempotency.
  document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', event => {
      if (event.defaultPrevented) return;
      if (form.dataset.submitting === 'true') {
        event.preventDefault();
        return;
      }
      if (typeof form.checkValidity === 'function' && !form.checkValidity()) return;
      form.dataset.submitting = 'true';
    });
  });

  // Keyboard shortcut: press "/" to search when not editing a form field.
  document.addEventListener('keydown', event => {
    if (event.key !== '/' || event.ctrlKey || event.altKey || event.metaKey) return;
    const tag = document.activeElement?.tagName?.toLowerCase();
    if (['input', 'textarea', 'select'].includes(tag)) return;
    const input = document.querySelector('.search input[name="q"]');
    if (input) {
      event.preventDefault();
      input.focus();
    }
  });
})();
