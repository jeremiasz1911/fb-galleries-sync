<?php
/**
 * Plugin Name: FB Galleries Sync
 * Description: Sync Facebook Page albums/photos into WP, with folders + shortcode gallery.
 * Version: 0.1.0
 */

if (!defined('ABSPATH')) exit;

define('FBGS_PATH', plugin_dir_path(__FILE__));
define('FBGS_URL', plugin_dir_url(__FILE__));

require_once FBGS_PATH . 'includes/class-cpt.php';
require_once FBGS_PATH . 'includes/class-admin-settings.php';
require_once FBGS_PATH . 'includes/class-fb-client.php';
require_once FBGS_PATH . 'includes/class-sync.php';
require_once FBGS_PATH . 'includes/class-shortcode.php';

add_action('plugins_loaded', function () {
  (new FBGS_CPT())->boot();
  (new FBGS_Admin_Settings())->boot();
  (new FBGS_Shortcode())->boot();
  (new FBGS_Sync())->boot_cron();
});
