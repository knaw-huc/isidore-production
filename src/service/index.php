<?php
require(dirname(__FILE__) . '/config/config.php');
require(dirname(__FILE__) . '/config/db_config.php');
require(dirname(__FILE__) . '/classes/db.class.php');
require(dirname(__FILE__) . '/includes/functions.php');

$URI = $_SERVER["REQUEST_URI"];


$segments = explode('/', $URI);
if (isset($segments[2])) {
    $page = $segments[2];
} else {
    $page = "NULL";
}

switch ($page) {
    case "dummy":
        get_dummy();
        break;
    case "search":
        if (isset($segments[3])) {
            search($segments[3]);
        } else {
            throw_error();
        }
        break;
    case "detail":
        if (isset($segments[3])) {
            detail($segments[3]);
        } else {
            throw_error();
        }
        break;
    case "elastic":
        if (isset($segments[3])) {
            switch ($segments[3]) {
                case "initial_facet":
                    if (isset($segments[4]) && isset($segments[5]) && isset($segments[6])) {
                        get_initial_facets($segments[4], $segments[5], $segments[6]);
                    } else {
                        throw_error();
                    }
                    break;
                case "facet":
                    if (isset($segments[4]) && isset($segments[5]) && isset($segments[6]) && isset($segments[7])) {
                        get_facets($segments[4], $segments[5], $segments[7], $segments[6]);
                    } else {
                        throw_error();
                    }
                    break;
                case "nested_facet":
                    if (isset($segments[4]) && isset($segments[5]) && isset($segments[6])) {
                        if (isset($segments[7])) {
                            get_nested_facets($segments[4], $segments[5], $segments[6], strtolower($segments[7]));
                        } else {
                            get_nested_facets($segments[4], $segments[5], $segments[6]);
                        }

                    } else {
                        throw_error();
                    }
                    break;
                case "filter_facets":
                    if (isset($segments[4])) {
                        get_filter_facets($segments[4]);
                    } else {
                        throw_error();
                    }
                    break;
                case "search":
                    if (isset($segments[4])) {
                        search($segments[4]);
                    } else {
                        throw_error();
                    }
                    break;
                default:
                    throw_error();
                    break;
            }

        } else {
            throw_error();
        }
        break;
    default:
        throw_error();
        break;
}