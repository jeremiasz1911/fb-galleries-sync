<?php
class FBGS_Admin_Settings {
  public function boot() {
    add_action('admin_menu', [$this, 'menu']);
    add_action('admin_init', [$this, 'settings']);
  }

  public function menu() {
    add_options_page('FB Galleries Sync', 'FB Galleries Sync', 'manage_options', 'fbgs-settings', [$this, 'page']);
  }

  public function settings() {
    register_setting('fbgs', 'fbgs_page_id');
    register_setting('fbgs', 'fbgs_page_token');
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
      </form>
    </div>
    <?php
  }
}
