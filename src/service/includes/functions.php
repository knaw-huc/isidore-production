<?php
$db = new db();

function get_dummy()
{
    global $db;

    send_json($db->dummy());
}

function detail($id)
{
    global $db;

    $result = $db->getBaseDetails($id);
    $file = "$id.jpg";
    if (file_exists(APPATH . "/img/detail/$file")) {
        $result["image"] = $file;
    } else {
        $result["image"] = "no_image.jpg";
    }
    if ($result["certainty"] == "no") {
        $result["place_absolute"] = $result["place_absolute"] . " (?)";
    }
    if ($result) {
        send_json($result);
    } else {
        throw_error();
    }
}


function elastic($json_struc)
{

    $options = array('Content-type: application/json', 'Content-Length: ' . strlen($json_struc));

    $ch = curl_init(ELASTIC_HOST);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $options);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_struc);
    //curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}


function search($codedStruc)
{
    $queryArray = json_decode(base64_decode($codedStruc), true);
    $json_struc = parse_codedStruc($queryArray);
    $send_back = array();
    error_log($json_struc);
    $result = elastic($json_struc);
    $send_back["amount"] = $result["hits"]["total"]["value"];
    $send_back["pages"] = ceil($send_back["amount"] / $queryArray["page_length"]);
    $send_back["manuscripts"] = array();
    foreach ($result["hits"]["hits"] as $manuscript) {
        $send_back["manuscripts"][] = $manuscript["_source"];
    }
    send_json($send_back);
}

function parse_codedStruc($queryArray)
{
    $page_length = $queryArray["page_length"];
    $from = ($queryArray["page"] - 1) * $queryArray["page_length"];
    $sortOrder = $queryArray["sortorder"];
    if ($queryArray["searchvalues"] == "none") {
        //$json_struc = "{ \"query\": {\"match_all\": {}}, \"size\": 50, \"from\": 0, \"_source\": [\"id\", \"shelfmark\", \"bischoff\", \"cla\",\"scaled_dates.date\", \"physical_state\",  \"absolute_places.place_absolute\", \"certainty\", \"no_of_folia\", \"page_height_min\", \"page_width_min\", \"designed_as\" ,\"material_type\", \"books_latin\", \"additional_content_scaled\", \"image\"], \"sort\": [{ \"id.keyword\": {\"order\":\"asc\"}}]}";
        $json_struc = "{ \"query\": {\"match_all\": {}}, \"size\": $page_length, \"from\": 0, \"_source\": [\"id\", \"shelfmark\", \"bischoff\", \"cla\",\"scaled_dates.date\", \"physical_state\",  \"absolute_places.place_absolute\", \"absolute_places.latitude\", \"absolute_places.longitude\", \"certainty\", \"no_of_folia\", \"page_height_min\", \"page_width_min\", \"designed_as\" ,\"material_type\", \"books_latin\", \"additional_content_scaled\", \"image\"]}";
        //error_log($json_struc);
    } else {
        $json_struc = buildQuery($queryArray, $from, $sortOrder);
    }
    error_log($json_struc);
    return $json_struc;
}

function buildQuery($queryArray, $from, $sortOrder)
{
    $terms = array();

    foreach ($queryArray["searchvalues"] as $item) {
        if (strpos($item["field"], '.')) {
            $fieldArray = explode(".", $item["field"]);
            $terms[] = nestedTemplate($fieldArray, makeItems($item["values"]));
        } else {
            $terms[] = matchTemplate($item["field"], makeItems($item["values"]));
        }

    }

    return queryTemplate(implode(",", $terms), $from, $sortOrder);
}

function matchTemplate($term, $value)
{
    switch ($term) {
        case "FREE_TEXT":
            return "{\"multi_match\": {\"query\": $value}}";
        case "BOOK":
            return bookValues($value);
            break;
        default:
            return "{\"terms\": {\"$term.raw\": [$value]}}";
    }
}

function nestedTemplate($fieldArray, $value)
{
    $path = $fieldArray[0];
    $field = implode(".", $fieldArray);
    return "{\"nested\": {\"path\": \"$path\",\"query\": {\"bool\": {\"must\": [{\"terms\": {\"$field.raw\": [$value]}}]}}}}";
}

function queryTemplate($terms, $from, $sortOrder)
{
    return "{ \"query\": { \"bool\": { \"must\": [ $terms ] } }, \"size\": 20, \"from\": $from, \"_source\": [\"id\", \"shelfmark\", \"bischoff\", \"cla\",\"scaled_dates.date\", \"physical_state\",  \"absolute_places.place_absolute\", \"absolute_places.latitude\", \"absolute_places.longitude\", \"certainty\", \"no_of_folia\", \"page_height_min\", \"page_width_min\", \"designed_as\" ,\"material_type\", \"books_latin\", \"additional_content_scaled\", \"image\"]}";
}

function bookValues($book)
{
    $book = str_replace("\"", "", $book);
    $bookSplit = explode(":", $book);
    $base = romanToNumeric($bookSplit[0]);
    $range = explode("-", $bookSplit[1]);
    $from = $base + $range[0];
    $to = $base + $range[1];
    return "{\"range\": {\"books.details.section\": {\"from\": $from, \"to\": $to}}}";
}

function romanToNumeric($book)
{
    switch ($book) {
        case "I":
            return 1000;
            break;
        case "II":
            return 2000;
            break;
        case "III":
            return 3000;
            break;
        case "IV":
            return 4000;
            break;
        case "V":
            return 5000;
            break;
        case "VI":
            return 6000;
            break;
        case "VII":
            return 7000;
            break;
        case "VIII":
            return 8000;
            break;
        case "IX":
            return 9000;
            break;
        case "X":
            return 10000;
            break;
        case "XI":
            return 11000;
            break;
        case "XII":
            return 12000;
            break;
        case "XIII":
            return 13000;
            break;
        case "XIV":
            return 14000;
            break;
        case "XV":
            return 15000;
            break;
        case "XVI":
            return 16000;
            break;
        case "XVII":
            return 17000;
            break;
        case "XVIII":
            return 18000;
            break;
        case "XIX":
            return 19000;
            break;
        case "XX":
            return 20000;
            break;
        default:
            return 0;
    }
}

function makeItems($termArray)
{
    $retArray = array();

    foreach ($termArray as $term) {
        $retArray[] = "\"" . $term . "\"";
    }
    return implode(", ", $retArray);
}

function get_facets($field, $filter, $type)
{
    if ($type == 'long') {
        $amount = 10;
    } else {
        $amount = 5;
    }
    if ($field == "schipper_naam") {
        $json_struc = "{\"query\": {\"regexp\": {\"schipper_achternaam\": {\"value\": \"$filter.*\"}}},\"aggs\": {\"names\" : {\"terms\" : { \"field\" : \"$field.raw\",  \"size\" : $amount }}}}";
    } else {
        $json_struc = "{\"query\": {\"regexp\": {\"$field\": {\"value\": \"$filter.*\"}}},\"aggs\": {\"names\" : {\"terms\" : { \"field\" : \"$field.raw\",  \"size\" : $amount }}}}";
    }

    $result = elastic($json_struc);
    send_json(array("buckets" => $result["aggregations"]["names"]["buckets"]));
}

function get_nested_facets($field, $type, $filter = "")
{
    switch ($type) {
        case "long":
            $amount = 10;
            break;
        case "normal":
            $amount = 100;
            break;
        default:
            $amount = 5;
            break;
    }
    $field_elements = explode(".", $field);
    $path = $field_elements[0];
    $json_struc = "{\"size\": 0,\"aggs\": {\"nested_terms\": {\"nested\": {\"path\": \"$path\"},\"aggs\": {\"filter\": {\"filter\": {\"regexp\": {\"$field\": \"$filter.*\"}},\"aggs\": {\"names\": {\"terms\": {\"field\": \"$field.raw\",\"size\": $amount}}}}}}}}";
    //error_log($json_struc);
    $result = elastic($json_struc);
    send_json(array("buckets" => $result["aggregations"]["nested_terms"]["filter"]["names"]["buckets"]));
}

function get_initial_facets($field, $type)
{
    switch ($type) {
        case "long":
            $amount = 10;
            break;
        case "normal":
            $amount = 100;
            break;
        default:
            $amount = 5;
            break;
    }
    $json_struc = "{\"size\": 0,\"aggs\" : {\"names\" : {\"terms\" : { \"field\" : \"$field.raw\",  \"size\" : $amount }}}}";
    $result = elastic($json_struc);
    echo send_json(array("buckets" => $result["aggregations"]["names"]["buckets"]));
}


function throw_error($error = "Bad request")
{
    $response = array("error" => $error);
    send_json($response);
}

function send_json($message_array)
{
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($message_array);
}

function send_elastic($json)
{
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    echo $json;
}