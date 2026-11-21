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

  // Prevent accidental duplicate form submissions (reset if restored from browser BFCache)
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
  window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting]').forEach(form => {
      delete form.dataset.submitting;
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

  // Order detail near-real-time status & timeline live DOM updater (every 20s while visible & not final)
  const orderWatch = document.querySelector('[data-order-watch]');
  if (orderWatch && apiUrl && orderWatch.getAttribute('data-order-final') !== '1') {
    const orderCode = orderWatch.getAttribute('data-order-watch') || '';
    let currentSig = orderWatch.getAttribute('data-order-signature') || '';
    const liveBanner = document.getElementById('order-live-banner');
    let orderTimer = null;

    const applyOrderUpdate = data => {
      // 1. Order status pill & updated_at
      const statusPill = orderWatch.querySelector('[data-order-status-pill]');
      if (statusPill) {
        statusPill.textContent = data.order_status_label || '';
        statusPill.className = `status-pill status-pill--${data.order_status_tone || 'info'}`;
      }
      const updatedEl = orderWatch.querySelector('[data-order-updated-at]');
      if (updatedEl && data.updated_at) updatedEl.textContent = data.updated_at;

      // 2. Top terminal status banner (COMPLETED / CANCELLED)
      const bannerSlot = orderWatch.querySelector('[data-order-banner-slot]');
      if (bannerSlot) {
        bannerSlot.textContent = '';
        if (data.order_status === 'COMPLETED') {
          const box = document.createElement('div');
          box.className = 'order-banner order-banner--success';
          const st = document.createElement('strong');
          st.textContent = 'Giao hàng thành công!';
          const p = document.createElement('p');
          p.textContent = `Đơn hàng ${data.order_code} đã được giao thành công${data.delivered_at ? ' lúc ' + data.delivered_at : ''}. Cảm ơn bạn đã tin tưởng BanHang!`;
          box.append(st, p);
          bannerSlot.appendChild(box);
        } else if (data.order_status === 'CANCELLED') {
          const box = document.createElement('div');
          box.className = 'order-banner order-banner--danger';
          const st = document.createElement('strong');
          st.textContent = 'Đơn hàng đã bị hủy';
          const p1 = document.createElement('p');
          p1.textContent = `Lý do: ${data.cancel_reason || 'Đơn hàng đã bị hủy theo quy trình xử lý.'}`;
          const p2 = document.createElement('p');
          p2.textContent = `Tình trạng thanh toán: ${data.payment_label || ''}.`;
          box.append(st, p1, p2);
          bannerSlot.appendChild(box);
        }
      }

      // 3. Re-render accurate timeline steps from server
      const timelineEl = orderWatch.querySelector('[data-order-timeline]');
      if (timelineEl && Array.isArray(data.timeline)) {
        timelineEl.textContent = '';
        for (const step of data.timeline) {
          const div = document.createElement('div');
          const safeState = ['done', 'current', 'upcoming', 'cancelled'].includes(step.state) ? step.state : 'upcoming';
          div.className = `timeline-step is-${safeState}`;
          const dot = document.createElement('span');
          dot.className = 'timeline-dot';
          const strong = document.createElement('strong');
          strong.textContent = step.label || '';
          const sm = document.createElement('small');
          sm.textContent = step.time || '';
          div.append(dot, strong, sm);
          timelineEl.appendChild(div);
        }
      }

      // 4. Shipping / tracking block (real data only)
      const shippingSlot = orderWatch.querySelector('[data-order-shipping-slot]');
      if (shippingSlot) {
        shippingSlot.textContent = '';
        if (data.shipping_carrier || data.tracking_code || data.shipped_at) {
          const box = document.createElement('div');
          box.className = 'shipping-box';
          const h3 = document.createElement('h3');
          h3.textContent = 'Thông tin vận chuyển';
          box.appendChild(h3);
          if (data.shipping_carrier) {
            const p = document.createElement('p');
            p.append('Đơn vị vận chuyển: ');
            const strong = document.createElement('strong');
            strong.textContent = data.shipping_carrier;
            p.appendChild(strong);
            box.appendChild(p);
          }
          if (data.tracking_code) {
            const p = document.createElement('p');
            p.append('Mã vận đơn: ');
            const code = document.createElement('code');
            code.textContent = data.tracking_code;
            p.appendChild(code);
            box.appendChild(p);
          }
          if (data.shipped_at) {
            const p = document.createElement('p');
            p.textContent = `Thời gian bàn giao vận chuyển: ${data.shipped_at}`;
            box.appendChild(p);
          }
          if (data.delivered_at) {
            const p = document.createElement('p');
            p.textContent = `Thời gian giao thành công: ${data.delivered_at}`;
            box.appendChild(p);
          }
          shippingSlot.appendChild(box);
        }
      }

      // 5. Customer-visible note block (never admin_note)
      const noteSlot = orderWatch.querySelector('[data-order-customer-note-slot]');
      if (noteSlot) {
        noteSlot.textContent = '';
        if (data.customer_note) {
          const box = document.createElement('div');
          box.className = 'payment-status-note';
          const st = document.createElement('strong');
          st.textContent = 'Ghi chú từ cửa hàng';
          const p = document.createElement('p');
          p.textContent = data.customer_note;
          box.append(st, p);
          noteSlot.appendChild(box);
        }
      }

      // 6. Public history list
      const historyEl = orderWatch.querySelector('[data-order-history-list]');
      if (historyEl && Array.isArray(data.history) && data.history.length > 0) {
        historyEl.textContent = '';
        for (const ev of data.history) {
          const item = document.createElement('div');
          item.className = 'history-item';
          const top = document.createElement('div');
          const st = document.createElement('strong');
          st.textContent = ev.label || '';
          const sp = document.createElement('span');
          sp.textContent = ev.created_at || '';
          top.append(st, sp);
          item.appendChild(top);
          if (ev.note) {
            const p = document.createElement('p');
            p.textContent = ev.note;
            item.appendChild(p);
          }
          historyEl.appendChild(item);
        }
      }

      // 7. Payment pill, review status & live PAID / CANCELLED payment card update
      const payPill = orderWatch.querySelector('[data-order-payment-pill]');
      if (payPill) {
        payPill.textContent = data.payment_label || '';
        payPill.className = `status-pill status-pill--${data.payment_tone || 'neutral'}`;
      }
      const payRev = orderWatch.querySelector('[data-order-payment-review]');
      if (payRev && data.payment_review_label) payRev.textContent = data.payment_review_label;

      const payBody = orderWatch.querySelector('[data-order-payment-body]');
      if (payBody && data.payment_status === 'PAID') {
        payBody.textContent = '';
        const box = document.createElement('div');
        box.className = 'order-banner order-banner--success';
        const st = document.createElement('strong');
        const p = document.createElement('p');
        if (data.payment_method === 'BANK') {
          st.textContent = 'Thanh toán thành công!';
          p.textContent = `BanHang đã đối soát sao kê MB Bank và xác nhận nhận đủ ${data.total_amount_formatted || ''} cho đơn hàng ${data.order_code}.`;
        } else {
          st.textContent = 'Đã xác nhận thu tiền COD';
          p.textContent = 'Cửa hàng đã xác nhận nhận đủ tiền thanh toán cho đơn hàng này.';
        }
        box.append(st, p);
        const verifiedAt = data.payment_method === 'BANK' ? data.payment_verified_at : data.cod_paid_at;
        if (verifiedAt) {
          const sm = document.createElement('small');
          sm.textContent = `Xác nhận lúc: ${verifiedAt}`;
          box.appendChild(sm);
        }
        payBody.appendChild(box);
      } else if (payBody && data.order_status === 'CANCELLED') {
        payBody.textContent = '';
      }
    };

    const stopOrderPoll = () => {
      if (orderTimer !== null) {
        window.clearInterval(orderTimer);
        orderTimer = null;
      }
    };

    const checkOrder = async () => {
      if (document.hidden || !orderCode) return;
      try {
        const res = await fetch(`${apiUrl}?action=order_status&code=${encodeURIComponent(orderCode)}`, { credentials: 'same-origin' });
        if (!res.ok) return;
        const data = await res.json();
        if (!data || !data.ok) return;
        const nextSig = data.signature || `${data.order_status}|${data.payment_status}|${data.payment_review_status}|${data.updated_at}`;
        if (nextSig !== currentSig) {
          currentSig = nextSig;
          applyOrderUpdate(data);
          if (liveBanner) {
            liveBanner.hidden = false;
            liveBanner.textContent = `Đã tự động cập nhật tiến độ đơn ${orderCode}: ${data.payment_label} · ${data.order_status_label}.`;
          }
          showToast('Đơn hàng có cập nhật mới', `${data.payment_label} · ${data.order_status_label}`, window.location.href);
          if (data.is_final) {
            orderWatch.setAttribute('data-order-final', '1');
            stopOrderPoll();
          }
        }
      } catch (_) {}
    };

    const startOrderPoll = () => {
      if (orderTimer !== null || orderWatch.getAttribute('data-order-final') === '1') return;
      orderTimer = window.setInterval(checkOrder, 20000);
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
