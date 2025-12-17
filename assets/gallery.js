(function () {
  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.from((root || document).querySelectorAll(sel)); }

  // Build lightbox once
  function ensureLightbox() {
    let lb = qs('.fbgs-lightbox');
    if (lb) return lb;

    lb = document.createElement('div');
    lb.className = 'fbgs-lightbox';
    lb.innerHTML = `
      <div class="fbgs-lightbox-backdrop" data-fbgs-close="1"></div>
      <button class="fbgs-lightbox-prev" type="button" aria-label="Poprzednie">‹</button>
      <button class="fbgs-lightbox-next" type="button" aria-label="Następne">›</button>
      <button class="fbgs-lightbox-close" type="button" aria-label="Zamknij" data-fbgs-close="1">✕</button>

      <div class="fbgs-lightbox-dialog" role="dialog" aria-modal="true">
        <div class="fbgs-lightbox-figure">
          <div class="fbgs-lightbox-img-wrap">
            <img class="fbgs-lightbox-img" alt="">
          </div>
          <div class="fbgs-lightbox-caption"></div>
        </div>
      </div>
    `;
    document.body.appendChild(lb);
    return lb;
  }

  function initGallery(root) {
    const items = qsa('a.fbgs-photo[href]', root);
    if (!items.length) return;

    const lb = ensureLightbox();
    const imgEl = qs('.fbgs-lightbox-img', lb);
    const capEl = qs('.fbgs-lightbox-caption', lb);
    const prevBtn = qs('.fbgs-lightbox-prev', lb);
    const nextBtn = qs('.fbgs-lightbox-next', lb);

    let index = 0;

    function setIndex(i) {
      index = (i + items.length) % items.length;
      const a = items[index];
      const href = a.getAttribute('href');
      const caption = a.getAttribute('data-caption') || a.getAttribute('title') || '';

      // Set now
      imgEl.src = href;
      imgEl.alt = caption || 'Zdjęcie';
      capEl.textContent = caption;

      // Preload neighbors
      const next = items[(index + 1) % items.length].getAttribute('href');
      const prev = items[(index - 1 + items.length) % items.length].getAttribute('href');
      const preload1 = new Image(); preload1.src = next;
      const preload2 = new Image(); preload2.src = prev;
    }

    function openAt(i) {
      setIndex(i);
      lb.classList.add('is-open');
      document.documentElement.style.overflow = 'hidden';
    }

    function close() {
      lb.classList.remove('is-open');
      document.documentElement.style.overflow = '';
      // Optional: clear src to stop loading
      // imgEl.src = '';
    }

    // Click on thumbnails
    items.forEach((a, i) => {
      a.addEventListener('click', (e) => {
        // allow open in new tab if ctrl/cmd
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) return;
        e.preventDefault();
        openAt(i);
      });
    });

    // Controls
    prevBtn.addEventListener('click', () => setIndex(index - 1));
    nextBtn.addEventListener('click', () => setIndex(index + 1));

    // Backdrop / close buttons
    lb.addEventListener('click', (e) => {
      const target = e.target;
      if (target && target.getAttribute && target.getAttribute('data-fbgs-close') === '1') {
        close();
      }
    });

    // Keyboard
    document.addEventListener('keydown', (e) => {
      if (!lb.classList.contains('is-open')) return;
      if (e.key === 'Escape') close();
      if (e.key === 'ArrowLeft') setIndex(index - 1);
      if (e.key === 'ArrowRight') setIndex(index + 1);
    });

    // Swipe (simple)
    let startX = 0, startY = 0, moved = false;
    imgEl.addEventListener('touchstart', (e) => {
      moved = false;
      const t = e.touches[0];
      startX = t.clientX; startY = t.clientY;
    }, { passive: true });

    imgEl.addEventListener('touchmove', (e) => {
      moved = true;
    }, { passive: true });

    imgEl.addEventListener('touchend', (e) => {
      if (!moved) return;
      const t = e.changedTouches[0];
      const dx = t.clientX - startX;
      const dy = t.clientY - startY;
      if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 40) {
        if (dx > 0) setIndex(index - 1);
        else setIndex(index + 1);
      }
    }, { passive: true });

    // Expose close (optional)
    window.fbgsCloseLightbox = close;
  }
jQuery(function ($) {
  const $modal = $('#fbgs-modal');
  const $list = $('#fbgs-list');
  const $onlyNew = $('#fbgs-only-new');

  function openModal() {
    $modal.show();
  }
  function closeModal() {
    $modal.hide();
    $list.empty();
  }

  function render(albums) {
    const onlyNew = $onlyNew.is(':checked');
    const filtered = onlyNew ? albums.filter(a => !a.exists) : albums;

    if (!filtered.length) {
      $list.html('<p>Brak albumów do pokazania.</p>');
      return;
    }

    const html = filtered.map(a => {
      const badge = a.exists ? '✅ już jest' : '➕ nowy';
      const disabled = a.exists ? 'disabled' : '';
      const checked = a.exists ? '' : 'checked';
      const meta = [
        a.created_time ? a.created_time.substring(0,10) : '',
        a.count != null ? (a.count + ' zdjęć') : ''
      ].filter(Boolean).join(' • ');

      return `
        <label style="display:flex; gap:10px; align-items:flex-start; padding:8px 0; border-bottom:1px solid #eee;">
          <input type="checkbox" class="fbgs-alb" value="${a.id}" ${checked} ${disabled}>
          <div>
            <div><strong>${escapeHtml(a.name)}</strong> <span style="opacity:.7">${badge}</span></div>
            <div style="opacity:.7; font-size:12px;">${escapeHtml(meta)}</div>
          </div>
        </label>
      `;
    }).join('');

    $list.html(html);
  }

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  }

  async function loadPreview() {
    $list.html('<p>Ładowanie albumów z Facebooka…</p>');

    const res = await $.post(FBGS_ADMIN.ajax_url, {
      action: 'fbgs_preview_albums',
      nonce: FBGS_ADMIN.nonce
    });

    if (!res || !res.success) {
      $list.html('<p>Błąd: ' + escapeHtml(res?.data?.message || 'nieznany') + '</p>');
      return;
    }

    const albums = res.data.albums || [];
    $modal.data('albums', albums);
    render(albums);
  }

  $('#fbgs-preview').on('click', function (e) {
    e.preventDefault();
    openModal();
    loadPreview();
  });

  $('#fbgs-close, .fbgs-modal-backdrop').on('click', function (e) {
    e.preventDefault();
    closeModal();
  });

  $onlyNew.on('change', function () {
    render($modal.data('albums') || []);
  });

  $('#fbgs-import-selected').on('click', function (e) {
    e.preventDefault();

    const ids = $('.fbgs-alb:checked').map(function () { return $(this).val(); }).get();

    if (!ids.length) {
      alert('Nie wybrano żadnych nowych albumów do importu.');
      return;
    }

    const $inputs = $('#fbgs-selected-inputs').empty();
    ids.forEach(id => $inputs.append(`<input type="hidden" name="fb_album_ids[]" value="${id}">`));

    $('#fbgs-import-form')[0].submit();
  });
});

  // Init on DOM ready
  document.addEventListener('DOMContentLoaded', function () {
    qsa('.fbgs-wrap').forEach(initGallery);
  });
})();
