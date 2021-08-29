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

    function getDownloadDetails($range)
    {
        $results = pg_query($this->con, "SELECT * FROM manuscripts WHERE id IN ($range)");
        $items = $this->ass_arr($results);
        if (count($items)) {
            return $this->buildDownload($items);
        } else {
            return 0;
        }
    }

    private function buildDownload($items)
    {
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
        $manuscript["former_shelfmarks"] = $item["former_shelfmarks"];
        $manuscript["siglum"] = $item["siglum"];
        $manuscript["steinova"] = $item["steinova"];
        $manuscript["material_type"] = $item["material_type"];
        $manuscript["bischoff"] = $item["bischoff"];
        $manuscript["anspach"] = $item["anspach"];
        $manuscript["cla"] = $item["cla"];
        $manuscript["bischoff_cla_date"] = $this->getDateOfOrigin($id);
        $manuscript["source_dating"] = $this->get_source($id);
        $manuscript["place_absolute"] = $this->get_place($id);
        $manuscript["certainty"] = $this->get_certainty($id);
        $manuscript["physical_state_scaled"] = $item["physical_state_scaled"];
        $manuscript["physical_state"] = $item["physical_state_detail"];
        $manuscript["provenances"] = $this->get_provenance($id, $download);
        $manuscript["designed_as"] = $this->get_designed_as($id);
        $manuscript["no_of_folia"] = $item["no_of_folia"];
        $manuscript["layout"] = $this->createLayout($item);
        $manuscript["script"] = $this->getScript($id);
        $manuscript["content"] = $this->getContent($id, $download);
        $manuscript["type"] = $this->getContentType($id);
        $manuscript["additional_content"] = $this->createLines($item["additional_content_scaled"], $download);
        $manuscript["larger_unit"] = $this->createLines($item["collection_larger_unit"], $download);
        $manuscript["related_manuscripts"] = $this->createRelatedManuscriptsList($id, $download);
        $manuscript["interpolations"] = $this->getInterpolations($id);
        $manuscript["easter_tables"] = $this->getEasterTables($id);
        $manuscript["annotations"] = $this->getAnnotations($id);
        $manuscript["diagrams"] = $this->getDiagrams($id);
        $manuscript["innovations"] = $this->stuffEmpty($item["innovations"]);
        $manuscript["additional_observations"] = $this->stuffEmpty($item["additional_observations"]);
        $manuscript["bibliography"] = $this->createBibliography($item, $download);
        $manuscript["digitized_at"] = $this->createDigitalVersions($id, $download);
        $manuscript["url_other"] = $this->createOtherInfo($id, $download);
        $manuscript["iiif"] = $item["iiif"];
        $manuscript["page_number"] = $this->getPageNumber($id);
        $manuscript["created_by"] = $item["created_by"];
        $manuscript["created_on"] = $item["created_on"];
        $manuscript["contributions_from"] = $item["contributions_from"];
        $manuscript["completeness_of_the_record"] = $item["completeness_of_the_record"];
        $manuscript["last_updated_on"] = $item["last_updated_on"];
        return $manuscript;
    }


    private function get_provenance($id, $download)
    {
        $results = pg_query($this->con, "SELECT provenance  FROM provenance_scaled pd, manuscripts_provenance_scaled m WHERE m.m_id = '$id' AND m.p_id = pd.p_id");
        if ($download) {
            return $this->flattenProvenanceArray($this->ass_arr($results));
        } else {
            return $this->ass_arr($results);
        }

    }

    private function getPageNumber($id)
    {
        $results = $this->ass_arr(pg_query($this->con, "SELECT subscript FROM image_subscripts WHERE m_id = '$id'"));
        if (count($results)) {
            return $results[0]["subscript"];
        } else {
            return "";
        }
    }

    private function getDateOfOrigin($id) {
        $results = $this->ass_arr(pg_query($this->con, "SELECT date FROM manuscripts_scaled_dates msc, scaled_dates sc WHERE msc.m_id = '$id' AND msc.date_id=sc.date_id"));
        if (count($results)) {
            return $results[0]["date"];
        } else {
            return "-";
        }
    }

    private function stuffEmpty($str)
    {
        if (is_null($str) || strlen($str) == 0) {
            return "-";
        } else {
            return str_replace("amp;", "", $str);
            //return $str;
        }
    }

    private function createLines($str, $download)
    {
        if ($download) {
            return implode("\n", $this->trexplode(";", $str));
        } else {
            return $this->trexplode(";", $str);
        }
    }

    private function getInterpolations($id)
    {
        $result = pg_query($this->con, "SELECT interpolation, folia, description FROM interpolations WHERE m_id='$id' AND interpolation <> ''");
        if (pg_num_rows($result) > 0) {
            return $this->ass_arr($result);
        } else {
            return array();
        }
    }

    private function getDiagrams($id)
    {
        $result = pg_query($this->con, "SELECT diagram_type, folia, description FROM diagrams WHERE m_id='$id' AND diagram_type <> ''");
        if (pg_num_rows($result) > 0) {
            return $this->ass_arr($result);
        } else {
            return array();
        }
    }

    private function getAnnotations($id)
    {
        $result = pg_query($this->con, "SELECT number_of_annotations, books, language, remarks, REPLACE(url, 'http://peterboot.nl/isidore/#glossms-', '') url FROM annotations WHERE m_id = '$id' AND number_of_annotations <> ''");

        if (pg_num_rows($result) > 0) {
            return $this->ass_arr($result);
        } else {
            return array();
        }
    }

    private function getEasterTables($id)
    {
        $result = pg_query($this->con, "SELECT easter_table_type, folia, remarks FROM easter_table WHERE m_id='$id' AND easter_table_type <> ''");
        if (pg_num_rows($result) > 0) {
            return $this->ass_arr($result);
        } else {
            return array();
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

    private function getShelfmark($id)
    {
        $result = $this->ass_arr(pg_query($this->con, "SELECT shelfmark FROM manuscripts WHERE id = '$id'"));
        if (count($result)) {
            return $result[0]["shelfmark"];
        } else {
            return "";
        }
    }

    private function createRelatedManuscriptsList($id, $download)
    {
        $retArray = array();
        $current_reason = "";
        $buffer = array();
        $results = $this->ass_arr(pg_query($this->con, "SELECT m_id, reason, rel_mss_id, rel_mss_other FROM relationships WHERE m_id = '$id' ORDER BY reason"));
        if (count($results)) {
            foreach ($results as $item) {
                if ($item["reason"] != $current_reason) {
                    $current_reason = $item["reason"];
                    if (count($buffer)) {
                        $retArray[] = $buffer;
                    }
                    $buffer = array("reason" => $item["reason"], "intern" => array(), "extern" => array());
                }
                if (!is_null($item["rel_mss_id"]) && strlen($item["rel_mss_id"]) != 0) {
                    $buffer["intern"][] = array("id" => $item["rel_mss_id"], "shelfmark" => $this->getShelfmark($item["rel_mss_id"]));
                }
                if (!is_null($item["rel_mss_other"]) && strlen($item["rel_mss_other"]) != 0) {
                    $buffer["extern"][] = array("name" => $item["rel_mss_other"]);
                }
            }
            if (count($buffer)) {
                $retArray[] = $buffer;
            }
        } else {
            if ($download) {
                return "";
            } else {
                return $retArray;
            }
        }


//error_log(print_r($retArray, true));
        if ($download) {
            return $this->flattenRelationArray($retArray);
        } else {
            return $retArray;
        }

    }

    private
    function flattenRelationArray($rels)
    {
        $retArray = array();

        foreach ($rels as $rel) {
            $retArray[] = $rel["reason"];
            foreach ($rel["intern"] as $element) {
                $retArray[] = $element["shelfmark"] . "(" . $element["id"] . ")";
            }
            $retArray[] = implode("\n", $rel["extern"]);
        }
        return implode(", ", $retArray);
    }

    private
    function flattenProvenanceArray($prov)
    {
        $retArray = array();

        foreach ($prov as $item) {
            $retArray[] = $item["provenance"];
        }
        return implode(", ", $retArray);
    }

    private
    function getInternRelations($str)
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

    private
    function trexplode($delimmiter, $str)
    {
        $retArray = array();
        $tmpArray = explode($delimmiter, $str);
        foreach ($tmpArray as $element) {
            $retArray[] = trim($element);
        }
        return $retArray;
    }

    private
    function createBibliography($item, $download)
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

    private function createOtherInfo($id, $download) {
        $retArray = array();
        $results = $this->ass_arr(pg_query($this->con, "SELECT * FROM url WHERE m_id = '$id'"));

        if (count($results)) {
            foreach ($results as $item) {
                $retArray = $this->getInfoFromField('fama', $item, $retArray);
                $retArray = $this->getInfoFromField('jordanus', $item, $retArray);
                $retArray = $this->getInfoFromField('mirabileweb', $item, $retArray);
                $retArray = $this->getInfoFromField('trismegistos', $item, $retArray);
                $retArray = $this->getInfoFromField('manuscripta_medica', $item, $retArray);
                $retArray = $this->getInfoFromField('bstk_online', $item, $retArray);
                $retArray = $this->getInfoFromField('dhbm', $item, $retArray);
                $retArray = $this->getInfoFromField('handscriftencensus', $item, $retArray);
            }
        }
        if (!is_null($item["other_links"]) && !is_null($item["label_other_links"])) {
            $retArray[] = array("url" => $item["other_links"], "label" => "(" . $item["label_other_links"] . ")");
        }

        if ($download) {
            return $this->flattenOtherInfo($retArray);
        } else {
            return $retArray;
        }
    }

    private function flattenOtherInfo($array) {
        $retArray = array();

        foreach ($array as $item) {
            $retArray = implode(" ", $item);
        }
        return implode("\n", $retArray);
    }

    private function getInfoFromField($field, $item, $array) {
        $retArray = $array;
        if ($item[$field] != "" && !is_null($item[$field])) {
            $retArray[] = array("url" => $item[$field], "label" => $this->getInfoLabel($field));
        }

        return $retArray;
    }

    private function getInfoLabel($field) {
        $retStr = "";

        switch ($field) {
            case "fama":
                $retStr = "(FAMA)";
                break;
            case "jordanus":
                $retStr = "(Jordanus)";
                break;
            case "mirabileweb":
                $retStr = "(Mirabileweb)";
                break;
            case "trismegistos":
                $retStr = "(Trismegistos)";
                break;
            case "manuscripta_medica":
                $retStr = "(Manuscripta medica)";
                break;
            case "bstk_online":
                $retStr = "(BSTK Online)";
                break;
            case "dhbm":
                $retStr = "(DHBM)";
                break;
            case "handscriftencensus":
                $retStr = "(Handscriftencensus)";
                break;
            default:
                $retStr = "Unknown";
        }
        return $retStr;
    }

    private
    function createDigitalVersions($id, $download)
    {
        $retArray = array();
        $results = $this->ass_arr(pg_query($this->con, "SELECT url_images as other_links, label as label_other_links FROM url WHERE m_id='$id'"));
        if (count($results)) {
            foreach ($results as $item) {
                $retArray[] = array("other_links" => $item["other_links"], "label" => $item["label_other_links"]);
            }
        }

        if ($download) {
            return  $this->flattenDigitalVersions($retArray);
        } else {
            return $retArray;
        }
    }

    private function flattenDigitalVersions($lst) {
        $retArray = array();
        foreach ($lst as $item) {
            $retArray[] = implode(", ", $item);
        }
        return implode("\n", $retArray);
    }

    private
    function getContent($id, $download)
    {
        $results = pg_query($this->con, "SELECT material_type, books_included, string_agg( details, ';') details , string_agg( locations, ';') locations from manuscripts_details_locations WHERE m_id = '$id' GROUP BY material_type, books_included");
        if ($download) {
            return $this->contentDownload($this->ass_arr($results));
        } else {
            $retArray = [];
            foreach ($this->ass_arr($results) as $item) {
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

    private
    function merge($details, $locations)
    {
        $count = count($details);
        $retArray = array();

        for ($i = 0; $i < $count; $i++) {
            $buffer = array();
            $buffer["details"] = $details[$i];
            $buffer["locations"] = $locations[$i];
            $retArray[] = $buffer;
        }
        return $retArray;
    }

    private
    function contentDownload($arr)
    {
        $retArr = array();
        foreach ($arr as $element) {
            $retArr[] = implode(", ", array_values($element));
        }
        return implode("\n", $retArr);
    }

    private
    function getContentType($id)
    {
        $results = $this->ass_arr(pg_query($this->con, "select ct.content_type from manuscripts_content_types mt, content_types ct WHERE mt.m_id = '$id' AND mt.type_id = ct.type_id"));
        if (count($results)) {
            return $results[0]["content_type"];
        } else {
            return "";
        }
    }

    private
    function get_place($id)
    {
        $results = pg_query($this->con, "SELECT ap.place_absolute  FROM absolute_places ap, manuscripts_absolute_places m WHERE m.m_id = '$id' AND m.place_id = ap.place_id");
        $items = $this->ass_arr($results);
        if (count($items)) {
            return $items[0]["place_absolute"];
        } else {
            return "-";
        }
    }

    private
    function get_certainty($id)
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
        //error_log($json_struc);
        $ch = curl_init(ELASTIC_HOST);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $options);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_struc);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    private
    function ass_arr($results)
    {
        $retArray = array();
        while ($row = pg_fetch_assoc($results)) {
            $retArray[] = $row;
        }
        return $retArray;
    }
}