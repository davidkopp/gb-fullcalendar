<?php
/**
 * Plugin Name:     GB FullCalendar
 * Plugin URI:      https://github.com/oberhauser-dev/gb-fullcalendar/
 * Description:     GB FullCalendar is a Gutenberg block for displaying events.
 * Version:         0.2.1
 * Requires at least: 5.3.2
 * Tested up to:    5.7
 * Requires PHP:    7.0.0
 * Author:          August Oberhauser
 * Author URI:      https://www.oberhauser.dev/
 * License:         GPL3+
 * License URI:     https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:     gb-fullcalendar
 *
 * @package         oberhauser-dev
 */

include_once(ABSPATH . 'wp-admin/includes/plugin.php'); // load method for front-end
require_once 'php/gb-fc.php';
include_once 'php/gb-fc-ajax.php';
include_once 'php/gb-fc-actions.php';

if (!is_plugin_active('wp-fullcalendar/wp-fullcalendar.php')) {
    // Define WPFC-Version to enable EM-wpfc API (ajax);
    if (!defined('WPFC_VERSION'))
        define('WPFC_VERSION', '2.1.0');
}

/**
 * Registers block assets so that they can be enqueued through the block editor
 * in the corresponding context.
 *
 * @see https://developer.wordpress.org/block-editor/tutorials/block-tutorial/applying-styles-with-stylesheets/
 */
function create_block_gb_fullcalendar_block_init()
{
    $dir = dirname(__FILE__);

    // Admin dependencies are defined in index.asset.php
    $admin_script_asset_path = "$dir/build/index.asset.php";
    if (!file_exists($admin_script_asset_path)) {
        throw new Error(
            'You need to run `npm start` or `npm run build` for the "oberhauser-dev/gb-fullcalendar" block first.'
        );
    }

    // Client dependencies are defined in client.asset.php
    $client_script_asset_path = "$dir/build/client.asset.php";
    if (!file_exists($client_script_asset_path)) {
        throw new Error(
            'You need to run `npm start` or `npm run build` for the client script in the "oberhauser-dev/gb-fullcalendar" block first.'
        );
    }

    $admin_script_asset = require($admin_script_asset_path);
    $client_script_asset = require($client_script_asset_path);

    // Register admin (editor) scripts and styles; client dependencies are loaded in enqueue_assets_if_shortcode_present
    if (is_admin()) {
    $index_js = 'build/index.js';
    wp_register_script(
        'gb-fullcalendar-block-editor',
        plugins_url($index_js, __FILE__),
            $admin_script_asset['dependencies'],
            $admin_script_asset['version']
    );

    $editor_css = 'build/index.css';
    wp_register_style(
        'gb-fullcalendar-block-editor',
        plugins_url($editor_css, __FILE__),
        array(),
        filemtime("$dir/$editor_css")
    );

        register_block_type('oberhauser-dev/gb-fullcalendar', array(
            'editor_script' => 'gb-fullcalendar-block-editor',
            'editor_style' => 'gb-fullcalendar-block-editor',
        ));

        localize_script();
        include_once('php/gb-fc-admin.php');
    }

    register_block_type('oberhauser-dev/gb-fullcalendar', array(
        'script' => 'gb-fullcalendar-block-client',
        'style' => 'gb-fullcalendar-block-client',
    ));

    // Add shortcode for frontend usage
    if (!is_admin()) {
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
 * Only load client functionalities if shortcode is present in front-end.
 */
function enqueue_assets_if_shortcode_present() {
    global $post;

    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'fullcalendar')) {
        $dir = dirname(__FILE__);
        $client_script_asset_path = "$dir/build/client.asset.php";
        
        if (!file_exists($client_script_asset_path)) {
            throw new Error('Client asset file not found.');
        }

        $client_script_asset = require($client_script_asset_path);

        // Register and enqueue client script
        $client_js = 'build/client.js';
        wp_enqueue_script(
            'gb-fullcalendar-block-client',
            plugins_url($client_js, __FILE__),
            $client_script_asset['dependencies'],
            $client_script_asset['version'],
            true
        );

        // Register and enqueue client style
        $client_css = 'build/client.css';
        wp_enqueue_style(
            'gb-fullcalendar-block-client',
            plugins_url($client_css, __FILE__),
            array(),
            filemtime("$dir/$client_css")
        );

        // Localize javascript variables
        localize_script();
    }
}

add_action('wp_enqueue_scripts', 'enqueue_assets_if_shortcode_present');

// action links (e.g. Settings)
function gbfc_settings_link($links)
{
    array_unshift($links, '<a href="' . admin_url('options-general.php?page=gb-fullcalendar') . '">' . __('Settings', 'gb-fullcalendar') . '</a>');

    // Add remove wipe data option.
    $plugin_data = get_plugin_data(__FILE__);
    $url = wp_nonce_url(admin_url('admin-post.php?action=gbfc_uninstall'), 'gbfc_uninstall');
    $links[] = '<span class="delete"><a href="' . $url
        . '" onclick="return confirm(\'Are you sure you want to uninstall ' . $plugin_data['Name']
        . '? All preferences will be removed!\')">Uninstall</a></span>';
    return $links;
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'gbfc_settings_link', 10, 1);

/**
 * Admin post action hook, without the need of specifying own endpoint / handler.
 * https://codex.wordpress.org/Plugin_API/Action_Reference/admin_post_(action)
 */
function gbfc_admin_uninstall()
{
    check_admin_referer('gbfc_uninstall');
    $plugins = [plugin_basename(__FILE__)];
    deactivate_plugins($plugins);
    GbFcActions::deleteOptions();
    delete_plugins($plugins);
    wp_redirect($_SERVER['HTTP_REFERER']);
    exit();
}

add_action('admin_post_gbfc_uninstall', 'gbfc_admin_uninstall');

function gbfc_admin_reset()
{
    check_admin_referer('gbfc_reset');
    GbFcActions::resetOptions();
    wp_redirect($_SERVER['HTTP_REFERER']);
    exit();
}

add_action('admin_post_gbfc_reset', 'gbfc_admin_reset');

function gbfc_admin_resetToWpFc()
{
    check_admin_referer('gbfc_resetToWpFc');
    GbFcActions::resetToWpFcOptions();
    wp_redirect($_SERVER['HTTP_REFERER']);
    exit();
}

add_action('admin_post_gbfc_resetToWpFc', 'gbfc_admin_resetToWpFc');

/**
 * Localize javascript variables for gb-fullcalendar.
 */
function localize_script()
{
    wp_localize_script(
        'gb-fullcalendar-block-client',
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
