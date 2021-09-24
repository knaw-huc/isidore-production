<?php
require(dirname(__FILE__) . '/config/config.php');
require(dirname(__FILE__) . '/config/db_config.php');
require(dirname(__FILE__) . '/classes/db.class.php');
require(dirname(__FILE__) . '/includes/functions.php');

error_reporting(0);

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
        if (isset($_GET["q"])) {
            search($_GET["q"]);
        } else {
            throw_error();
        }
        break;
    case "download":
        if (isset($segments[3]) && isset($_GET["q"])) {
            download($segments[3], $_GET["q"]);
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
                    if (isset($_GET["f"]) && isset($_GET["q"]) && isset($_GET["l"])) {
                        get_initial_facets($_GET["f"], $_GET["q"], $_GET["l"]);
                    } else {
                        throw_error();
                    }
                    break;
                case "facet":
                    if (isset($_GET["f"]) && isset($_GET["q"]) && isset($_GET["l"]) && isset($_GET["s"])) {
                        get_facets($_GET["f"], $_GET["q"], $_GET["s"], $_GET["l"]);
                    } else {
                        throw_error();
                    }
                    break;
                case "nested_facet":
                    if (isset($_GET["f"]) && isset($_GET["q"]) && isset($_GET["l"])) {
                        if (isset($_GET["s"])) {
                            get_nested_facets($_GET["f"], $_GET["q"], $_GET["l"], strtolower($_GET["s"]));
                        } else {
                            get_nested_facets($_GET["f"], $_GET["q"], $_GET["l"]);
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
                    if (isset($_GET["q"])) {
                        search($_GET["q"]);
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