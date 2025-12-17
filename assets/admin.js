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
      $list.html('<p>Brak albumów.</p>');
      return;
    }

    const html = filtered.map(a => `
      <label style="display:flex;gap:10px;padding:8px 0;border-bottom:1px solid #eee;">
        <input type="checkbox" class="fbgs-alb" value="${a.id}" ${a.exists ? 'disabled' : 'checked'}>
        <div>
          <strong>${a.name}</strong>
          <div style="font-size:12px;opacity:.7">
            ${a.exists ? '✅ już istnieje' : '➕ nowy'}
            ${a.created_time ? ' • ' + a.created_time.substring(0,10) : ''}
            ${a.count != null ? ' • ' + a.count + ' zdjęć' : ''}
          </div>
        </div>
      </label>
    `).join('');

    $list.html(html);
  }

  async function loadAlbums() {
    $list.html('<p>Ładowanie…</p>');

    const res = await $.post(FBGS_ADMIN.ajax_url, {
      action: 'fbgs_preview_albums',
      nonce: FBGS_ADMIN.nonce
    });

    if (!res.success) {
      $list.html('<p>Błąd pobierania albumów</p>');
      return;
    }

    $modal.data('albums', res.data.albums);
    render(res.data.albums);
  }

  $('#fbgs-preview').on('click', function (e) {
    e.preventDefault();
    openModal();
    loadAlbums();
  });

  $('#fbgs-close, .fbgs-modal-backdrop').on('click', closeModal);

  $onlyNew.on('change', () => render($modal.data('albums') || []));

  $('#fbgs-import-selected').on('click', function () {
    const ids = $('.fbgs-alb:checked').map(function () {
      return $(this).val();
    }).get();

    if (!ids.length) {
      alert('Nie wybrano albumów');
      return;
    }

    const $inputs = $('#fbgs-selected-inputs').empty();
    ids.forEach(id =>
      $inputs.append(`<input type="hidden" name="fb_album_ids[]" value="${id}">`)
    );

    $('#fbgs-import-form')[0].submit();
  });
});
