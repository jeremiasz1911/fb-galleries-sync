<?php
class FBGS_CPT {
  public function boot() {
    add_action('init', [$this, 'register']);
  }

  public function register() {
    register_post_type('fb_album', [
      'label' => 'Facebook Albumy',
      'public' => false,
      'show_ui' => true,
      'supports' => ['title', 'editor', 'thumbnail'],
    ]);

    register_taxonomy('fb_folder', ['fb_album'], [
      'label' => 'Foldery galerii',
      'hierarchical' => true,
      'show_ui' => true,
      'show_admin_column' => true,
    ]);
  }
}
