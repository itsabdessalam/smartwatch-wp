<?php

if (!defined('ABSPATH')) {
    // Exit if accessed directly.
    exit;
}

header('HTTP/1.1 404 Not Found');
header('Content-Type: application/json');

echo json_encode(
    array(
        'code'    => 'rest_no_route',
        'message' => 'No route was found matching the URL and request method',
        'data'    => (object) array('status' => 404),
    )
);

die;
