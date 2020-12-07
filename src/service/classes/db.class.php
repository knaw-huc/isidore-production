<?php

class db
{

    var $con;

    function __construct()
    {
        $this->con = pg_connect(PG_DB);
    }

    function dummy()
    {
        $results = pg_query($this->con, "SELECT shelfmark, bischoff_cla_date, material_type, string_agg(DISTINCT designed_as, ',') as designed_as, string_agg(DISTINCT p.place_absolute, ',') AS place, certainty, no_of_folia, page_height_min, page_width_min, additional_content_scaled, string_agg(DISTINCT b.roman, ', ') AS books, m.id FROM manuscripts m, absolute_places p, manuscripts_absolute_places mp, books b, manuscripts_books_included bi, designed_as d, manuscripts_designed_as md WHERE m.id = mp.m_id AND mp.place_id = p.place_id AND m.id = md.m_id AND md.design_id = d.design_id AND m.id = bi.m_id AND bi.b_id = b.id GROUP BY m.id LIMIT 20");
        $items = $this->ass_arr($results);
        return $items;
    }

    function getBaseDetails($id)
    {
        $results = pg_query($this->con, "SELECT * FROM manuscripts WHERE id = '$id'");
        $items = $this->ass_arr($results);
        if (count($items)) {
            return $this->buildManuscriptInstance($items[0]);
        } else {
            return 0;
        }
    }

    private function buildManuscriptInstance($item)
    {
        $id = $item["id"];
        $manuscript = array();
        $manuscript["id"] = $item["id"];
        $manuscript["shelfmark"] = $item["shelfmark"];
        $manuscript["steinova"] = $item["steinova"];
        $manuscript["material_type"] = $item["material_type"];
        $manuscript["bischoff"] = $item["bischoff"];
        $manuscript["anspach"] = $item["anspach"];
        $manuscript["bischoff_cla_date"] = $item["bischoff_cla_date"];
        $manuscript["place_absolute"] = $this->get_place($id);
        $manuscript["certainty"] = $item["certainty"];
        $manuscript["provenance"] = $item["provenance"];
        $manuscript["no_of_folia"] = $item["no_of_folia"];
        $manuscript["layout"] = $this->createLayout($item);
        $manuscript["script"] = $this->getScript($id);
        $manuscript["content"] = $this->getContent($id);
        $manuscript["type"] = $this->getContentType($id);
        $manuscript["additional_content"] = $this->createLines($item["additional_content_scaled"]);
        $manuscript["larger_unit"] = $this->createLines($item["collection_larger_unit"]);
        $manuscript["related_manuscripts"] = $this->createRelatedManuscriptsList($item);
        $manuscript["annotations"] = $this->stuffEmpty($item["annotations"]);
        $manuscript["innovations"] = $this->stuffEmpty($item["innovations"]);
        $manuscript["additional_observations"] = $this->stuffEmpty($item["additional_observations"]);
        $manuscript["bibliography"] = $this->createBibliography($item);
        $manuscript["digitized_at"] = $this->createDigitalVersions($item);
        $manuscript["page_number"] = $this->getPageNumber($id);
        return $manuscript;
    }

    private function getPageNumber($id) {
        $results = $this->ass_arr(pg_query($this->con, "SELECT subscript FROM image_subscripts WHERE m_id = '$id'"));
        if (count($results)) {
            return $results[0]["subscript"];
        } else {
            return "";
        }

    }

    private function stuffEmpty($str) {
        if (is_null($str) || strlen($str) == 0)
        {
            return "-";
        } else {
            return $str;
        }
    }

    private function createLines($str) {
        return $this->trexplode(";", $str);
    }

    private function getScript($id)
    {
        $results = pg_query($this->con, "select s.script from manuscripts_scripts ms, scripts s WHERE ms.script_id = s.script_id AND ms.m_id = '$id'");
        $items = $this->ass_arr($results);
        if (isset($items[0]["script"])) {
            return $items[0]["script"];
        } else {
            return "";
        }
    }

    private function createLayout($item)
    {
        $retArray = array();
        if (is_numeric($item["page_height_min"]) && is_numeric($item["page_width_min"])) {
            if (is_numeric($item["page_height_max"])) {
                $retArray[] = $item["page_height_max"] . "x" . $item["page_width_min"] . " mm";
            } else {
                $retArray[] = $item["page_height_min"] . "x" . $item["page_width_min"] . " mm";
            }
        }
        if ($item["columns"] == 1) {
            $retArray[] = str_replace("(x mm), ", "", "(" . $item["writing_window_height_min"] . "x" . $item["writing_window_width_min"] . " mm), " . $item["lines"] . " long lines");
        } else {
            $retArray[] = str_replace("(x mm), ", "", "(" . $item["writing_window_height_min"] . "x" . $item["writing_window_width_min"] . " mm), " . $item["lines"] . " lines, " . $item["columns"] . " columns");
        }
        return implode(", ", $retArray);
    }

    private function createRelatedManuscriptsList($item)
    {
        $retArray = array();
        if (is_null($item["reason_for_relationship"]) || strlen($item["reason_for_relationship"]) == 0) {
            return $retArray;
        }
        $reasons = $this->trexplode("+", $item["reason_for_relationship"]);
        $intern = $this->trexplode("+", $item["related_mss_in_the_database"]);
        $extern = $this->trexplode("+", $item["related_mss_outside_of_the_database"]);
        if (count($reasons) > 0) {
            for ($i = 0; $i < count($reasons); $i++) {
                $tmpArray = array();
                $tmpArray["reason"] = $reasons[$i];
                if (isset($intern[$i])) {
                    $tmpArray["intern"] = $this->getInternRelations($intern[$i]);
                } else {
                    $tmpArray["intern"] = array();
                }
                if (isset($extern[$i])) {
                    $tmpArray["extern"] = $this->trexplode(";", $extern[$i]);
                } else {
                    $tmpArray["extern"] = array();
                }
            }
            $retArray[] = $tmpArray;
        }
        return $retArray;
    }

    private function getInternRelations($str)
    {
        $tmpArray = $this->trexplode(";", $str);
        if (count($tmpArray) > 0) {
            $tmpIDs = array();
            for ($i = 0; $i < count($tmpArray); $i++) {
                $tmpIDs[] = "'" . $tmpArray[$i] . "'";
            }
            $ids = implode(", ", $tmpIDs);
            $results = pg_query($this->con, "SELECT id, shelfmark FROM manuscripts WHERE id IN ($ids)");
            return $this->ass_arr($results);
        } else {
            return array();
        }
    }

    private function trexplode($delimmiter, $str)
    {
        $retArray = array();
        $tmpArray = explode($delimmiter, $str);
        foreach ($tmpArray as $element) {
            $retArray[] = trim($element);
        }
        return $retArray;
    }

    private function createBibliography($item)
    {
        $books = $this->trexplode(";", $item["bibliography"]);
        return $books;  
    }

    private function createDigitalVersions($item)
    {
        $retArray = array();
        if (!is_null($item["url_images_1"]) && strlen($item["url_images_1"]) > 0) {
            $retArray[] = array( "item" => $item["url_images_1"]);
        }
        if (!is_null($item["url_images_2"]) && strlen($item["url_images_2"]) > 0) {
            $retArray[] = array( "item" => $item["url_images_2"]);
        }
        if (!is_null($item["url_images_3"]) && strlen($item["url_images_3"]) > 0) {
            $retArray[] = array( "item" => $item["url_images_3"]);
        }
        if (!is_null($item["url_images_4"]) && strlen($item["url_images_4"]) > 0) {
            $retArray[] = array( "item" => $item["url_images_4"]);
        }
        return $retArray;
    }

    private function getContent($id)
    {
        $results = pg_query($this->con, "SELECT details, locations FROM manuscripts_details_locations WHERE m_id = '$id'");
        return $this->ass_arr($results);
    }

    private function getContentType($id)
    {
        $results = $this->ass_arr(pg_query($this->con, "select ct.content_type from manuscripts_content_types mt, content_types ct WHERE mt.m_id = '$id' AND mt.type_id = ct.type_id"));
        if (count($results)) {
            return $results[0]["content_type"];
        } else {
            return "";
        }
    }

    private function get_place($id)
    {
        $results = pg_query($this->con, "SELECT ap.place_absolute  FROM absolute_places ap, manuscripts_absolute_places m WHERE m.m_id = '$id' AND m.place_id = ap.place_id");
        $items = $this->ass_arr($results);
        if (count($items)) {
            return $items[0]["place_absolute"];
        } else {
            return "-";
        }
    }

    function elastic($json_struc)
    {
        $options = array('Content-type: application/json', 'Content-Length: ' . strlen($json_struc));
        error_log($json_struc);
        $ch = curl_init(ELASTIC_HOST);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $options);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_struc);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    private function ass_arr($results)
    {
        $retArray = array();
        while ($row = pg_fetch_assoc($results)) {
            $retArray[] = $row;
        }
        return $retArray;
    }
}