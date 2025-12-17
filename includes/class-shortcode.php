<?php
class FBGS_Shortcode {

  public function boot() {
    add_shortcode('fb_gallery', [$this, 'render']);

    // ⬇️ DODAJ TO
    add_action('wp_enqueue_scripts', [$this, 'register_assets']);
  }

  public function register_assets() {
    wp_register_style(
      'fbgs-gallery',
      FBGS_URL . 'assets/gallery.css',
      [],
      '0.1.0'
    );

    wp_register_script(
      'fbgs-gallery',
      FBGS_URL . 'assets/gallery.js',
      [],
      '0.1.0',
      true
    );
  }


  public function render($atts) {

    // ⬇️ TO JEST KLUCZOWE
    wp_enqueue_style('fbgs-gallery');
    wp_enqueue_script('fbgs-gallery');

    $atts = shortcode_atts([
      'album'    => '',
      'folder'   => '',
      'per_page' => 24,
      'page'     => max(1, (int)($_GET['fbpg'] ?? 1)),
    ], $atts);

    $taxQuery = [];
    if ($atts['folder']) {
      $taxQuery[] = [
        'taxonomy' => 'fb_folder',
        'field' => 'slug',
        'terms' => $atts['folder'],
      ];
    }

    $q = new WP_Query([
      'post_type' => 'fb_album',
      'post_status' => 'publish',
      'posts_per_page' => (int)$atts['per_page'],
      'paged' => (int)$atts['page'],
      'tax_query' => $taxQuery ?: null,
    ]);

    ob_start();
    echo '<div class="fbgs-grid">';
    foreach ($q->posts as $p) {
      $date = get_post_meta($p->ID, 'fb_created_time', true);
      echo '<a class="fbgs-card" href="#">';
      echo '<div class="fbgs-title">' . esc_html(get_the_title($p)) . '</div>';
      if ($date) echo '<div class="fbgs-date">' . esc_html(date('Y-m-d', strtotime($date))) . '</div>';
      echo '</a>';
    }
    echo '</div>';

    // prosta paginacja
    if ($q->max_num_pages > 1) {
      echo '<div class="fbgs-pagination">';
      for ($i=1; $i<=$q->max_num_pages; $i++) {
        $url = add_query_arg('fbpg', $i);
        echo '<a href="'.esc_url($url).'">'.(int)$i.'</a> ';
      }
      echo '</div>';
    }

    return ob_get_clean();
  }
}
