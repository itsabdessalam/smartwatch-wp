<?php

if (!defined('ABSPATH')) {
    // Exit if accessed directly.
    exit;
}

define('NETLIFY_BUILD_HOOK', getenv('NETLIFY_BUILD_HOOK'));

/**
 * Check whether user is authenticated or not
 */
function get_user_credentials($authorization)
{

    $base64decoded = base64_decode($authorization);
    $user          = explode(':', $base64decoded);
    $username      = $user[0];
    $password      = $user[1];

    if (!empty($username) && !empty($password)) {
        return [
            "username" => $username,
            "password" => $password,
        ];
    }

    return [];
}

/**
 * Check whether user is authenticated or not
 */
function is_authenticated($user)
{
    $username = $user["username"];
    $password = $user["password"];

    return !is_wp_error(wp_authenticate_username_password(null, $username, $password));
}

/**
 * Enqueue scripts and styles
 */
function load_scripts()
{
    wp_enqueue_style('smartwatch-style', get_stylesheet_uri());
}

add_action('wp_enqueue_scripts', 'load_scripts');

/**
 * Remove wordpress version and tag generator
 */
function remove_wordpress_version()
{
    return '';
}

add_filter('the_generator', 'remove_wordpress_version');
remove_action('wp_head', 'wp_generator');

/**
 * Remove wordpress core updates
 */
function remove_core_updates()
{
    global $wp_version;
    return (object) array(
        'last_checked'    => time(),
        'version_checked' => $wp_version,
    );
}

add_filter('pre_site_transient_update_core', 'remove_core_updates');
add_filter('pre_site_transient_update_plugins', 'remove_core_updates');
add_filter('pre_site_transient_update_themes', 'remove_core_updates');

/**
 * Remove wordpress version from files
 */
function remove_version_from_files($src)
{
    if (strpos($src, 'ver=' . get_bloginfo('version'))) {
        $src = remove_query_arg('ver', $src);
    }
    return $src;
}

add_filter('style_loader_src', 'remove_version_from_files');
add_filter('script_loader_src', 'remove_version_from_files');
add_filter('use_block_editor_for_post', '__return_false', 10);

/**
 * Remove image sizes from image on edior
 */
function remove_size_attributes($html)
{
    $html = preg_replace('/(width|height)="\d*"\s/', '', $html);
    return $html;
}

add_filter('post_thumbnail_html', 'remove_size_attributes', 10);
add_filter('image_send_to_editor', 'remove_size_attributes', 10);

/**
 * Remove default image sizes
 *
 */
function prefix_remove_default_images($sizes)
{
    unset($sizes['small']); // 150px
    unset($sizes['medium']); // 300px
    unset($sizes['medium_large']); // 768px
    unset($sizes['large']); // 1024px
    return $sizes;
}

add_filter('intermediate_image_sizes_advanced', 'prefix_remove_default_images');
add_filter('max_srcset_image_width', create_function('', 'return 1;'));

/**
 * Remove default image css classes
 */
function image_tag_class($class, $id, $align, $size)
{
    return 'align' . $align . ' size-' . $size;
}
add_filter('get_image_tag_class', 'image_tag_class', 0, 4);

/**
 * Register custom post types
 */
function register_custom_post_types()
{
    register_post_type('product', array(
        'labels'              => array(
            'name'          => __('Products'),
            'singular_name' => __('Product'),
        ),
        'public'              => true,
        'has_archive'         => true,
        'rest_base'           => 'products',
        'graphql_single_name' => 'Product',
        'graphql_plural_name' => 'Products',
        'public'              => true,
        'show_in_rest'        => true,
        'show_in_graphql'     => true,
        'supports'            => array(
            'title',
        ),
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_nav_menus'   => true,
        'show_in_admin_bar'   => true,
        'hierarchical'        => true,
        'menu_icon'           => 'dashicons-products',
    ));
    register_post_type('brand', array(
        'labels'              => array(
            'name'          => __('Brands'),
            'singular_name' => __('Brand'),
        ),
        'public'              => true,
        'has_archive'         => true,
        'rest_base'           => 'brands',
        'graphql_single_name' => 'Brand',
        'graphql_plural_name' => 'Brands',
        'public'              => true,
        'show_in_rest'        => true,
        'show_in_graphql'     => true,
        'supports'            => array(
            'title',
        ),
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_nav_menus'   => true,
        'show_in_admin_bar'   => true,
        'hierarchical'        => true,
        'menu_icon'           => 'dashicons-admin-page',
    ));
}

add_action('init', 'register_custom_post_types');
add_filter('acf/format_value', function ($value) {
    if ($value instanceof WP_Post) {
        return ['post_type' => $value->post_type, 'id' => $value->ID];
    }

    return $value;
}
    , 100);

/**
 * Disable remember me on login form
 */
function remove_remember_me()
{
    unset($_POST['rememberme']);
}

function start_login_form_cache()
{
    ob_start('process_login_form_cache');
}

function process_login_form_cache($content)
{
    $find = array(
        '/<p class="forgetmenot">(.*)<\/p>/',
        '/<p id="nav">(.*)<\/p>/',
        '/<p id="backtoblog">(.*)<\/p>/',
    );
    return preg_replace($find, '', $content);
}

add_action('login_form', 'start_login_form_cache');
add_action('login_head', 'remove_remember_me');

add_filter('show_password_fields', false);
add_filter('allow_password_reset', false);

/**
 * Override default Rest API prefix
 */
function rest_url_prefix()
{
    return 'api';
}

add_filter('rest_url_prefix', 'rest_url_prefix');

/**
 * Get all posts
 */
function get_all_posts()
{
    $posts = get_posts(array(
        'post_type'      => 'post',
        'posts_per_page' => 100,
    ));

    if (empty($posts)) {
        return null;
    }

    foreach ($posts as $key => $post) {
        $post->custom_fields = get_fields($post->ID);
        $post->categories    = get_categories($post->ID);
    }

    return $posts;
}

/**
 * Get post by ID
 */
function get_post_by_id($data)
{
    $post = get_post($data['id']);

    if (empty($post)) {
        return null;
    }

    $post->custom_fields = get_fields($data['id']);
    $post->categories    = get_categories($data['id']);

    return $post;
}

/**
 * Get all products
 */
function get_all_products()
{
    $products = get_posts(array(
        'post_type'      => 'product',
        'posts_per_page' => 100,
    ));

    if (empty($products)) {
        return null;
    }

    foreach ($products as $key => $product) {
        $product->custom_fields             = get_fields($product->ID);
        $product->custom_fields['brand']    = get_post(get_field('brand', $product->ID)['id']);
        $product->custom_fields['amount']   = get_field('price', $product->ID);
        $product->custom_fields['currency'] = 'EUR';
    }

    return $products;
}

/**
 * Get product by ID
 */
function get_product_by_id($data)
{
    $product = get_post($data['id']);

    if (empty($product)) {
        return null;
    }

    $product->custom_fields             = get_fields($data['id']);
    $product->custom_fields['brand']    = get_post(get_field('brand', $data['id'])['id']);
    $product->custom_fields['amount']   = get_field('price', $data['id']);
    $product->custom_fields['currency'] = 'EUR';

    return $product;
}

/**
 * Update stock quantity for each received product
 */
function update_stock(WP_REST_Request $request)
{
    $authorization = $request->get_header('authorization');
    $user          = get_user_credentials($authorization);

    if (empty($user) || !is_authenticated($user)) {
        return new WP_Error('rest_not_logged_in', 'You are not currently logged in.', array('status' => 401));
    }

    $data     = $request->get_params();
    $products = $data["products"];

    if (empty($data) || empty($products)) {
        return new WP_Error('rest_invalid_payload', 'You provided empty or invalid payload.', array('status' => 400));
    }

    foreach ($products as $key => $product) {
        $stock = get_field('stock', $product["id"]);
        $stock -= $product["quantity"];
        update_field('stock', $stock, $product["id"]);
    }

    return true;
}

/**
 * Register custom routes
 */
function register_custom_rest_routes()
{
    register_rest_route('custom/v1', '/posts/', array(
        'methods'  => 'GET',
        'callback' => 'get_all_posts',
    ));

    register_rest_route('custom/v1', '/posts/(?P<id>\d+)', array(
        'methods'  => 'GET',
        'callback' => 'get_post_by_id',
    ));

    register_rest_route('custom/v1', '/products/', array(
        'methods'  => 'GET',
        'callback' => 'get_all_products',
    ));

    register_rest_route('custom/v1', '/products/(?P<id>\d+)', array(
        'methods'  => 'GET',
        'callback' => 'get_product_by_id',
    ));

    register_rest_route('custom/v1', '/stock', array(
        'methods'  => 'POST',
        'callback' => 'update_stock',
    ));
}

add_action('rest_api_init', 'register_custom_rest_routes');

/**
 * Remove default routes
 */
function remove_default_endpoints($endpoints)
{
    $routes = array();
    foreach ($endpoints as $key => $endpoint) {
        $namespace_endpoint = "custom/v1";

        if ($endpoint["namespace"] == $namespace_endpoint) {
            $routes[$key] = $endpoints[$key];
        }
    }

    $endpoints = $routes;
    return (array) $endpoints;
}

add_filter('rest_endpoints', 'remove_default_endpoints');

/**
 * Register build hook on each post update
 */
function post_published_notification($ID, $post)
{
    wp_remote_post(NETLIFY_BUILD_HOOK);
}

add_action('publish_post', 'post_published_notification', 10, 2);

/**
 * Add product columns that will be displayed on dashboard
 */
function product_columns($columns)
{
    $columns['stock'] = __('Stock');
    return $columns;
}

add_filter('manage_product_posts_columns', 'product_columns');

/**
 * Fill product columns
 */
function fill_product_columns($column, $post_id)
{
    if ($column === 'stock') {
        echo get_field('stock', $post_id);
    }
}

add_action('manage_product_posts_custom_column', 'fill_product_columns', 10, 2);
