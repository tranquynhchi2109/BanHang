/* BanHang progressive enhancements & near-real-time notification/order polling.
   Server owns all prices, stock, permissions and state changes. */
(() => {
  'use strict';

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const apiUrl = document.querySelector('meta[name="api-url"]')?.getAttribute('content') || '';

  // Mobile menu toggles
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

  // CSP-safe auto-submit for catalog sort dropdowns
  document.querySelectorAll('[data-auto-submit]').forEach(el => {
    el.addEventListener('change', () => {
      if (el.form) el.form.submit();
    });
  });

  // Image fallback when a manufacturer CDN is unavailable
  document.addEventListener('error', event => {
    const img = event.target;
    if (!(img instanceof HTMLImageElement)) return;
    const backup = img.dataset.imageFallback;
    if (!backup || img.dataset.backupUsed === '1') return;
    img.dataset.backupUsed = '1';
    img.src = backup;
    img.alt = 'Ảnh minh họa cho danh mục sản phẩm';
  }, true);

  // Destructive server actions require confirmation
  document.querySelectorAll('[data-confirm]').forEach(button => {
    button.addEventListener('click', event => {
      const message = button.getAttribute('data-confirm');
      if (message && !window.confirm(message)) event.preventDefault();
    });
  });

  // Quantity clamping UX (PHP revalidates against stock)
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

  // Prevent accidental duplicate form submissions
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

  // Keyboard shortcut: press "/" to focus search
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

  function showToast(title, message, href) {
    const region = document.getElementById('toast-region');
    if (!region) return;
    const card = document.createElement('div');
    card.className = 'toast-card';
    const strong = document.createElement('strong');
    strong.textContent = title;
    const p = document.createElement('p');
    p.textContent = message;
    card.appendChild(strong);
    card.appendChild(p);
    if (href) {
      const a = document.createElement('a');
      a.className = 'link-blue';
      a.href = href;
      a.textContent = 'Xem chi tiết đơn hàng →';
      card.appendChild(a);
    }
    region.appendChild(card);
    window.setTimeout(() => {
      card.classList.add('is-fading');
      window.setTimeout(() => card.remove(), 350);
    }, 9000);
  }

  // Notification Bell Dropdown + Near-real-time Polling (every 20s, pauses when tab hidden)
  const notifWrap = document.querySelector('[data-notif-wrap]');
  if (notifWrap && apiUrl) {
    const bellBtn = notifWrap.querySelector('[data-notif-toggle]');
    const dropdown = document.getElementById('notif-dropdown');
    const badge = notifWrap.querySelector('[data-notif-badge]');
    const listEl = notifWrap.querySelector('[data-notif-list]');
    const markAllBtn = notifWrap.querySelector('[data-notif-mark-all]');

    let latestNotifId = 0;
    listEl?.querySelectorAll('[data-notif-id]').forEach(el => {
      const id = Number.parseInt(el.getAttribute('data-notif-id') || '0', 10);
      if (id > latestNotifId) latestNotifId = id;
    });

    const updateBadge = count => {
      if (!badge) return;
      badge.textContent = count > 99 ? '99+' : String(count);
      badge.classList.toggle('is-hidden', count <= 0);
    };

    const renderNotifList = items => {
      if (!listEl) return;
      listEl.textContent = '';
      if (!items.length) {
        const empty = document.createElement('p');
        empty.className = 'notif-empty';
        empty.textContent = 'Chưa có thông báo nào.';
        listEl.appendChild(empty);
        return;
      }
      for (const n of items) {
        const a = document.createElement('a');
        a.className = 'notif-item' + (n.is_read ? '' : ' is-unread');
        a.href = n.link_url || 'index.php?page=notifications';
        a.setAttribute('data-notif-id', String(n.id));
        const st = document.createElement('strong');
        st.textContent = n.title;
        const p = document.createElement('p');
        p.textContent = n.message;
        const sm = document.createElement('small');
        sm.textContent = n.created_at;
        a.append(st, p, sm);
        listEl.appendChild(a);
      }
    };

    const markRead = async payload => {
      if (!csrfToken) return;
      const body = new URLSearchParams({ action: 'mark_read', _csrf: csrfToken, ...payload });
      const res = await fetch(apiUrl, { method: 'POST', credentials: 'same-origin', body });
      if (!res.ok) return;
      const data = await res.json();
      if (data && data.ok) updateBadge(Number(data.unread_count || 0));
    };

    bellBtn?.addEventListener('click', event => {
      event.stopPropagation();
      if (!dropdown) return;
      const opening = dropdown.hidden;
      dropdown.hidden = !opening;
      bellBtn.setAttribute('aria-expanded', String(opening));
    });

    document.addEventListener('click', event => {
      if (dropdown && !dropdown.hidden && !notifWrap.contains(event.target)) {
        dropdown.hidden = true;
        bellBtn?.setAttribute('aria-expanded', 'false');
      }
    });

    markAllBtn?.addEventListener('click', async () => {
      await markRead({ all: '1' });
      listEl?.querySelectorAll('.notif-item.is-unread').forEach(el => el.classList.remove('is-unread'));
    });

    document.addEventListener('click', event => {
      const link = event.target instanceof Element ? event.target.closest('a[data-notif-id]') : null;
      if (!link) return;
      const nid = link.getAttribute('data-notif-id');
      if (nid) {
        const body = new URLSearchParams({ action: 'mark_read', _csrf: csrfToken, id: nid });
        if (navigator.sendBeacon) {
          navigator.sendBeacon(apiUrl, body);
        } else {
          fetch(apiUrl, { method: 'POST', credentials: 'same-origin', body, keepalive: true }).catch(() => {});
        }
      }
    });

    let pollTimer = null;
    const pollNotifications = async () => {
      if (document.hidden) return;
      try {
        const res = await fetch(`${apiUrl}?action=notifications&limit=6`, { credentials: 'same-origin' });
        if (!res.ok) return;
        const data = await res.json();
        if (!data || !data.ok) return;
        updateBadge(Number(data.unread_count || 0));
        const items = Array.isArray(data.notifications) ? data.notifications : [];
        renderNotifList(items);
        if (items.length) {
          const newest = items[0];
          if (latestNotifId > 0 && newest.id > latestNotifId && !newest.is_read) {
            showToast(newest.title, newest.message, newest.link_url);
          }
          if (newest.id > latestNotifId) latestNotifId = newest.id;
        }
      } catch (_) {}
    };

    const startNotifPoll = () => {
      if (pollTimer !== null) return;
      pollTimer = window.setInterval(pollNotifications, 20000);
    };
    const stopNotifPoll = () => {
      if (pollTimer !== null) {
        window.clearInterval(pollTimer);
        pollTimer = null;
      }
    };
    startNotifPoll();
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) stopNotifPoll();
      else { pollNotifications(); startNotifPoll(); }
    });
  }

  // Order detail near-real-time status watcher (every 20s while visible & order not final)
  const orderWatch = document.querySelector('[data-order-watch]');
  if (orderWatch && apiUrl && orderWatch.getAttribute('data-order-final') !== '1') {
    const orderCode = orderWatch.getAttribute('data-order-watch') || '';
    let currentSig = orderWatch.getAttribute('data-order-signature') || '';
    const liveBanner = document.getElementById('order-live-banner');
    let orderTimer = null;

    const checkOrder = async () => {
      if (document.hidden || !orderCode) return;
      try {
        const res = await fetch(`${apiUrl}?action=order_status&code=${encodeURIComponent(orderCode)}`, { credentials: 'same-origin' });
        if (!res.ok) return;
        const data = await res.json();
        if (!data || !data.ok) return;
        const nextSig = `${data.order_status}|${data.payment_status}|${data.payment_review_status}|${data.updated_at}`;
        if (nextSig !== currentSig) {
          currentSig = nextSig;
          if (liveBanner) {
            liveBanner.hidden = false;
            liveBanner.textContent = '';
            const msg = document.createElement('span');
            msg.textContent = `Đơn hàng ${orderCode} vừa cập nhật: ${data.payment_label} · ${data.order_status_label}. `;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'button button--light';
            btn.textContent = 'Cập nhật giao diện ngay';
            btn.addEventListener('click', () => window.location.reload());
            liveBanner.append(msg, btn);
          }
          showToast('Đơn hàng có cập nhật mới', `${data.payment_label} · ${data.order_status_label}`, window.location.href);
        }
      } catch (_) {}
    };

    const startOrderPoll = () => {
      if (orderTimer !== null) return;
      orderTimer = window.setInterval(checkOrder, 20000);
    };
    const stopOrderPoll = () => {
      if (orderTimer !== null) {
        window.clearInterval(orderTimer);
        orderTimer = null;
      }
    };
    startOrderPoll();
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) stopOrderPoll();
      else { checkOrder(); startOrderPoll(); }
    });
  }

  // Admin topbar/sidebar live reconciliation counter (every 25s while visible)
  const adminShell = document.querySelector('[data-admin-shell]');
  if (adminShell && apiUrl) {
    let adminTimer = null;
    const pollAdmin = async () => {
      if (document.hidden) return;
      try {
        const res = await fetch(`${apiUrl}?action=admin_summary`, { credentials: 'same-origin' });
        if (!res.ok) return;
        const data = await res.json();
        if (!data || !data.ok) return;
        const rec = Number(data.pending_reconciliation || 0);
        const ord = Number(data.pending_orders || 0);
        const recBadge = document.querySelector('[data-admin-pending-rec]');
        const ordBadge = document.querySelector('[data-admin-pending-orders]');
        const alertPill = document.querySelector('[data-admin-alert-pill]');
        const alertText = document.querySelector('[data-admin-pending-rec-text]');
        if (recBadge) { recBadge.textContent = String(rec); recBadge.classList.toggle('is-hidden', rec <= 0); }
        if (ordBadge) { ordBadge.textContent = String(ord); ordBadge.classList.toggle('is-hidden', ord <= 0); }
        if (alertText) alertText.textContent = String(rec);
        if (alertPill) alertPill.classList.toggle('is-hidden', rec <= 0);
      } catch (_) {}
    };
    const startAdminPoll = () => {
      if (adminTimer !== null) return;
      adminTimer = window.setInterval(pollAdmin, 25000);
    };
    const stopAdminPoll = () => {
      if (adminTimer !== null) { window.clearInterval(adminTimer); adminTimer = null; }
    };
    startAdminPoll();
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) stopAdminPoll();
      else { pollAdmin(); startAdminPoll(); }
    });
  }
})();
