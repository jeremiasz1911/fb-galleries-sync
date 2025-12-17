<?php
class FBGS_Sync {

  public function boot_cron() {
    add_action('admin_post_fbgs_backfill', [$this, 'handle_backfill']);
    add_action('fbgs_cron_sync', [$this, 'run_sync']);

    if (!wp_next_scheduled('fbgs_cron_sync')) {
      wp_schedule_event(time() + 300, 'daily', 'fbgs_cron_sync');
    }
  }

  public function handle_backfill() {
    if (!current_user_can('manage_options')) wp_die('No access');
    check_admin_referer('fbgs_backfill');

    $this->run_sync();

    wp_safe_redirect(admin_url('options-general.php?page=fbgs-settings&synced=1'));
    exit;
  }

  public function run_sync() {
    $token  = get_option('fbgs_page_token');
    $pageId = get_option('fbgs_page_id');
    if (!$token || !$pageId) return;

    $client = new FBGS_FB_Client($token);

    $after = null;
    do {
      $albums = $client->list_albums($pageId, 50, $after);
      foreach (($albums['data'] ?? []) as $a) {
        $this->upsert_album($a);
        $this->sync_album_photos($client, $a['id']);
      }
      $after = $albums['paging']['cursors']['after'] ?? null;
    } while ($after);
  }

  public function run_sync_selected(array $albumIds) {
    $token  = get_option('fbgs_page_token');
    $pageId = get_option('fbgs_page_id');
    if (!$token || !$pageId) return;

    $client = new FBGS_FB_Client($token);

    foreach ($albumIds as $albumId) {
      // minimalne utworzenie albumu jeśli go nie ma
      $this->upsert_album([
        'id' => $albumId,
        'name' => 'Album ' . $albumId,
      ]);

      // zdjęcia dopniemy w kolejnym kroku
    }
  }

  private function upsert_album(array $a) {
    $existing = $this->find_album_post_id($a['id']);
    $postId = $existing ?: wp_insert_post([
      'post_type' => 'fb_album',
      'post_status' => 'publish',
      'post_title' => $a['name'] ?? ('Album ' . $a['id']),
      'post_content' => $a['description'] ?? '',
    ]);

    update_post_meta($postId, 'fb_album_id', $a['id']);
    update_post_meta($postId, 'fb_created_time', $a['created_time'] ?? null);
    update_post_meta($postId, 'fb_updated_time', $a['updated_time'] ?? null);
    update_post_meta($postId, 'fb_count', $a['count'] ?? null);
  }

  private function find_album_post_id(string $fbAlbumId): ?int {
    $q = new WP_Query([
      'post_type' => 'fb_album',
      'post_status' => 'any',
      'fields' => 'ids',
      'meta_query' => [[ 'key' => 'fb_album_id', 'value' => $fbAlbumId ]],
      'posts_per_page' => 1,
    ]);
    return $q->posts[0] ?? null;
  }

  private function sync_album_photos(FBGS_FB_Client $client, string $albumId) {
    // Tu: albo zapis do własnej tabeli wp_fb_gallery_photos,
    // albo do post meta (dla małych albumów).
    // Kluczowe: deduplikacja po fb_photo_id.
  }

}
