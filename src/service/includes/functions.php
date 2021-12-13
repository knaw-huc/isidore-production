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
    //error_log($json_struc);
    $options = array('Content-type: application/json', 'Content-Length: ' . strlen($json_struc));
    $ch = curl_init(ELASTIC_HOST);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $options);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_struc);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function download($format, $codedStruc)
{
    global $db;

    $queryArray = json_decode(base64_decode($codedStruc), true);
    $json_struc = parse_codedStruc($queryArray, true);
    $result = elastic($json_struc);
    $ids = array();
    foreach ($result["hits"]["hits"] as $manuscript) {
        $ids[] = "'" . $manuscript["_source"]["id"] . "'";
    }
    $downloadData = $db->getDownloadDetails(implode(", ", $ids));
    switch($format) {
        case "csv":
            download_csv($downloadData);
            break;
        case "excel":
            download_excel($downloadData);
            break;
        case "xml":
            download_xml($downloadData);
            break;
    }


}

function download_csv($downloadData) {
    $row = $downloadData[0];
    header("Content-Disposition: attachment; filename=isidore_results.csv");
    header("Content-Type: text/csv");
    $fp = fopen('php://output', 'w');
    fputcsv($fp, array_keys($row), "\t");
    foreach ($downloadData as $data) {
        fputcsv($fp, $data, "\t", '"');
    }
}

function download_xml($downloadData) {
    $xml = array_to_xml($downloadData);
    header("Content-Disposition: attachment; filename=isidore_results.xml");
    header('Content-Type: text/xml');
    echo $xml;
}

function array_to_xml($arr) {
    $retXML = "";
    foreach ($arr as $row) {
        $retXML .= get_item($row);
    }
    return spit_element("root", $retXML);
}

function get_item($row) {
    $item = "";
    foreach ($row as $key => $value) {
        $item .= spit_element($key, $value);
    }
    return spit_element("item", $item);
}

function spit_element($key, $value) {
    return "<$key>$value</$key>";
}

function download_excel($downloadData) {
    $row = $downloadData[0];
    $fileName = "isidore_results.xls";
    $excelData = implode("\t", array_keys($row)) . "\n";
    $i = 0;
    while ($i < count($downloadData)) {
        $rowData = $downloadData[$i];
        array_walk($rowData, 'filterData');
        $excelData .= implode("\t", array_values($rowData)) . "\n";
        $i++;
    }
    header("Content-Disposition: attachment; filename=\"$fileName\"");
    header('Content-type: text/csv; charset=UTF-8');
    //echo "\xEF\xBB\xBF"; // UTF-8 BOM
    //mb_convert_encoding($excelData, 'UTF-16LE', 'UTF-8');
    echo $excelData;
}


function search($codedStruc)
{
    $queryArray = json_decode(base64_decode($codedStruc), true);
    $json_struc = parse_codedStruc($queryArray);
    $send_back = array();
    $result = elastic($json_struc);
    $send_back["amount"] = $result["hits"]["total"]["value"];
    $send_back["pages"] = ceil($send_back["amount"] / $queryArray["page_length"]);
    $send_back["manuscripts"] = array();
    foreach ($result["hits"]["hits"] as $manuscript) {
        $send_back["manuscripts"][] = $manuscript["_source"];
    }
    send_json($send_back);
}

function parse_codedStruc($queryArray, $download = false)
{
    $page_length = $queryArray["page_length"];
    $from = ($queryArray["page"] - 1) * $queryArray["page_length"];
    $sortOrder = $queryArray["sortorder"];
    if ($queryArray["searchvalues"] == "none") {
        $json_struc = "{ \"query\": {\"match_all\": {}}, \"size\": $page_length, \"from\": $from, \"_source\": [\"id\", \"shelfmark\", \"bischoff\", \"cla\",\"scaled_dates.label\", \"accepted_date\",\"physical_state\",  \"absolute_places.place_absolute\", \"absolute_places.latitude\", \"absolute_places.longitude\", \"library.place_name\", \"library.latitude\", \"library.longitude\", \"certainty\", \"no_of_folia\", \"page_height_min\", \"page_width_min\", \"designed_as\" ,\"material_type\", \"books_latin\", \"additional_content_scaled\", \"image\"],\"sort\": [{ \"shelfmark.raw\": {\"order\":\"asc\"}}]}";
    } else {
        $json_struc = buildQuery($queryArray, $from, $page_length, $sortOrder, $download);
    }

    return $json_struc;
}

function buildQuery($queryArray, $from, $page_length, $sortOrder, $download)
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

    return queryTemplate(implode(",", $terms), $from, $page_length, $sortOrder, $download);
}

function matchTemplate($term, $value)
{
    switch ($term) {
        case "FREE_TEXT":
            return "{\"multi_match\": {\"query\": $value, \"fields\": [\"fulltext\"]}}";
        case "DATE_NUMERICAL":
            $range = explode("-", str_replace('"', '',$value));
            $lower = $range[0];
            $upper = $range[1];
            return "{\"nested\": {\"path\": \"scaled_dates\",\"query\": {\"range\": {\"scaled_dates.lower\": {\"gte\": $lower}}}}},{\"nested\": {\"path\": \"scaled_dates\",\"query\": {\"range\": {\"scaled_dates.upper\": {\"lt\": $upper}}}}}";
        case "BOOK":
            return bookValues($value);
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

function queryTemplate($terms, $from, $page_length, $sortOrder, $download)
{
    if ($download) {
        return "{ \"query\": { \"bool\": { \"must\": [ $terms ] } }, \"size\": 500, \"from\": 0, \"_source\": [\"id\"]}";
    } else {
        return "{ \"query\": { \"bool\": { \"must\": [ $terms ] } }, \"size\": $page_length, \"from\": $from, \"_source\": [\"id\", \"shelfmark\", \"bischoff\", \"cla\",\"scaled_dates.label\", \"accepted_date\", \"physical_state\",  \"absolute_places.place_absolute\", \"absolute_places.latitude\", \"absolute_places.longitude\", \"library.place_name\", \"library.latitude\", \"library.longitude\", \"certainty\", \"no_of_folia\", \"page_height_min\", \"page_width_min\", \"designed_as\" ,\"material_type\", \"books_latin\", \"additional_content_scaled\", \"image\"], \"sort\": [{ \"shelfmark.raw\": {\"order\":\"asc\"}}]}";
    }

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

function get_filter_facets($searchStruc)
{
    $queryArray = json_decode(base64_decode($searchStruc), true);
    $subQuery = parseQueryFields($queryArray);
    $values = array();
    $values["annotations"] = get_filter_facet_amount("annotations", "yes", $subQuery);
    $values["digitized"] = get_filter_facet_amount("digitized", "yes", $subQuery);
    $values["excluded"] = get_filter_facet_amount("excluded", "yes", $subQuery);
    $values["part"] = get_filter_facet_amount("part", "yes", $subQuery);
    send_json($values);
}

function parseQueryFields($queryArray)
{
    if ($queryArray["searchvalues"] == "none") {
        return "none";
    }

    $terms = array();

    foreach ($queryArray["searchvalues"] as $item) {
        if (strpos($item["field"], '.')) {
            $fieldArray = explode(".", $item["field"]);
            $terms[] = nestedTemplate($fieldArray, makeItems($item["values"]));
        } else {
            $terms[] = matchTemplate($item["field"], makeItems($item["values"]));
        }

    }

    return implode(",", $terms);
}

function get_filter_facet_amount($field, $value, $subQuery)
{
    $retValue = 0;
    if ($subQuery == "none") {
        $json_struc = "{\"size\": 0, \"aggs\": {\"names\": {\"terms\": {\"field\": \"$field.raw\", \"size\": 10}}}}";
    } else {
        $json_struc = "{ \"query\": { \"bool\": { \"must\": [ $subQuery ] } }, \"size\": 0, \"aggs\": {\"names\": {\"terms\": {\"field\": \"$field.raw\", \"size\": 10}}}}";
    }

    $result = elastic($json_struc);
    $buckets = $result["aggregations"]["names"]["buckets"];
    foreach ($buckets as $bucket) {
        if ($bucket["key"] == $value) {
            $retValue = $bucket["doc_count"];
        }
    }
    return $retValue;
}


function get_facets($field, $searchStruc, $filter, $type)
{
    if ($type == 'long') {
        $amount = 400;
    } else {
        $amount = 10;
    }
    $queryArray = json_decode(base64_decode($searchStruc), true);
    $subQuery = parseQueryFields($queryArray);
    if ($subQuery == "none") {
        $json_struc = "{\"query\": {\"regexp\": {\"$field\": {\"value\": \"$filter.*\"}}},\"aggs\": {\"names\" : {\"terms\" : { \"field\" : \"$field.raw\",  \"size\" : $amount }}}}";
    } else {
        $json_struc = "{\"query\":  {\"bool\": { \"must\": [ $subQuery , {\"regexp\": {\"$field\": {\"value\": \"$filter.*\"}}}] }},\"aggs\": {\"names\" : {\"terms\" : { \"field\" : \"$field.raw\",  \"size\" : $amount }}}}";
    }

    $result = elastic($json_struc);
    send_json(array("buckets" => sortResult($result["aggregations"]["names"]["buckets"])));
}

function get_nested_facets($field, $searchStruc, $type, $filter = "")
{
    switch ($type) {
        case "long":
            $amount = 400;
            break;
        case "normal":
            $amount = 100;
            break;
        default:
            $amount = 10;
            break;
    }
    $queryArray = json_decode(base64_decode($searchStruc), true);
    $subQuery = parseQueryFields($queryArray);

    $field_elements = explode(".", $field);
    $path = $field_elements[0];
    if ($subQuery == "none") {
        $json_struc = "{\"size\": 0,\"aggs\": {\"nested_terms\": {\"nested\": {\"path\": \"$path\"},\"aggs\": {\"filter\": {\"filter\": {\"regexp\": {\"$field\": \"$filter.*\"}},\"aggs\": {\"names\": {\"terms\": {\"field\": \"$field.raw\",\"size\": $amount}}}}}}}}";
    } else {
        $json_struc = "{\"query\": { \"bool\": { \"must\": [ $subQuery ] } }, \"size\": 0, \"aggs\": {\"nested_terms\": {\"nested\": {\"path\": \"$path\"},\"aggs\": {\"filter\": {\"filter\": {\"regexp\": {\"$field\": \"$filter.*\"}},\"aggs\": {\"names\": {\"terms\": {\"field\": \"$field.raw\",\"size\": $amount}}}}}}}}";
    }

    $result = elastic($json_struc);
    send_json(array("buckets" => sortResult($result["aggregations"]["nested_terms"]["filter"]["names"]["buckets"])));
}

function sortResult($arr) {
    usort($arr, "cmp");
    return $arr;
}

function cmp($a, $b)
{
    if (strtolower($a["key"]) == strtolower($b["key"])) {
        return 0;
    }
    return (strtolower($a["key"]) < strtolower($b["key"])) ? -1 : 1;
}

function get_initial_facets($field, $searchStruc, $type)
{
    switch ($type) {
        case "long":
            $amount = 400;
            break;
        case "normal":
            $amount = 100;
            break;
        default:
            $amount = 10;
            break;
    }

    $queryArray = json_decode(base64_decode($searchStruc), true);
    $subQuery = parseQueryFields($queryArray);
    if ($subQuery == "none") {
        $json_struc = "{\"size\": 0,\"aggs\" : {\"names\" : {\"terms\" : { \"field\" : \"$field.raw\",  \"size\" : $amount, \"order\": {\"_key\": \"asc\"}}, \"aggs\": {\"byHash\": {\"terms\": {\"field\": \"hash\"}}}}}}";
    } else {
        $json_struc = "{\"query\": { \"bool\": { \"must\": [ $subQuery ] } }, \"size\": 0, \"aggs\" : {\"names\" : {\"terms\" : { \"field\" : \"$field.raw\",  \"size\" : $amount, \"order\": {\"_key\": \"asc\"}}, \"aggs\": {\"byHash\": {\"terms\": {\"field\": \"hash\"}}}}}}";
    }
    $result = elastic($json_struc);
    //error_log($json_struc);
    echo send_json(array("buckets" => $result["aggregations"]["names"]["buckets"]));
}

// Export to Excel function
function filterData(&$str){
    $str = preg_replace("/\t/", "\\t", $str);
    $str = preg_replace("/\r?\n/", "\\n", $str);
    if(strstr($str, '"')) $str = '"' . str_replace('"', '""', $str) . '"';
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