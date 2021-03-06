<?php
/**
 * ----------------------------------------------------
 * Created by: PhpStorm.
 * Written by: Camilo Lozano III (Camilo3rd)
 *             www.camilord.com
 *             me@camilord.com
 * Date: 25/02/2018
 * Time: 3:50 PM
 * ----------------------------------------------------
 */

namespace camilord\NZCompaniesRegister;

use camilord\NZCompaniesRegister\Utils\Qurl;

class NZCompaniesRegister
{
    //private $_url = "https://www.business.govt.nz/companies/app/ui/pages/search";
    private $_url = "https://app.companiesoffice.govt.nz/companies/app/ui/pages/search";
    private $_final_url;

    /**
     * @param $keywords
     * @return array
     */
    public function search($keywords) {

        $this->_final_url = $this->_url . '?q='.urlencode($keywords).'&type=entities';
        $data = Qurl::get($this->_final_url, true);

        return $this->parse_raw_search_result($data);

    }

    /**
     * @return mixed
     */
    public function get_url() {
        return $this->_final_url;
    }

    /**
     * @param $data
     * @return array
     */
    private function parse_raw_search_result($data) {

        /**
         * if no data or response from the host,
         * or the server is down as MBIE have a crappy service of this things...
         */
        if ($data === false) {
            return [];
        }

        $processed_data = array();

        $tmp = explode('<ul class="LSTable">', $data);
        $data = explode('<li class="LSRow', $tmp[1]);
        unset($tmp);

        if (is_array($data) && count($data) > 0) {
            foreach ($data as $item) {
                if (!preg_match("/class=\"footer\"/", $item)) {
                    $item = '<li class="LSRow'.$item;

                    if (preg_match("/registryNote/", $item)) {
                        $company_data = $this->parse_with_history_names($item);
                    } else {
                        $company_data = array(
                            'name' => strip_tags($item),
                            'registry_notes' => array()
                        );
                    }
                    $tmp = explode(' - ', $company_data['name']);
                    $company_data['name'] = trim(@$tmp[0]);
                    $company_data['registration_number'] = trim(@$tmp[1]);
                    $company_data['nzbn'] = trim(str_replace('nzbn:', '',  strtolower(@$tmp[2])));

                    if (preg_match("/struck off/", $company_data['nzbn'])) {
                        $company_data['nzbn'] = trim(str_replace("(struck off)", '', $company_data['nzbn']));
                        $company_data['status'] = 'struck off';
                    } else {
                        $company_data['status'] = 'registered';
                    }

                    if (strlen($company_data['name']) > 1) {
                        $processed_data[] = $company_data;
                    }
                }
            }
        }

        return $processed_data;
    }

    /**
     * @param $data
     * @return array
     */
    private function parse_with_history_names($data) {
        $tmp = explode('<div class="registryNote">', $data);
        $company_main_name = strip_tags($tmp[0]);
        $registry_notes = array();
        if (is_array($tmp) && count($tmp) > 1) {
            for ($i = 1; $i < count($tmp); $i++) {
                $registry_notes[] = trim(str_replace(array('(',')','previously known as '), '', strip_tags($tmp[$i])));
            }
        }
        return array(
            'name' => $company_main_name,
            'registry_notes' => $registry_notes
        );
    }
}