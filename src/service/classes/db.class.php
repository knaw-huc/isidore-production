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

    function getDownloadDetails($range) {
        $results = pg_query($this->con, "SELECT * FROM manuscripts WHERE id IN ($range)");
        $items = $this->ass_arr($results);
        if (count($items)) {
            return $this->buildDownload($items);
        } else {
            return 0;
        }
    }

    private function buildDownload($items) {
        $retArray = array();
        foreach ($items as $item) {
            $retArray[] = $this->buildManuscriptInstance($item, true);
        }
        return $retArray;
    }

    private function buildManuscriptInstance($item, $download = false)
    {
        $id = $item["id"];
        $manuscript = array();
        $manuscript["id"] = $item["id"];
        $manuscript["shelfmark"] = $item["shelfmark"];
        $manuscript["steinova"] = $item["steinova"];
        $manuscript["material_type"] = $item["material_type"];
        $manuscript["bischoff"] = $item["bischoff"];
        $manuscript["anspach"] = $item["anspach"];
        $manuscript["cla"] = $item["cla"];
        $manuscript["bischoff_cla_date"] = $item["bischoff_cla_date"];
        $manuscript["source_dating"] = $this->get_source($id);
        $manuscript["place_absolute"] = $this->get_place($id);
        $manuscript["certainty"] = $this->get_certainty($id);
        $manuscript["physical_state_scaled"] = $this->get_physical_state($id);
        $manuscript["physical_state"] = $item["physical_state_detail"];
        $manuscript["provenances"] = $this->get_provenance($id);
        $manuscript["designed_as"] = $this->get_designed_as($id);
        $manuscript["no_of_folia"] = $item["no_of_folia"];
        $manuscript["layout"] = $this->createLayout($item);
        $manuscript["script"] = $this->getScript($id);
        $manuscript["content"] = $this->getContent($id, $download);
        $manuscript["type"] = $this->getContentType($id);
        $manuscript["additional_content"] = $this->createLines($item["additional_content_scaled"], $download);
        $manuscript["larger_unit"] = $this->createLines($item["collection_larger_unit"], $download);
        $manuscript["related_manuscripts"] = $this->createRelatedManuscriptsList($item, $download);
        $manuscript["annotations"] = $this->stuffEmpty($item["annotations"]);
        $manuscript["diagrams"] = $this->stuffEmpty($item["diagrams"]);
        $manuscript["innovations"] = $this->stuffEmpty($item["innovations"]);
        $manuscript["additional_observations"] = $this->stuffEmpty($item["additional_observations"]);
        $manuscript["bibliography"] = $this->createBibliography($item, $download);
        $manuscript["digitized_at"] = $this->createDigitalVersions($item, $download);
        $manuscript["url_other"] = $this->stuffEmpty($item["url_other"]);
        $manuscript["page_number"] = $this->getPageNumber($id);
        return $manuscript;
    }


    private function get_provenance($id) {
        $results = pg_query($this->con, "SELECT provenance  FROM provenance_scaled pd, manuscripts_provenance_scaled m WHERE m.m_id = '$id' AND m.p_id = pd.p_id");
        return $this->ass_arr($results);
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
            return str_replace("amp;", "", $str);
            //return $str;
        }
    }

    private function createLines($str, $download) {
        if ($download) {
            return implode("\n", $this->trexplode(";", $str));
        } else {
            return $this->trexplode(";", $str);
        }

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

    private function get_source($id)
    {
        $results = pg_query($this->con, "select s.source from manuscripts_source_of_dating ms, source_of_dating s WHERE ms.s_id = s.s_id AND ms.m_id = '$id'");
        $items = $this->ass_arr($results);
        if (isset($items[0]["source"])) {
            return $items[0]["source"];
        } else {
            return "";
        }
    }

    private function get_physical_state($id)
    {
        $results = pg_query($this->con, "select physical_state FROM physical_state WHERE m_id = '$id'");
        $items = $this->ass_arr($results);
        if (isset($items[0]["physical_state"])) {
            return $items[0]["physical_state"];
        } else {
            return "";
        }
    }

    private function get_designed_as($id)
    {
        $results = pg_query($this->con, "select d.designed_as from manuscripts_designed_as md, designed_as d WHERE md.design_id = d.design_id AND md.m_id = '$id'");
        $items = $this->ass_arr($results);
        if (isset($items[0]["designed_as"])) {
            return $items[0]["designed_as"];
        } else {
            return "-";
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

    private function createRelatedManuscriptsList($item, $download)
    {
        $retArray = array();
        if (is_null($item["reason_for_relationship"]) || strlen($item["reason_for_relationship"]) == 0) {
            if ($download) {
                return "-";
            } else {
                return $retArray;
            }
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
        if ($download) {
            return $this->flattenRelationArray($retArray);
        } else {
            return $retArray;
        }

    }

    private function flattenRelationArray($rels) {
        $retArray = array();

        foreach ($rels as $rel) {
            $retArray[] = $rel["reason"];
            foreach ($rel["intern"] as $element) {
                $retArray[] = $element["shelfmark"] . "(" . $element["id"] . ")";
            }
            $retArray[] = implode("\n", $rel["extern"]);
        }
        return implode("\n", $retArray);
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

    private function createBibliography($item, $download)
    {
        $tmp = str_replace("</i>", "", $item["bibliography"]);
        $tmp = str_replace("<i>", "", $tmp);
        $books = $this->trexplode(";", $tmp);
        if ($download) {
           return implode("\n", $books);
        } else {
            return $books;
        }
    }

    private function createDigitalVersions($item, $download)
    {
        $retArray = array();
        if (!is_null($item["url_images_1"]) && strlen($item["url_images_1"]) > 0) {
            if ($download) {
                $retArray[] = $item["url_images_1"];
            } else {
                $retArray[] = array( "item" => $item["url_images_1"]);
            }
        }
        if (!is_null($item["url_images_2"]) && strlen($item["url_images_2"]) > 0) {
            if ($download) {
                $retArray[] = $item["url_images_2"];
            } else {
                $retArray[] = array( "item" => $item["url_images_2"]);
            }
        }
        if (!is_null($item["url_images_3"]) && strlen($item["url_images_3"]) > 0) {
            if ($download) {
                $retArray[] = $item["url_images_3"];
            } else {
                $retArray[] = array( "item" => $item["url_images_3"]);
            }
        }
        if (!is_null($item["url_images_4"]) && strlen($item["url_images_4"]) > 0) {
            if ($download) {
                $retArray[] = $item["url_images_4"];
            } else {
                $retArray[] = array( "item" => $item["url_images_4"]);
            }
        }

        if ($download) {
            return implode("\n",$retArray);
        } else {
            return $retArray;
        }

    }

    private function getContent($id, $download)
    {
        $results = pg_query($this->con, "SELECT material_type, books_included, string_agg( details, ';') details , string_agg( locations, ';') locations from manuscripts_details_locations WHERE m_id = '$id' GROUP BY material_type, books_included");
        if ($download) {
            return $this->contentDownload($this->ass_arr($results));
        } else {
            $retArray = [];
            foreach ( $this->ass_arr($results) as $item ) {
                $buffer = [];
                $buffer["material_type"] = $item["material_type"];
                $buffer["books_included"] = $item["books_included"];
                $details = explode(";", $item["details"]);
                $locations = explode(";", $item["locations"]);
                $buffer["details"] = $this->merge($details, $locations);
                $retArray[] = $buffer;
            }
            return $retArray;
        }
    }

    private function merge($details, $locations) {
        $count = count($details);
        $retArray = array();

        for ($i = 0; $i < $count; $i++)
        {
            $buffer = array();
            $buffer["details"] = $details[$i];
            $buffer["locations"] = $locations[$i];
            $retArray[] = $buffer;
        }
        return $retArray;
    }

    private function contentDownload($arr) {
        $retArr = array();
        foreach ($arr as $element) {
            $retArr[] = implode(", ", array_values($element));
        }
        return implode("\n", $retArr);
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

    private function get_certainty($id)
    {
        $results = pg_query($this->con, "SELECT certainty  FROM manuscripts_absolute_places WHERE m_id = '$id'");
        $items = $this->ass_arr($results);
        if (count($items)) {
            return $items[0]["certainty"];
        } else {
            return "";
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