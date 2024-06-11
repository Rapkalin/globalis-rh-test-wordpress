<?php

require_once __DIR__ . '/src/schema.php';
require_once __DIR__ . '/src/registrations.php';

add_action('filter_page_access', 'do_filter_page_access');

/**
 * Limit the access to the listed pages
 * in $limited_access_pages
 *
 * @return void
 */
function do_filter_page_access(): void
{
    $limited_access_pages = [
        'registrations'
    ];

    if (in_array(get_post_type(), $limited_access_pages)) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
        get_template_part(404);
        exit();
    }
}
