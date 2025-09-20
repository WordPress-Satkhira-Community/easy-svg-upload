<?php
/**
 * Plugin Name: Easy SVG Upload
 * Plugin URI: https://wordpress.org/plugins/easy-svg-upload
 * Description: Safely enable SVG uploads by sanitizing files on upload. Optionally allow Authors+, with size limits and hardening.
 * Version: 1.2
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Delower Hossain
 * Author URI: https://www.delowerhossain.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: easy-svg-upload
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ESUP_VERSION', '1.2.2');
define('ESUP_PLUGIN_FILE', __FILE__);

/**
 * Load plugin textdomain.
 */
function esup_load_textdomain() {
    load_plugin_textdomain('easy-svg-upload', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'esup_load_textdomain');

/**
 * Load the SVG sanitizer library.
 * Supports:
 * 1) Composer (vendor/autoload.php)
 * 2) Manual bundle in lib/svg-sanitize/src (PSR-4)
 *
 * Manual bundle: Place the library's src folder at:
 *   easy-svg-upload/lib/svg-sanitize/src
 */
function esup_maybe_load_sanitizer() {
    // Try Composer autoloader.
    if (!class_exists('\enshrined\svgSanitize\Sanitizer')) {
        $vendor_autoload = plugin_dir_path(ESUP_PLUGIN_FILE) . 'vendor/autoload.php';
        if (file_exists($vendor_autoload)) {
            require_once $vendor_autoload;
        }
    }

    // Fallback: PSR-4 autoload from lib/svg-sanitize/src.
    if (!class_exists('\enshrined\svgSanitize\Sanitizer')) {
        $base = plugin_dir_path(ESUP_PLUGIN_FILE) . 'lib/svg-sanitize/src/';
        if (is_dir($base) && !defined('ESUP_SANITIZER_AUTOLOADER_REGISTERED')) {
            define('ESUP_SANITIZER_AUTOLOADER_REGISTERED', true);
            spl_autoload_register(function ($class) use ($base) {
                $prefix = 'enshrined\\svgSanitize\\';
                $len = strlen($prefix);
                if (strncmp($prefix, $class, $len) !== 0) {
                    return;
                }
                $relative = substr($class, $len);
                $file = $base . str_replace('\\', '/', $relative) . '.php';
                if (file_exists($file)) {
                    require $file;
                }
            });
        }
    }
}
add_action('plugins_loaded', 'esup_maybe_load_sanitizer', 5);

/**
 * Helper: check if sanitizer is available.
 */
function esup_sanitizer_available() {
    esup_maybe_load_sanitizer();
    return class_exists('\enshrined\svgSanitize\Sanitizer');
}

/**
 * Initialize options with safe defaults.
 * Options:
 * - esup_enable_easy_svg_upload: 'on'|'off' (default 'off')
 * - esup_allow_authors: 'on'|'off' (default 'off')
 * - esup_max_svg_kb: int (default 512)
 */
function esup_easy_svg_upload_initialize_options() {
    add_option('esup_enable_easy_svg_upload', 'off', '', false);
    add_option('esup_allow_authors', 'off', '', false);
    add_option('esup_max_svg_kb', 512, '', false);
}
add_action('admin_init', 'esup_easy_svg_upload_initialize_options');

/**
 * Sanitize helpers for settings.
 */
function esup_sanitize_on_off($value) {
    return (!empty($value) && $value === 'on') ? 'on' : 'off';
}
function esup_sanitize_max_svg_kb($value) {
    $v = (int) $value;
    if ($v < 10) {
        $v = 10;
    }
    if ($v > 8192) {
        $v = 8192;
    }
    return $v;
}

/**
 * Register settings (Settings API).
 */
function esup_easy_svg_upload_register_settings() {
    register_setting('esup_easy_svg_upload_settings', 'esup_enable_easy_svg_upload', array(
        'type'              => 'string',
        'sanitize_callback' => 'esup_sanitize_on_off',
        'show_in_rest'      => false,
    ));
    register_setting('esup_easy_svg_upload_settings', 'esup_allow_authors', array(
        'type'              => 'string',
        'sanitize_callback' => 'esup_sanitize_on_off',
        'show_in_rest'      => false,
    ));
    register_setting('esup_easy_svg_upload_settings', 'esup_max_svg_kb', array(
        'type'              => 'integer',
        'sanitize_callback' => 'esup_sanitize_max_svg_kb',
        'show_in_rest'      => false,
    ));
}
add_action('admin_init', 'esup_easy_svg_upload_register_settings');

/**
 * Capability: who may upload SVGs effectively (right now)?
 * Returns true only if enabled, sanitizer loaded, and user has required caps.
 */
function esup_current_user_can_upload_svg() {
    if (get_option('esup_enable_easy_svg_upload') !== 'on') {
        return false;
    }

    if (!esup_sanitizer_available()) {
        return false;
    }

    $allow_authors = (get_option('esup_allow_authors') === 'on');
    if ($allow_authors) {
        return current_user_can('upload_files');
    }

    // Safe default: Admins only.
    return current_user_can('unfiltered_html') || current_user_can('manage_options');
}

/**
 * Allow SVG mime only when permitted for current user.
 */
function esup_easy_svg_upload_type($mimes) {
    $mimes = is_array($mimes) ? $mimes : array();

    if (esup_current_user_can_upload_svg()) {
        $mimes['svg'] = 'image/svg+xml';
    } else {
        if (isset($mimes['svg'])) {
            unset($mimes['svg']);
        }
    }
    return $mimes;
}
add_filter('upload_mimes', 'esup_easy_svg_upload_type');

/**
 * Help WP identify SVGs precisely.
 */
function esup_check_filetype_and_ext($data, $file, $filename, $mimes, $real_mime = null) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext === 'svg') {
        $data['ext']  = 'svg';
        $data['type'] = 'image/svg+xml';
        $data['proper_filename'] = wp_basename($filename);
    }
    return $data;
}
add_filter('wp_check_filetype_and_ext', 'esup_check_filetype_and_ext', 10, 5);

/**
 * Prefilter: block disallowed users, enforce size, randomize filename for SVGs.
 */
function esup_upload_prefilter($file) {
    $name = isset($file['name']) ? $file['name'] : '';
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if ($ext === 'svg') {
        if (!esup_current_user_can_upload_svg()) {
            $file['error'] = __('You are not allowed to upload SVG files.', 'easy-svg-upload');
            return $file;
        }

        $max_kb = (int) get_option('esup_max_svg_kb', 512);
        $max    = (int) apply_filters('esup_svg_max_size', max(10, $max_kb) * 1024);

        if (!empty($file['size']) && (int) $file['size'] > $max) {
            $file['error'] = sprintf(
                /* translators: %d: size in KB */
                __('SVG file is too large. Max allowed: %d KB.', 'easy-svg-upload'),
                (int) ($max / 1024)
            );
            return $file;
        }

        // Randomize filename; WP will still ensure uniqueness.
        $file['name'] = wp_generate_uuid4() . '.svg';
    }

    return $file;
}
add_filter('wp_handle_upload_prefilter', 'esup_upload_prefilter');

/**
 * After upload: sanitize SVG contents or reject upload.
 */
function esup_handle_upload($upload) {
    if (!is_array($upload)) {
        return $upload;
    }
    if (empty($upload['type']) || empty($upload['file'])) {
        return $upload;
    }

    if ($upload['type'] === 'image/svg+xml') {
        $result = esup_sanitize_svg_file($upload['file']);
        if (is_wp_error($result)) {
            @unlink($upload['file']);
            return array('error' => $result->get_error_message());
        }
    }

    return $upload;
}
add_filter('wp_handle_upload', 'esup_handle_upload');

/**
 * Core sanitizer using enshrined/svg-sanitize.
 */
function esup_sanitize_svg_file($path) {
    if (!file_exists($path)) {
        return new WP_Error('esup_missing', __('Uploaded SVG not found on disk.', 'easy-svg-upload'));
    }

    $svg = file_get_contents($path);
    if ($svg === false) {
        return new WP_Error('esup_read', __('Unable to read the uploaded SVG.', 'easy-svg-upload'));
    }

    // Must have an <svg> root element.
    if (!preg_match('/<\s*svg\b[^>]*>/i', $svg)) {
        return new WP_Error('esup_invalid', __('The uploaded file is not a valid SVG.', 'easy-svg-upload'));
    }

    // Ensure sanitizer exists.
    if (!esup_sanitizer_available()) {
        return new WP_Error(
            'esup_sanitizer_missing',
            __('SVG sanitizer library is missing; SVG uploads are blocked until installed.', 'easy-svg-upload')
        );
    }

    try {
        $sanitizer = new \enshrined\svgSanitize\Sanitizer();

        // Sanitize the SVG.
        $clean = $sanitizer->sanitize($svg);
        if ($clean === false || trim($clean) === '') {
            return new WP_Error('esup_sanitize', __('SVG failed sanitization.', 'easy-svg-upload'));
        }

        // Extra defense: block dangerous constructs if anything slipped through.
        $forbiddenTags = array('script', 'foreignObject', 'iframe', 'object', 'embed');
        foreach ($forbiddenTags as $tag) {
            if (preg_match('/<\s*' . preg_quote($tag, '/') . '\b/i', $clean)) {
                return new WP_Error(
                    'esup_forbidden',
                    sprintf(__('Forbidden element <%s> found in SVG.', 'easy-svg-upload'), $tag)
                );
            }
        }
        if (preg_match('/on\w+\s*=\s*["\']|xlink:href\s*=\s*["\']\s*javascript:/i', $clean)) {
            return new WP_Error(
                'esup_event_handlers',
                __('Inline event handlers or javascript: URLs are not allowed in SVG.', 'easy-svg-upload')
            );
        }

        if (file_put_contents($path, $clean) === false) {
            return new WP_Error('esup_write', __('Unable to write sanitized SVG to disk.', 'easy-svg-upload'));
        }

        return true;
    } catch (\Throwable $e) {
        return new WP_Error('esup_exception', __('SVG sanitization error.', 'easy-svg-upload'));
    }
}

/**
 * Settings page UI.
 */
function esup_easy_svg_upload_options_page() {
    add_options_page(
        __('Easy SVG Upload Settings', 'easy-svg-upload'),
        __('Easy SVG Upload', 'easy-svg-upload'),
        'manage_options',
        'easy-svg-upload',
        'esup_easy_svg_upload_options_page_content'
    );
}
add_action('admin_menu', 'esup_easy_svg_upload_options_page');

/**
 * Options page content.
 */
function esup_easy_svg_upload_options_page_content() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'easy-svg-upload'));
    }
    $enabled       = (get_option('esup_enable_easy_svg_upload') === 'on');
    $allow_authors = (get_option('esup_allow_authors') === 'on');
    $max_kb        = (int) get_option('esup_max_svg_kb', 512);
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Easy SVG Upload Settings', 'easy-svg-upload'); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('esup_easy_svg_upload_settings'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable Easy SVG Upload', 'easy-svg-upload'); ?></th>
                    <td>
                        <label for="esup_enable_easy_svg_upload">
                            <input type="checkbox" name="esup_enable_easy_svg_upload" id="esup_enable_easy_svg_upload" value="on" <?php checked(true, $enabled); ?> />
                            <?php esc_html_e('Enable (SVG files are sanitized on the server).', 'easy-svg-upload'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Allow Authors and above', 'easy-svg-upload'); ?></th>
                    <td>
                        <label for="esup_allow_authors">
                            <input type="checkbox" name="esup_allow_authors" id="esup_allow_authors" value="on" <?php checked(true, $allow_authors); ?> />
                            <?php esc_html_e('By default, only Administrators can upload SVG. Enable this to allow Authors and above.', 'easy-svg-upload'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Max SVG size (KB)', 'easy-svg-upload'); ?></th>
                    <td>
                        <input type="number" min="10" max="8192" step="10" name="esup_max_svg_kb" id="esup_max_svg_kb" value="<?php echo esc_attr($max_kb); ?>" />
                        <p class="description"><?php esc_html_e('Limit SVG upload size for safety. Default: 512 KB.', 'easy-svg-upload'); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>

        <hr />
        <h2><?php echo esc_html__('Status', 'easy-svg-upload'); ?></h2>
        <?php esup_maybe_load_sanitizer(); ?>
        <ul>
            <li>
                <?php
                echo $enabled
                    ? '✅ ' . esc_html__('SVG uploads: Enabled', 'easy-svg-upload')
                    : '⛔ ' . esc_html__('SVG uploads: Disabled', 'easy-svg-upload');
                ?>
            </li>
            <li>
                <?php
                echo $allow_authors
                    ? '👥 ' . esc_html__('Allowed roles: Authors and above', 'easy-svg-upload')
                    : '🛡️ ' . esc_html__('Allowed roles: Administrators only', 'easy-svg-upload');
                ?>
            </li>
            <li>
                <?php
                echo '📦 ' . sprintf(esc_html__('Max SVG size: %d KB', 'easy-svg-upload'), $max_kb);
                ?>
            </li>
            <li>
                <?php
                echo esup_sanitizer_available()
                    ? '✅ ' . esc_html__('Sanitizer: Loaded', 'easy-svg-upload')
                    : '⚠️ ' . esc_html__('Sanitizer: Missing (uploads are blocked until installed)', 'easy-svg-upload');
                ?>
            </li>
            <li>
                <?php
                echo esup_current_user_can_upload_svg()
                    ? '✅ ' . esc_html__('Current user can upload SVG: Yes', 'easy-svg-upload')
                    : 'ℹ️ ' . esc_html__('Current user can upload SVG: No', 'easy-svg-upload');
                ?>
            </li>
        </ul>
    </div>
    <?php
}

/**
 * Admin notice if sanitizer missing while enabled.
 */
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (get_option('esup_enable_easy_svg_upload') !== 'on') {
        return;
    }
    if (esup_sanitizer_available()) {
        return;
    }
    if (function_exists('get_current_screen')) {
        $screen = get_current_screen();
        if ($screen && !in_array($screen->id, array('settings_page_easy-svg-upload', 'plugins'), true)) {
            return;
        }
    }
    echo '<div class="notice notice-error"><p><strong>Easy SVG Upload:</strong> '
        . esc_html__('Sanitizer library is missing, so SVG uploads are currently blocked. You can install it via Composer or bundle manually. See plugin readme for details.', 'easy-svg-upload')
        . '</p></div>';
});

/**
 * Plugin action links.
 */
function esup_action_links($actions) {
    $mylinks = array(
        '<a href="' . esc_url(admin_url('options-general.php?page=easy-svg-upload')) . '">' . esc_html__('Settings', 'easy-svg-upload') . '</a>',
    );
    $actions = array_merge($actions, $mylinks);
    return $actions;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'esup_action_links');

/**
 * Redirect to settings page once activated.
 */
function esup_activation_redirect($plugin) {
    if ($plugin === plugin_basename(__FILE__)) {
        wp_safe_redirect(admin_url('options-general.php?page=easy-svg-upload'));
        exit;
    }
}
add_action('activated_plugin', 'esup_activation_redirect');