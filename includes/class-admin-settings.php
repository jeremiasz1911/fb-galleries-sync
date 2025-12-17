<?php
class FBGS_Admin_Settings {
  public function boot() {
    add_action('admin_menu', [$this, 'menu']);
    add_action('admin_init', [$this, 'settings']);
    add_action('wp_ajax_fbgs_preview_albums', [$this, 'ajax_preview_albums']);
    add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    add_action('admin_post_fbgs_backfill_selected', [$this, 'handle_backfill_selected']);
  }

  public function menu() {
    add_options_page('FB Galleries Sync', 'FB Galleries Sync', 'manage_options', 'fbgs-settings', [$this, 'page']);
  }

  public function settings() {
    register_setting('fbgs', 'fbgs_page_id');
    register_setting('fbgs', 'fbgs_page_token');
  }

  public function enqueue_admin_assets($hook) {
    // tylko na stronie ustawień wtyczki
    if ($hook !== 'settings_page_fbgs-settings') return;

    wp_enqueue_script(
      'fbgs-admin',
      FBGS_URL . 'assets/admin.js',
      ['jquery'],
      '0.1.0',
      true
    );

    wp_localize_script('fbgs-admin', 'FBGS_ADMIN', [
      'ajax_url' => admin_url('admin-ajax.php'),
      'nonce'    => wp_create_nonce('fbgs_admin_nonce'),
    ]);
  }

  public function ajax_preview_albums() {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'No access'], 403);
    check_ajax_referer('fbgs_admin_nonce', 'nonce');

    $token  = get_option('fbgs_page_token');
    $pageId = get_option('fbgs_page_id');
    if (!$token || !$pageId) wp_send_json_error(['message' => 'Brak Page ID lub tokena'], 400);

    // istniejące albumy w WP
    $existingIds = $this->get_existing_album_ids();

    $client = new FBGS_FB_Client($token);

    $after = null;
    $out = [];

    do {
      $res = $client->list_albums($pageId, 50, $after);
      foreach (($res['data'] ?? []) as $a) {
        $id = $a['id'] ?? null;
        if (!$id) continue;

        $out[] = [
          'id' => $id,
          'name' => $a['name'] ?? ('Album ' . $id),
          'created_time' => $a['created_time'] ?? null,
          'count' => $a['count'] ?? null,
          'exists' => in_array($id, $existingIds, true),
        ];
      }
      $after = $res['paging']['cursors']['after'] ?? null;
    } while ($after);

    wp_send_json_success(['albums' => $out]);
  }

  public function handle_backfill_selected() {
    if (!current_user_can('manage_options')) wp_die('No access');
    check_admin_referer('fbgs_backfill_selected');

    $selected = array_map('sanitize_text_field', (array)($_POST['fb_album_ids'] ?? []));

    // tu wołasz sync tylko dla wybranych
    (new FBGS_Sync())->run_sync_selected($selected);

    wp_safe_redirect(admin_url('options-general.php?page=fbgs-settings&synced=1'));
    exit;
  }

  private function get_existing_album_ids(): array {
    $q = new WP_Query([
      'post_type' => 'fb_album',
      'post_status' => 'any',
      'posts_per_page' => -1,
      'fields' => 'ids',
      'meta_query' => [[ 'key' => 'fb_album_id', 'compare' => 'EXISTS' ]],
    ]);

    $ids = [];
    foreach ($q->posts as $pid) {
      $fbid = get_post_meta($pid, 'fb_album_id', true);
      if ($fbid) $ids[] = (string)$fbid;
    }
    return array_values(array_unique($ids));
  }

  public function page() {
    ?>
    <div class="wrap">
      <h1>FB Galleries Sync</h1>

      <form method="post" action="options.php">
        <?php settings_fields('fbgs'); ?>
        <table class="form-table">
          <tr>
            <th>Page ID</th>
            <td><input type="text" name="fbgs_page_id" value="<?php echo esc_attr(get_option('fbgs_page_id')); ?>" class="regular-text"></td>
          </tr>
          <tr>
            <th>Page Access Token</th>
            <td><input type="password" name="fbgs_page_token" value="<?php echo esc_attr(get_option('fbgs_page_token')); ?>" class="regular-text"></td>
          </tr>
        </table>
        <?php submit_button(); ?>
      </form>

      <hr>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="fbgs_backfill">
        <?php wp_nonce_field('fbgs_backfill'); ?>
        <?php submit_button('Backfill teraz (pobierz albumy i zdjęcia)', 'secondary'); ?>
        <button class="button button-secondary" id="fbgs-preview">
          Podgląd backfill (wybierz albumy)
        </button>

        <div id="fbgs-modal" style="display:none;">
          <div class="fbgs-modal-backdrop"></div>
          <div class="fbgs-modal-box">
            <h2>Albumy z Facebooka</h2>

            <label style="display:block;margin:10px 0;">
              <input type="checkbox" id="fbgs-only-new"> pokaż tylko nowe
            </label>

            <div id="fbgs-list" style="max-height:50vh; overflow:auto; border:1px solid #ddd; padding:10px;"></div>

            <div style="margin-top:12px; display:flex; gap:10px;">
              <button class="button button-primary" id="fbgs-import-selected">Importuj zaznaczone</button>
              <button class="button" id="fbgs-close">Zamknij</button>
            </div>

            <form id="fbgs-import-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:none;">
              <input type="hidden" name="action" value="fbgs_backfill_selected">
              <?php wp_nonce_field('fbgs_backfill_selected'); ?>
              <div id="fbgs-selected-inputs"></div>
            </form>
          </div>
        </div>

      </form>
    </div>
    <?php
  }
}
