(function () {
  'use strict';

  const root = document.getElementById('support-workspace');
  if (!root) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const urls = {
    liveIndex: root.dataset.liveIndexUrl,
    unread: root.dataset.unreadUrl,
    firebaseToken: root.dataset.firebaseTokenUrl,
    reply: root.dataset.replyUrlTemplate,
    assign: root.dataset.assignUrlTemplate,
    close: root.dataset.closeUrlTemplate,
    reopen: root.dataset.reopenUrlTemplate,
    messages: root.dataset.messagesUrlTemplate,
  };
  const firebaseConfig = {
    apiKey: root.dataset.fbApiKey || '',
    authDomain: root.dataset.fbAuthDomain || '',
    projectId: root.dataset.fbProjectId || '',
    storageBucket: root.dataset.fbStorageBucket || '',
    messagingSenderId: root.dataset.fbSenderId || '',
    appId: root.dataset.fbAppId || '',
  };

  const els = {
    shell: document.getElementById('support-shell'),
    list: document.getElementById('support-list'),
    threadEmpty: document.getElementById('support-thread-empty'),
    threadPane: document.getElementById('support-thread-pane'),
    messages: document.getElementById('support-messages'),
    title: document.getElementById('support-thread-title'),
    avatar: document.getElementById('support-thread-avatar'),
    meta: document.getElementById('support-thread-meta'),
    status: document.getElementById('support-thread-status'),
    composer: document.getElementById('support-composer'),
    body: document.getElementById('support-reply-body'),
    image: document.getElementById('support-reply-image'),
    imagePreview: document.getElementById('support-reply-image-preview'),
    imagePreviewImg: document.getElementById('support-reply-image-preview-img'),
    imageClear: document.getElementById('support-reply-image-clear'),
    sendBtn: document.getElementById('support-reply-submit'),
    btnAssign: document.getElementById('support-btn-assign'),
    btnClose: document.getElementById('support-btn-close'),
    btnReopen: document.getElementById('support-btn-reopen'),
    btnToggleInfo: document.getElementById('support-btn-toggle-info'),
    btnCloseInfo: document.getElementById('support-btn-close-info'),
    search: document.getElementById('support-search'),
    soundToggle: document.getElementById('support-sound-toggle'),
    liveBadge: document.getElementById('support-live-badge'),
    liveDot: document.getElementById('support-live-dot'),
    unreadBadges: () => document.querySelectorAll('[data-support-unread-badge]'),
    tabs: document.querySelectorAll('[data-support-filter]'),
    cannedChips: document.querySelectorAll('.support-canned-chip'),
    // Info Pane elements
    infoPane: document.getElementById('support-info-pane'),
    infoAvatar: document.getElementById('support-info-avatar'),
    infoName: document.getElementById('support-info-name'),
    infoTier: document.getElementById('support-info-tier'),
    infoPhone: document.getElementById('support-info-phone'),
    infoEmail: document.getElementById('support-info-email'),
    infoCustomerLink: document.getElementById('support-info-customer-link'),
    infoWhatsappLink: document.getElementById('support-info-whatsapp-link'),
    infoOrderCard: document.getElementById('support-info-order-card'),
    infoOrderStatus: document.getElementById('support-info-order-status'),
    infoOrderNum: document.getElementById('support-info-order-num'),
    infoOrderTotal: document.getElementById('support-info-order-total'),
    infoOrderLink: document.getElementById('support-info-order-link'),
    actionsLog: document.getElementById('support-actions-log'),
    downloadBtn: document.getElementById('support-image-download-btn'),
  };

  const state = {
    filter: root.dataset.filter || '',
    search: root.dataset.search || '',
    selectedUuid: root.dataset.selectedUuid || '',
    items: [],
    fingerprint: null,
    latestMessageId: 0,
    soundEnabled: localStorage.getItem('supportChatSound') !== '0',
    pollTimer: null,
    firestoreUnsubList: null,
    firestoreUnsubThread: null,
    usingFirestore: false,
    blinkTimer: null,
  };

  function tpl(template, uuid) {
    return String(template || '').replace('__UUID__', encodeURIComponent(uuid));
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function statusClass(status) {
    if (status === 'open') return 'bg-success-subtle text-success border border-success-subtle';
    if (status === 'assigned') return 'bg-primary-subtle text-primary border border-primary-subtle';
    if (status === 'closed') return 'bg-secondary-subtle text-secondary border border-secondary-subtle';
    return 'bg-light text-muted border';
  }

  function setLiveBadge(ok, text) {
    if (!els.liveBadge) return;
    els.liveBadge.className = 'badge fs-12 ' + (ok ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning');
    els.liveBadge.textContent = text;
    if (els.liveDot) {
      els.liveDot.style.backgroundColor = ok ? 'var(--vz-success)' : 'var(--vz-warning)';
    }
  }

  function updateUnreadBadges(count) {
    const n = Number(count || 0);
    els.unreadBadges().forEach((badge) => {
      badge.innerHTML = `<i class="ri-mail-unread-line me-1"></i> ${n} غير مقروء`;
      badge.classList.toggle('d-none', n <= 0);
      if (n > 0) {
        badge.classList.add('support-unread-blink');
        clearTimeout(state.blinkTimer);
        state.blinkTimer = setTimeout(() => badge.classList.remove('support-unread-blink'), 2500);
      }
    });
  }

  function playAlert() {
    if (!state.soundEnabled) return;
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.value = 880;
      gain.gain.value = 0.05;
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start();
      setTimeout(() => {
        osc.stop();
        ctx.close();
      }, 160);
    } catch (_) {}
  }

  async function api(url, options = {}) {
    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf,
        ...(options.headers || {}),
      },
      ...options,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(data.message || 'تعذر إكمال الطلب.');
    }
    return data;
  }

  function renderList(items, { announce = false } = {}) {
    const prevUnread = state.items.filter((i) => i.unread).length;
    state.items = items || [];
    const nextUnread = state.items.filter((i) => i.unread).length;
    if (announce && nextUnread > prevUnread) {
      playAlert();
    }

    if (!els.list) return;
    if (!state.items.length) {
      els.list.innerHTML = `
        <div class="text-center text-muted py-5 px-3">
          <div class="avatar-sm bg-light rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center text-muted">
            <i class="ri-inbox-line fs-20"></i>
          </div>
          <div class="fs-13 fw-semibold">لا توجد محادثات</div>
          <div class="fs-11 text-muted mt-1">لم يتم العثور على أي محادثة مطابقة للتصنيف الحالي.</div>
        </div>
      `;
      return;
    }

    els.list.innerHTML = state.items.map((item) => {
      const active = item.uuid === state.selectedUuid ? ' is-active' : '';
      const unread = item.unread ? ' is-unread' : '';
      const firstChar = escapeHtml((item.customer_name || 'ع').trim().charAt(0));
      const orderTag = item.related_order_id
        ? `<span class="badge bg-warning-subtle text-warning border border-warning-subtle fs-10 px-1 py-0"><i class="ri-shopping-bag-3-line"></i> #${escapeHtml(item.related_order_number || item.related_order_id)}</span>`
        : '';
      const agentTag = item.assigned_admin
        ? `<span class="text-muted fs-10"><i class="ri-user-star-line text-primary"></i> ${escapeHtml(item.assigned_admin)}</span>`
        : '<span class="text-warning fs-10"><i class="ri-user-unfollow-line"></i> غير مسند</span>';

      return `
        <button type="button" class="support-list-item${active}${unread}" data-uuid="${escapeHtml(item.uuid)}">
          <div class="d-flex align-items-start gap-2">
            <div class="avatar-xs bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold fs-12 flex-shrink-0 mt-1">
              ${firstChar}
            </div>
            <div class="flex-grow-1 min-w-0">
              <div class="d-flex align-items-center justify-content-between gap-1 mb-1">
                <div class="fw-bold text-dark text-truncate fs-13">${escapeHtml(item.customer_name || 'عميل')}</div>
                <span class="text-muted fs-11 flex-shrink-0">${escapeHtml(item.last_message_human || '')}</span>
              </div>
              <div class="text-truncate text-muted fs-12 mb-1" style="max-height: 1.5em;">${escapeHtml(item.last_message_preview || '—')}</div>
              <div class="d-flex align-items-center justify-content-between gap-1 mt-1">
                <div class="d-flex align-items-center gap-1 flex-wrap min-w-0">
                  <span class="badge ${statusClass(item.status)} fs-10 px-1 py-0">${escapeHtml(item.status_label || item.status)}</span>
                  ${orderTag}
                </div>
                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                  ${agentTag}
                  ${item.unread ? '<span class="badge bg-danger rounded-pill fs-10 px-1 py-0">جديد</span>' : ''}
                </div>
              </div>
            </div>
          </div>
        </button>
      `;
    }).join('');

    els.list.querySelectorAll('[data-uuid]').forEach((btn) => {
      btn.addEventListener('click', () => selectConversation(btn.dataset.uuid));
    });
  }

  function renderMessage(message) {
    const mine = message.sender_type === 'admin';
    const rowClass = mine ? 'support-msg-row support-msg-admin' : 'support-msg-row support-msg-customer';
    const avatar = mine
      ? `<div class="avatar-xs bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold fs-11 flex-shrink-0 align-self-end mb-1 shadow-sm" title="فريق الدعم"><i class="ri-customer-service-2-line"></i></div>`
      : `<div class="avatar-xs bg-light text-dark rounded-circle d-flex align-items-center justify-content-center fw-bold fs-11 flex-shrink-0 align-self-end mb-1 border shadow-sm" title="العميل"><i class="ri-user-3-line"></i></div>`;

    let attachment = '';
    if (message.has_attachment && message.attachment_url) {
      const url = escapeHtml(message.attachment_url);
      attachment = `
        <div class="mb-2 position-relative">
          <button type="button" class="btn p-0 border-0 bg-transparent support-chat-image-btn" data-image-url="${url}">
            <img src="${url}" alt="مرفق" class="rounded support-chat-thumb shadow-sm" loading="lazy">
          </button>
        </div>`;
    }

    const body = message.body
      ? `<div class="message-text" style="white-space:pre-wrap; word-break:break-word;">${escapeHtml(message.body)}</div>`
      : '';

    const checkmarks = mine ? '<i class="ri-check-double-line text-white-50 ms-1" title="تم التسليم"></i>' : '';

    return `
      <div class="${rowClass}" data-message-id="${message.id}">
        ${avatar}
        <div class="support-bubble">
          ${attachment}
          ${body}
          <div class="support-meta mt-1 d-flex align-items-center justify-content-end gap-1">
            <span>${escapeHtml(message.created_at || '')}</span>
            ${checkmarks}
          </div>
        </div>
      </div>
    `;
  }

  function formatMessagesWithDates(messages) {
    if (!messages || !messages.length) {
      return '<div class="text-center text-muted py-5"><div class="avatar-sm bg-light rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center text-muted"><i class="ri-chat-1-line fs-20"></i></div>لا توجد رسائل سابقة في هذه المحادثة.</div>';
    }

    let html = '';
    let lastDate = '';
    messages.forEach((msg) => {
      const datePart = msg.created_at ? msg.created_at.split(' ')[0] : '';
      if (datePart && datePart !== lastDate) {
        html += `<div class="support-date-divider"><span><i class="ri-calendar-line me-1"></i> ${escapeHtml(datePart)}</span></div>`;
        lastDate = datePart;
      }
      html += renderMessage(msg);
    });
    return html;
  }

  function bindImageButtons() {
    const modalEl = document.getElementById('supportImagePreviewModal');
    const previewImg = document.getElementById('support-image-preview');
    const downloadBtn = document.getElementById('support-image-download-btn');
    const modal = modalEl && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    els.messages?.querySelectorAll('.support-chat-image-btn').forEach((button) => {
      if (button.dataset.bound === '1') return;
      button.dataset.bound = '1';
      button.addEventListener('click', () => {
        const url = button.dataset.imageUrl || '';
        if (previewImg) previewImg.src = url;
        if (downloadBtn) {
          downloadBtn.href = url;
          downloadBtn.setAttribute('download', 'support-attachment');
        }
        modal?.show();
      });
    });
  }

  function renderActions(actions) {
    if (!els.actionsLog) return;
    if (!actions?.length) {
      els.actionsLog.innerHTML = '<div class="text-muted fs-11 text-center py-3">لا يوجد سجل إجراءات بعد.</div>';
      return;
    }
    els.actionsLog.innerHTML = actions.map((a) => `
      <div class="support-timeline-item">
        <span class="support-timeline-dot"></span>
        <div class="fw-bold text-dark fs-12">${escapeHtml(a.label)}</div>
        <div class="text-muted fs-11 mt-1">
          <i class="ri-user-line me-1"></i> ${escapeHtml(a.actor || 'النظام')} · ${escapeHtml(a.human || '')}
        </div>
      </div>
    `).join('');
  }

  function showThreadUi(conversation) {
    els.threadEmpty?.classList.add('d-none');
    if (els.threadPane) {
      els.threadPane.classList.remove('d-none');
      els.threadPane.style.display = 'flex';
    }

    const name = conversation.customer_name || 'عميل';
    if (els.title) els.title.textContent = name;
    if (els.avatar) {
      els.avatar.textContent = (name.trim().charAt(0) || 'ع').toUpperCase();
    }

    if (els.meta) {
      let metaHtml = `
        <span class="badge bg-light text-dark border px-2 py-1" dir="ltr">
          <i class="ri-phone-line text-success me-1"></i> ${escapeHtml(conversation.customer_phone || '')}
        </span>
      `;
      if (conversation.customer_phone) {
        metaHtml += `
          <button type="button" class="btn btn-xs btn-subtle-secondary copy-phone-btn px-2" data-phone="${escapeHtml(conversation.customer_phone)}" title="نسخ رقم الهاتف">
            <i class="ri-file-copy-line"></i> نسخ
          </button>
        `;
      }
      if (conversation.customer_whatsapp_url) {
        metaHtml += `
          <a href="${escapeHtml(conversation.customer_whatsapp_url)}" target="_blank" rel="noopener" class="btn btn-xs btn-subtle-success px-2" title="محادثة واتساب">
            <i class="ri-whatsapp-line"></i> واتساب
          </a>
        `;
      }
      if (conversation.related_order_id) {
        const orderUrl = conversation.related_order_url || `/admin/orders/${conversation.related_order_id}`;
        metaHtml += `
          <a href="${escapeHtml(orderUrl)}" target="_blank" class="badge bg-warning-subtle text-warning border border-warning-subtle text-decoration-none px-2 py-1" title="فتح تفاصيل الطلب">
            <i class="ri-shopping-cart-2-line me-1"></i> طلب #${escapeHtml(conversation.related_order_number || conversation.related_order_id)}
          </a>
        `;
      }
      metaHtml += `
        <span class="text-muted ms-1">
          <i class="ri-user-star-line text-primary me-1"></i> المسؤول: <strong>${escapeHtml(conversation.assigned_admin || 'غير مسند')}</strong>
        </span>
      `;
      els.meta.innerHTML = metaHtml;

      els.meta.querySelectorAll('.copy-phone-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
          navigator.clipboard?.writeText(btn.dataset.phone || '').then(() => {
            btn.innerHTML = '<i class="ri-check-line text-success"></i> تم النسخ';
            setTimeout(() => { btn.innerHTML = '<i class="ri-file-copy-line"></i> نسخ'; }, 2000);
          });
        });
      });
    }

    if (els.status) {
      els.status.className = 'badge ' + statusClass(conversation.status);
      els.status.textContent = conversation.status_label || conversation.status;
    }

    els.btnAssign?.classList.toggle('d-none', !conversation.can_assign || conversation.is_closed);
    els.btnClose?.classList.toggle('d-none', !conversation.can_close || conversation.is_closed);
    els.btnReopen?.classList.toggle('d-none', !conversation.can_reopen || !conversation.is_closed);
    els.composer?.classList.toggle('d-none', !conversation.can_reply || conversation.is_closed);

    // Populate Right Info Pane
    if (els.infoName) els.infoName.textContent = name;
    if (els.infoAvatar) els.infoAvatar.textContent = (name.trim().charAt(0) || 'ع').toUpperCase();
    if (els.infoTier) els.infoTier.textContent = conversation.customer_tier || 'عميل عادي';
    if (els.infoPhone) els.infoPhone.textContent = conversation.customer_phone || '—';
    if (els.infoEmail) els.infoEmail.textContent = conversation.customer_email || 'غير مسجل';
    if (els.infoCustomerLink) {
      els.infoCustomerLink.href = conversation.customer_url || (conversation.customer_id ? `/admin/customers/${conversation.customer_id}` : '#');
      els.infoCustomerLink.classList.toggle('d-none', !conversation.customer_id);
    }
    if (els.infoWhatsappLink) {
      els.infoWhatsappLink.href = conversation.customer_whatsapp_url || '#';
      els.infoWhatsappLink.classList.toggle('d-none', !conversation.customer_whatsapp_url);
    }

    // Order Info Card
    if (els.infoOrderCard) {
      if (conversation.related_order_id) {
        els.infoOrderCard.classList.remove('d-none');
        if (els.infoOrderNum) els.infoOrderNum.textContent = conversation.related_order_number || `#${conversation.related_order_id}`;
        if (els.infoOrderStatus) {
          els.infoOrderStatus.className = `badge ${conversation.related_order_status_badge || 'bg-light text-dark'}`;
          els.infoOrderStatus.textContent = conversation.related_order_status_label || 'قيد المعالجة';
        }
        if (els.infoOrderTotal) els.infoOrderTotal.textContent = conversation.related_order_total ? `${conversation.related_order_total} ر.ي` : '—';
        if (els.infoOrderLink) els.infoOrderLink.href = conversation.related_order_url || `/admin/orders/${conversation.related_order_id}`;
      } else {
        els.infoOrderCard.classList.add('d-none');
      }
    }
  }

  async function refreshList({ announce = false } = {}) {
    const params = new URLSearchParams();
    if (state.filter) params.set('filter', state.filter);
    if (state.search) params.set('search', state.search);
    const data = await api(urls.liveIndex + (params.toString() ? '?' + params.toString() : ''));
    const changed = state.fingerprint && data.fingerprint !== state.fingerprint;
    state.fingerprint = data.fingerprint;
    renderList(data.items || [], { announce: announce && changed });
    updateUnreadBadges(data.unread_count);
    setLiveBadge(true, state.usingFirestore ? 'Firestore مباشر' : 'تحديث تلقائي');
  }

  async function selectConversation(uuid) {
    if (!uuid) return;
    state.selectedUuid = uuid;
    const url = new URL(window.location.href);
    url.searchParams.set('c', uuid);
    if (state.filter) url.searchParams.set('filter', state.filter); else url.searchParams.delete('filter');
    if (state.search) url.searchParams.set('search', state.search); else url.searchParams.delete('search');
    window.history.replaceState({}, '', url.toString());
    renderList(state.items);
    await refreshMessages();
    attachThreadListener(uuid);
  }

  async function refreshMessages() {
    if (!state.selectedUuid) return;
    const data = await api(tpl(urls.messages, state.selectedUuid));
    const conversation = data.conversation;
    showThreadUi(conversation);
    const nextLatest = Number(data.latest_message_id || 0);
    const grew = nextLatest > state.latestMessageId && state.latestMessageId > 0;
    state.latestMessageId = nextLatest;
    if (els.messages) {
      els.messages.innerHTML = formatMessagesWithDates(data.messages || []);
      bindImageButtons();
      els.messages.scrollTop = els.messages.scrollHeight;
    }
    renderActions(data.actions || []);
    updateUnreadBadges(data.unread_count);
    if (grew) playAlert();
    state.items = state.items.map((item) => item.uuid === conversation.uuid
      ? { ...item, ...conversation, unread: false }
      : item);
    renderList(state.items);
  }

  async function postAction(kind) {
    if (!state.selectedUuid) return;
    const map = { assign: urls.assign, close: urls.close, reopen: urls.reopen };
    await api(tpl(map[kind], state.selectedUuid), { method: 'POST' });
    await Promise.all([refreshMessages(), refreshList()]);
  }

  async function sendReply(event) {
    event?.preventDefault();
    if (!state.selectedUuid || !els.composer) return;
    const bodyVal = (els.body?.value || '').trim();
    const hasImage = !!els.image?.files?.[0];
    if (!bodyVal && !hasImage) return;

    const formData = new FormData();
    formData.append('_token', csrf);
    formData.append('body', bodyVal);
    if (hasImage) {
      formData.append('image', els.image.files[0]);
    }
    els.sendBtn && (els.sendBtn.disabled = true);
    try {
      await api(tpl(urls.reply, state.selectedUuid), { method: 'POST', body: formData });
      if (els.body) els.body.value = '';
      clearImagePreview();
      await Promise.all([refreshMessages(), refreshList()]);
    } catch (e) {
      alert(e.message || 'تعذر الإرسال');
    } finally {
      els.sendBtn && (els.sendBtn.disabled = false);
      els.body?.focus();
    }
  }

  function clearImagePreview() {
    if (els.image) els.image.value = '';
    els.imagePreview?.classList.add('d-none');
    if (els.imagePreviewImg) els.imagePreviewImg.src = '';
  }

  function startPollingFallback() {
    state.usingFirestore = false;
    if (state.pollTimer) return;
    state.pollTimer = setInterval(() => {
      refreshList({ announce: true }).catch(() => setLiveBadge(false, 'تعذر التحديث'));
      if (state.selectedUuid) {
        refreshMessages().catch(() => {});
      }
    }, 5000);
  }

  function stopPollingFallback() {
    if (state.pollTimer) {
      clearInterval(state.pollTimer);
      state.pollTimer = null;
    }
  }

  async function initFirestore() {
    if (!window.firebase || !firebaseConfig.apiKey || !firebaseConfig.projectId) {
      startPollingFallback();
      return;
    }
    try {
      if (!firebase.apps.length) {
        firebase.initializeApp(firebaseConfig);
      }
      const tokenRes = await api(urls.firebaseToken);
      const customToken = tokenRes?.data?.token;
      if (!customToken) throw new Error('no token');
      await firebase.auth().signInWithCustomToken(customToken);
      const db = firebase.firestore();
      state.usingFirestore = true;
      stopPollingFallback();
      if (state.firestoreUnsubList) state.firestoreUnsubList();
      state.firestoreUnsubList = db.collection('support_conversations')
        .orderBy('last_message_at', 'desc')
        .limit(50)
        .onSnapshot(
          () => { refreshList({ announce: true }).catch(() => {}); },
          () => startPollingFallback(),
        );
      setLiveBadge(true, 'Firestore مباشر');
      if (state.selectedUuid) attachThreadListener(state.selectedUuid);
    } catch (_) {
      startPollingFallback();
    }
  }

  function attachThreadListener(uuid) {
    if (!state.usingFirestore || !window.firebase) return;
    if (state.firestoreUnsubThread) {
      state.firestoreUnsubThread();
      state.firestoreUnsubThread = null;
    }
    state.firestoreUnsubThread = firebase.firestore()
      .collection('support_conversations')
      .doc(uuid)
      .collection('messages')
      .orderBy('created_at')
      .onSnapshot(
        () => { refreshMessages().catch(() => {}); },
        () => {},
      );
  }

  // Events & Handlers
  els.tabs.forEach((tab) => {
    tab.addEventListener('click', async () => {
      state.filter = tab.dataset.supportFilter || '';
      els.tabs.forEach((t) => {
        const on = t === tab;
        t.classList.toggle('active', on);
        t.classList.toggle('btn-primary', on);
        t.classList.toggle('btn-soft-secondary', !on);
      });
      state.fingerprint = null;
      await refreshList({ announce: false });
    });
  });

  let searchTimer = null;
  els.search?.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(async () => {
      state.search = els.search.value.trim();
      state.fingerprint = null;
      await refreshList({ announce: false });
    }, 300);
  });

  els.soundToggle && (els.soundToggle.checked = state.soundEnabled);
  els.soundToggle?.addEventListener('change', () => {
    state.soundEnabled = !!els.soundToggle.checked;
    localStorage.setItem('supportChatSound', state.soundEnabled ? '1' : '0');
  });

  // Enter to send, Shift+Enter for new line
  els.body?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
      event.preventDefault();
      sendReply(event);
    }
  });

  els.composer?.addEventListener('submit', sendReply);
  els.btnAssign?.addEventListener('click', () => postAction('assign'));
  els.btnClose?.addEventListener('click', () => postAction('close'));
  els.btnReopen?.addEventListener('click', () => postAction('reopen'));

  // Toggle Right Info Pane
  els.btnToggleInfo?.addEventListener('click', () => {
    els.shell?.classList.toggle('is-info-hidden');
  });
  els.btnCloseInfo?.addEventListener('click', () => {
    els.shell?.classList.add('is-info-hidden');
  });

  // Canned Responses Chips
  document.querySelectorAll('.support-canned-chip').forEach((chip) => {
    chip.addEventListener('click', function () {
      if (!els.body) return;
      const text = this.dataset.quickText || '';
      els.body.value = (els.body.value ? els.body.value + ' ' : '') + text;
      els.body.focus();
    });
  });

  // Image Upload Preview
  els.image?.addEventListener('change', () => {
    const file = els.image.files?.[0];
    if (!file) {
      clearImagePreview();
      return;
    }
    const url = URL.createObjectURL(file);
    if (els.imagePreviewImg) els.imagePreviewImg.src = url;
    els.imagePreview?.classList.remove('d-none');
  });
  els.imageClear?.addEventListener('click', clearImagePreview);

  // Bootstrap from server-rendered JSON
  try {
    const bootEl = document.getElementById('support-bootstrap');
    const boot = bootEl ? JSON.parse(bootEl.textContent || '{}') : { items: [] };
    renderList(boot.items || []);
    updateUnreadBadges(boot.unread_count || 0);
    state.fingerprint = boot.fingerprint || null;
  } catch (_) {}

  refreshList({ announce: false })
    .then(() => {
      if (state.selectedUuid) return selectConversation(state.selectedUuid);
      return null;
    })
    .finally(() => initFirestore());
})();
