<?php
/**
 * Plugin Name: Easy SVG Upload
 * Plugin URI: https://www.wpsatkhira.com/plugins/easy-svg-upload
 * Description: Safely enable SVG uploads in WordPress with this powerful plugin. It allows you to seamlessly incorporate SVG files into your website while ensuring they are sanitized to prevent potential vulnerabilities. Additionally, it provides a convenient way to preview uploaded SVGs in the media library. Embrace the creative potential of SVG files within your secure and user-friendly WordPress site.
 * Version: 1.0
 * Requires at least: 5.7
 * Requires PHP: 7.2
 * Author: WordPress Satkhira Community
 * Author URI: https://www.wpsatkhira.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: easy-svg-upload
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    die; // Exit if accessed directly
}

/**
 * Load plugin textdomain.
 */
function esu_load_textdomain() {
    load_plugin_textdomain('easy-svg-upload', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'esu_load_textdomain');

// Register the options page
function esu_easy_svg_upload_options_page() {
    add_options_page(
        __('Easy SVG Upload Settings', 'easy-svg-upload'),
        __('Easy SVG Upload', 'easy-svg-upload'),
        'manage_options',
        'easy-svg-upload',
        'esu_easy_svg_upload_options_page_content'
    );
}
add_action('admin_menu', 'esu_easy_svg_upload_options_page');

// Initialize plugin options
function esu_easy_svg_upload_initialize_options() {
    // Add an option to enable or disable Easy SVG Upload
    add_option('esu_enable_easy_svg_upload', 'off');
}
add_action('admin_init', 'esu_easy_svg_upload_initialize_options');

// Options page content
function esu_easy_svg_upload_options_page_content() {
    ?>
    <div class="wrap">
        <h2><?php _e('Easy SVG Upload Settings', 'easy-svg-upload'); ?></h2>
        <form method="post" action="options.php">
            <?php settings_fields('esu_easy_svg_upload_settings'); ?>
            <?php do_settings_sections('esu-easy-svg-upload-settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Enable Easy SVG Upload', 'easy-svg-upload'); ?></th>
                    <td>
                        <label for="esu_enable_easy_svg_upload">
                            <input type="checkbox" name="esu_enable_easy_svg_upload" id="esu_enable_easy_svg_upload" value="on" <?php checked('on', get_option('esu_enable_easy_svg_upload')); ?>>
                            <?php _e('Enable', 'easy-svg-upload'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register plugin settings
function esu_easy_svg_upload_register_settings() {
    register_setting('esu_easy_svg_upload_settings', 'esu_enable_easy_svg_upload');
}
add_action('admin_init', 'esu_easy_svg_upload_register_settings');

// Filter the allowed Easy SVG Upload based on the option value
function esu_easy_svg_upload_type($mimes) {
    if (get_option('esu_enable_easy_svg_upload') === 'on') {
        $mimes['svg'] = 'image/svg+xml';
    }
    return $mimes;
}
add_filter('upload_mimes', 'esu_easy_svg_upload_type');

// Easy SVG Upload Plugin Option Links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'add_action_links');
function add_action_links($actions) {
   $mylinks = array(
      '<a href="' . admin_url('options-general.php?page=easy-svg-upload') . '">' . __('Settings', 'easy-svg-upload') . '</a>',
   );
   $actions = array_merge($actions, $mylinks);
   return $actions;
}

// Redirect to settings page once the plugin is activated
function esu_activation_redirect($plugin) {
    if ($plugin == plugin_basename(__FILE__)) {
        exit(wp_redirect(admin_url('options-general.php?page=easy-svg-upload')));
    }
}
add_action('activated_plugin', 'esu_activation_redirect');