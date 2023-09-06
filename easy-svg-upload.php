<?php
/**
 * @package           Easy_SVG_Upload
 * @version           1.0
 * @author            Delower Hossain
 * @copyright         2023 Delower Hossain
 * @license           GPL-2.0-or-later
 */

 /*
 * Plugin Name:       Easy SVG Upload
 * Plugin URI:        https://www.delowerhossain.com
 * Description:       Easy SVG Upload is your go-to solution for safely enabling SVG uploads in WordPress. This powerful plugin empowers you to seamlessly incorporate SVG files into your website, all while ensuring they are meticulously sanitized to thwart any potential SVG/XML vulnerabilities that could compromise your site's security. Additionally, Easy SVG Upload offers the convenience of previewing your uploaded SVGs directly in the media library, across all views. With Easy SVG Upload, you can confidently embrace the creative potential of SVG files within your WordPress site, all within a secure and user-friendly environment.
 * Version:           1.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Delower Hossain
 * Author URI:        https://www.delowerhossain.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       easy-svg-upload
 * Domain Path:       /languages
 */


//Avoiding Direct File Access

 if ( ! defined( 'ABSPATH' ) ) {
	die; // Exit if accessed directly
}
 
/**
 * Load plugin textdomain.
 */
function esu_load_textdomain() {
  load_plugin_textdomain( 'easy-svg-upload', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 
}
add_action( 'plugins_loaded', 'esu_load_textdomain' );


// Register the options page

function esu_easy_svg_upload_options_page() {
    add_options_page(
        __('Easy SVG Upload Settings', 'easy-svg-upload'),
        __('Easy SVG Upload','easy-svg-upload'),
        'manage_options',
        'easy-svg-upload',
        'esu_easy_svg_upload_options_page_content'
    );
}
add_action('admin_menu', 'esu_easy_svg_upload_options_page');

// Initialize plugin options
function esu_easy_svg_upload_initialize_options() {
    // Add an option to enable or disable the Easy SVG Upload
    add_option('esu_enable_easy_svg_upload', 'off');
}
add_action('admin_init', 'esu_easy_svg_upload_initialize_options');

// Options page content
function esu_easy_svg_upload_options_page_content() {
    ?>
    <div class="wrap">
        <h2>Easy SVG Upload Settings</h2>
        <form method="post" action="options.php">
            <?php settings_fields('esu_easy_svg_upload_settings'); ?>
            <?php do_settings_sections('esu-easy-svg-upload-settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Enable Easy SVG Upload</th>
                    <td>
                        <label for="esu_enable_easy_svg_upload">
                            <input type="checkbox" name="esu_enable_easy_svg_upload" id="esu_enable_easy_svg_upload" value="on" <?php checked('on', get_option('esu_enable_easy_svg_upload')); ?>>
                            Enable
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


//Easy SVG Upload Plugin Option Links

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'add_action_links' );

function add_action_links ( $actions ) {
   $mylinks = array(
      '<a href="' . admin_url( 'options-general.php?page=easy-svg-upload' ) . '">Settings</a>',
   );
   $actions = array_merge( $actions, $mylinks );
   return $actions;
}