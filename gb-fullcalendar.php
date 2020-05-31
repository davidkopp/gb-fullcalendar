<?php
/**
 * Plugin Name:     GB FullCalendar
 * Description:     Example block written with ESNext standard and JSX support – build step required.
 * Version:         0.1.0
 * Author:          The WordPress Contributors
 * License:         GPL-2.0-or-later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:     create-block
 *
 * @package         create-block
 */

include_once( ABSPATH . 'wp-admin/includes/plugin.php' ); // load method for front-end
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
        include('gb-fc-admin.php');
    }
}

add_action('init', 'create_block_gb_fullcalendar_block_init');

/**
 * Only localize js variables if block is present in front-end.
 */
function create_block_gb_fullcalendar_block_enqueue_script() {
    if (has_block('create-block/gb-fullcalendar')) {
        localize_script();
    }
}
add_action( 'wp_enqueue_scripts', 'create_block_gb_fullcalendar_block_enqueue_script' );

/**
 * Localize javascript variables for gb-fullcalendar.
 */
function localize_script() {
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

/**
 * Set most options of FullCalendar.
 * See https://fullcalendar.io/docs.
 *
 * @return array the FC options to be localized.
 */
function getFullCalendarArgs()
{
    // Header Toolbar
    $headerToolbar = new stdClass();
    $headerToolbar->left = 'prevYear,prev,today,next,nextYear';
    $headerToolbar->center = 'title';
    $headerToolbar->right = implode(',', get_option('gbfc_available_views', array('dayGridMonth', 'timeGridWeek', 'timeGridDay', 'listCustom')));
    $headerToolbar = apply_filters('gbfc_calendar_header_vars', $headerToolbar);

    // Custom views
    $gbfc_available_views_duration = get_option('gbfc_available_views_duration', array('dayGridCustom' => 7, 'timeGridCustom' => 1, 'listCustom' => 30));
    $viewsTypeMap = [
        'dayGridCustom' => 'dayGrid',
        'timeGridCustom' => 'timeGrid',
        'listCustom' => 'list',
    ];
    $views = new stdClass();
    foreach ($gbfc_available_views_duration as $customViewKey => $duration) {
        $view = new stdClass();
        $view->type = $viewsTypeMap[$customViewKey];
        $view->duration = new stdClass();
        $view->duration->days = intval($duration);
        $views->$customViewKey = $view;
    }

    return [
        'themeSystem' => get_option('gbfc_themeSystem', 'standard'), // else: 'bootstrap'
        'firstDay' => get_option('start_of_week'),
        'editable' => 'false',
        'initialView' => get_option('gbfc_defaultView', 'dayGridMonth'), // Can be overwritten in shortcode
        'weekends' => get_option('gbfc_weekends', true) ? 'true' : 'false',
        'headerToolbar' => $headerToolbar,
        'locale' => strtolower(str_replace('_', '-', get_locale())),
        'eventDisplay' => 'block', // See https://fullcalendar.io/docs/v5/eventDisplay
        // See https://fullcalendar.io/docs/v5/event-popover
        'dayMaxEventRows' => true,
        'dayMaxEvents' => true,
        'views' => $views,

        // eventBackgroundColor: 'white',
        // eventColor: 'white',
        // eventTextColor: 'black',

//        'gbfc_theme' => get_option('gbfc_theme_css') ? true : false,
//        'gbfc_theme_system' => get_option('gbfc_theme_system'),
//        'gbfc_limit' => get_option('gbfc_limit', 3),
//        'gbfc_limit_txt' => get_option('gbfc_limit_txt', 'more ...'),
        //'google_calendar_api_key' => get_option('gbfc_google_calendar_api_key', ''),
        //'google_calendar_ids' => preg_split('/\s+/', get_option('gbfc_google_calendar_ids', '')),
//        'timeFormat' => get_option('gbfc_timeFormat', 'h(:mm)t'),
//        'gbfc_qtips' => get_option('gbfc_qtips', true) == true,
//        'gbfc_dialog' => get_option('gbfc_dialog', true) == true,
    ];
}

/**
 * Set custom FullCalendar options. Needs a counterpart in @see "src/client.js".
 *
 * @return array the custom FC options to be localized.
 */
function getFullCalendarExtraArgs()
{
    $schema = is_ssl() ? 'https' : 'http';

    $args = []; // TODO fetch from settings
    $post_type = get_option('gbfc_default_type','event');
    //figure out what taxonomies to show
    $gbfc_post_taxonomies = get_option('gbfc_post_taxonomies');
    $search_taxonomies = array_keys($gbfc_post_taxonomies[$post_type]) ?? array();
    if (!empty($args['taxonomies'])) {
        //we accept taxonomies in arguments
        $search_taxonomies = explode(',', $args['taxonomies']);
        array_walk($search_taxonomies, 'trim');
        unset($args['taxonomies']);
    }
    //go through each post type taxonomy and display if told to
    $taxonomyNodes = [];
    foreach (get_object_taxonomies($post_type) as $taxonomy_name) {
        $taxonomy = get_taxonomy($taxonomy_name);
        if (count(get_terms($taxonomy_name, array('hide_empty' => 1))) > 0 && in_array($taxonomy_name, $search_taxonomies)) {
            $isCategory = $taxonomy_name === EM_TAXONOMY_CATEGORY;
            // Default value
            $default_value = $args[$taxonomy_name] ?? 0;
            if ($isCategory && !empty($args['category'])) {
                $default_value = $args['category'];
            }
            if (!is_numeric($default_value)) {
                $default_value = get_term_by('slug', $default_value, $taxonomy_name)->term_id;
            }

            // See: https://developer.wordpress.org/reference/classes/wp_term_query/__construct/
            $taxonomy_args = array(
                'hide_empty' => true,
                'hierarchical' => true,
                'taxonomy' => $taxonomy_name,
            );
            $taxonomy_args = apply_filters('gb_fc_taxonomy_args', $taxonomy_args, $taxonomy);
            $terms = get_terms($taxonomy_args);
            if(!$taxonomy_args['hide_empty'] || !empty($terms)) {
                // Add em category colors
                if($isCategory) {
                    foreach ($terms as $term) {
                        $term->color = get_em_term_color($term->term_id);
                    }
                }

                // Custom display object for client
                $display_args = array_merge($taxonomy_args, array(
                    'echo' => true,
                    'class' => 'gbfc-taxonomy ' . $taxonomy_name,
                    'selected' => $default_value,
                    'show_option_all' => $taxonomy->labels->all_items,
                    'items' => $terms,
                ));
                $display_args = apply_filters('gb_fc_taxonomy_display_args', $display_args, $taxonomy);
                $taxonomyNodes[] = $display_args;
            }
        }
    }

    return [
        'ajaxUrl' => admin_url('admin-ajax.php', $schema),
        // TODO The fetch interval may can be removed!
        'month' => intval(date('m', current_time('timestamp')) - 1),
        'year' => intval(date('Y', current_time('timestamp'))),
        'taxonomyNodes' => $taxonomyNodes,
    ];
}

function get_em_term_color($term_id)
{
    // @see: plugins/events-manager/em-wpfc.php#start_el
    global $wpdb;
    if (defined('EM_META_TABLE')) {
        $color = $wpdb->get_var('SELECT meta_value FROM ' . EM_META_TABLE . " WHERE object_id='{$term_id}' AND meta_key='category-bgcolor' LIMIT 1");
    }
    return (!empty($color)) ? $color : '#a8d144';
}