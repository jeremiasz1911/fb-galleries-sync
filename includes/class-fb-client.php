<?php
class FBGS_FB_Client {
  private string $token;
  private string $apiBase = 'https://graph.facebook.com/v24.0/';

  public function __construct(string $token) {
    $this->token = $token;
  }

  private function get(string $path, array $params = []) {
    $params['access_token'] = $this->token;
    $url = $this->apiBase . ltrim($path, '/') . '?' . http_build_query($params);

    $res = wp_remote_get($url, ['timeout' => 20]);
    if (is_wp_error($res)) throw new Exception($res->get_error_message());

    $body = json_decode(wp_remote_retrieve_body($res), true);
    if (!empty($body['error'])) throw new Exception($body['error']['message'] ?? 'FB API error');

    return $body;
  }

  public function list_albums(string $pageId, int $limit = 50, ?string $after = null) {
    $params = [
      'fields' => 'id,name,description,count,created_time,updated_time,cover_photo{picture}',
      'limit'  => $limit,
    ];
    if ($after) $params['after'] = $after;
    return $this->get("$pageId/albums", $params);
  }

  public function list_photos(string $albumId, int $limit = 50, ?string $after = null) {
    $params = [
      'fields' => 'id,name,created_time,images',
      'limit'  => $limit,
    ];
    if ($after) $params['after'] = $after;
    return $this->get("$albumId/photos", $params);
  }
}
