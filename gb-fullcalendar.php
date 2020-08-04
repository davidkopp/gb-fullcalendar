<?php
/**
 * Plugin Name:     GB FullCalendar
 * Plugin URI:      https://github.com/oberhauser-dev/gb-fullcalendar/
 * Description:     GB FullCalendar is a Gutenberg block for displaying events.
 * Version:         0.1.0
 * Author:          August Oberhauser
 * Author URI:      https://www.oberhauser.dev/
 * License:         GPL3+
 * License URI:     https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:     create-block
 *
 * @package         create-block
 */

include_once(ABSPATH . 'wp-admin/includes/plugin.php'); // load method for front-end
require_once 'php/gb-fc.php';
include_once 'php/gb-fc-ajax.php';

if (!is_plugin_active('wp-fullcalendar/wp-fullcalendar.php')) {
    // Define WPFC-Version to enable EM-wpfc API (ajax);
    if (!defined('WPFC_VERSION'))
        define('WPFC_VERSION', '2.1.0');
}

/**
 * Registers all block assets so that they can be enqueued through the block editor
 * in the corresponding context.
 *
 * @see https://developer.wordpress.org/block-editor/tutorials/block-tutorial/applying-styles-with-stylesheets/
 */
function create_block_gb_fullcalendar_block_init()
{
    $dir = dirname(__FILE__);

    $script_asset_path = "$dir/build/index.asset.php";
    if (!file_exists($script_asset_path)) {
        throw new Error(
            'You need to run `npm start` or `npm run build` for the "create-block/gb-fullcalendar" block first.'
        );
    }
    $index_js = 'build/index.js';
    $script_asset = require($script_asset_path);
    wp_register_script(
        'create-block-gb-fullcalendar-block-editor',
        plugins_url($index_js, __FILE__),
        $script_asset['dependencies'],
        $script_asset['version']
    );

    // TODO may only load, if block is present, if possible.
    $client_js = 'build/client.js';
    wp_register_script(
        'create-block-gb-fullcalendar-block',
        plugins_url($client_js, __FILE__),
        $script_asset['dependencies'],
        $script_asset['version']
    );

    // TODO only load, if block is registered and block is present.
    // Unfortunately can only register one style at a time.
    $style_css = 'build/client.css';
    wp_enqueue_style(
        'create-block-gb-fullcalendar-block-client',
        plugins_url($style_css, __FILE__),
        array(),
        filemtime("$dir/$style_css")
    );

    $editor_css = 'editor.css';
    wp_register_style(
        'create-block-gb-fullcalendar-block-editor',
        plugins_url($editor_css, __FILE__),
        array(),
        filemtime("$dir/$editor_css")
    );

    $style_css = 'style.css';
    wp_register_style(
        'create-block-gb-fullcalendar-block',
        plugins_url($style_css, __FILE__),
        array(),
        filemtime("$dir/$style_css")
    );

    register_block_type('create-block/gb-fullcalendar', array(
        'editor_script' => 'create-block-gb-fullcalendar-block-editor',
        'script' => 'create-block-gb-fullcalendar-block',
        'editor_style' => 'create-block-gb-fullcalendar-block-editor',
        'style' => 'create-block-gb-fullcalendar-block',
    ));

    if (is_admin()) {
        // Call always as admin, otherwise block cannot be added dynamically.
        localize_script();
        include_once('php/gb-fc-admin.php');
    } else {
        // Add shortcode
        add_shortcode('fullcalendar', 'calendar_via_shortcode');
    }

    /**
     * Create ajax endpoints.
     * https://codex.wordpress.org/Plugin_API/Action_Reference/wp_ajax_(action)
     */
    // TODO some time rename "WP_FullCalendar" to "gbfc_events"
    //overrides the ajax calls for event data
    if (defined('DOING_AJAX') && DOING_AJAX && !empty($_REQUEST['type'])) { //only needed during ajax requests anyway
        if ($_REQUEST['type'] === EM_POST_TYPE_EVENT) {
            add_filter('wpfc_fullcalendar_args', ['GbFcAjax', 'filter_ajax_em_event_args']);
        } else {
            add_action('wp_ajax_WP_FullCalendar', ['GbFcAjax', 'ajax_events']);
            add_action('wp_ajax_nopriv_WP_FullCalendar', ['GbFcAjax', 'ajax_events']);
        }
    }

    add_action('wp_ajax_gbfc_tooltip_content', ['GbFcAjax', 'ajax_tooltip_content']);
    add_action('wp_ajax_nopriv_gbfc_tooltip_content', ['GbFcAjax', 'ajax_tooltip_content']);
}

add_action('init', 'create_block_gb_fullcalendar_block_init');

/**
 * Only localize js variables if block is present in front-end.
 */
function create_block_gb_fullcalendar_block_enqueue_script()
{
    // Always enqueue script, as shortcode need localized script, too.
    // TODO may fix that only load, when needed.
//    if (has_block('create-block/gb-fullcalendar')) {
    localize_script();
//    }
}

add_action('wp_enqueue_scripts', 'create_block_gb_fullcalendar_block_enqueue_script');

// action links (e.g. Settings)
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'gbfc_settings_link', 10, 1);
function gbfc_settings_link($links)
{
    $new_links = array(); //put settings first
    $new_links[] = '<a href="' . admin_url('options-general.php?page=gb-fullcalendar') . '">' . __('Settings', 'gb-fullcalendar') . '</a>';
    return array_merge($new_links, $links);
}

/**
 * Localize javascript variables for gb-fullcalendar.
 */
function localize_script()
{
    wp_localize_script(
        'create-block-gb-fullcalendar-block',
        'GbFcGlobal', // Array containing dynamic data for a JS Global.
        [
            'pluginDirPath' => plugin_dir_path(__DIR__),
            'pluginDirUrl' => plugin_dir_url(__DIR__),
            // Add more data here that you want to access from `cgbGlobal` object.
            'fc' => getFullCalendarArgs(),
            'fcExtra' => getFullCalendarExtraArgs(),
        ]
    );
}
