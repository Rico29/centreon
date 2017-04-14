<?php
/**
 * Copyright 2005-2017 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

namespace CentreonLegacy\Configuration\Resource;

use \Pimple\Container;
use \CentreonLegacy\Configuration\Resource\Poller;


class Service extends BaseResource
{
    /**
     * @var \CentreonLegacy\Configuration\Resource\Poller
     */
    private $pollerObj;

    /**
     * Service constructor.
     * @param \Pimple\Container $dependencyInjector
     * @param Poller $pollerObj
     */
    public function __construct(\Pimple\Container $dependencyInjector, Poller $pollerObj)
    {
        parent::__construct($dependencyInjector);
        $this->pollerObj = $pollerObj;
    }

    /**
     *  Method that returns service description from service_id
     *
     * @param int $svcId
     * @return string
     */
    public function getDescription($svcId)
    {
        static $svcTab = null;

        if (is_null($svcTab)) {
            $svcTab = array();

            $rq = "SELECT service_id, service_description FROM service";
            $res = $this->dependencyInjector['configuration_db']->query($rq);
            while ($row = $res->fetchRow()) {
                $svcTab[$row['service_id']] = $row['service_description'];
            }
        }
        if (isset($svcTab[$svcId])) {
            return $svcTab[$svcId];
        }
        return null;
    }


    /**
     * Get Service Template ID
     *
     * @param string $templateName
     * @return int
     */
    public function getTemplateId($templateName)
    {

        $templateName = $this->dependencyInjector['configuration_db']->escape($templateName);
        $res = $this->dependencyInjector['configuration_db']->query(
            "SELECT service_id " .
            "FROM service " .
            "WHERE service_description = '" . $templateName . "' " .
            "AND service_register = '0'"
        );
        if (!$res->numRows()) {
            return null;
        }
        $row = $res->fetchRow();
        return $row['service_id'];
    }

    /**
     * @param null $svcDesc
     * @param null $hostName
     * @return mixed|null
     */
    public function getId($svcDesc = null, $hostName = null)
    {
        static $hostSvcTab = array();

        $hostName = $this->dependencyInjector['configuration_db']->escape($hostName);

        if (!isset($hostSvcTab[$hostName])) {
            $rq = "SELECT s.service_id, s.service_description " .
                " FROM service s" .
                " JOIN (SELECT hsr.service_service_id FROM host_service_relation hsr" .
                " JOIN host h" .
                "     ON hsr.host_host_id = h.host_id" .
                "     	WHERE h.host_name = '" . $hostName . "'" .
                "     UNION" .
                "    	 SELECT hsr.service_service_id FROM hostgroup_relation hgr" .
                " JOIN host h" .
                "     ON hgr.host_host_id = h.host_id" .
                " JOIN host_service_relation hsr" .
                "     ON hgr.hostgroup_hg_id = hsr.hostgroup_hg_id" .
                "     	WHERE h.host_name = '" . $hostName . "' ) ghsrv" .
                " ON s.service_id = ghsrv.service_service_id";
            $dbRes = $this->dependencyInjector['configuration_db']->query($rq);
            $hostSvcTab[$hostName] = array();
            while ($row = $dbRes->fetchRow()) {
                $hostSvcTab[$hostName][$row['service_description']] = $row['service_id'];
            }
        }
        if (!isset($svcDesc) && isset($hostSvcTab[$hostName])) {
            return $hostSvcTab[$hostName];
        }
        if (isset($hostSvcTab[$hostName]) && isset($hostSvcTab[$hostName][$svcDesc])) {
            return $hostSvcTab[$hostName][$svcDesc];
        }
        return null;
    }


    /**
     * @param $serviceDesc
     * @param $hgName
     * @return null
     */
    public function getIdFromHostgroup($serviceDesc, $hgName)
    {
        static $hgSvcTab = array();

        if (!isset($hgSvcTab[$hgName])) {

            $hgName = $this->dependencyInjector['configuration_db']->escape($hgName);

            $rq = "SELECT hsr.service_service_id, s.service_description " .
                "FROM host_service_relation hsr, hostgroup hg, service s " .
                "WHERE hsr.hostgroup_hg_id = hg.hg_id " .
                "AND hsr.service_service_id = s.service_id " .
                "AND hg.hg_name LIKE '" . $hgName . "' ";
            $res = $this->dependencyInjector['configuration_db']->query($rq);

            while ($row = $res->fetchRow()) {
                $hgSvcTab[$hgName][$row['service_description']] = $row['service_service_id'];
            }
        }

        if (isset($hgSvcTab[$hgName]) && isset($hgSvcTab[$hgName][$serviceDesc])) {
            return $hgSvcTab[$hgName][$serviceDesc];
        }
        return null;
    }

    /**
     * @param $id
     * @return mixed|null
     */
    public function getName($id)
    {
        static $svcTab = array();

        if (!isset($svcTab[$id])) {
            $query = "SELECT service_alias " .
                "FROM service " .
                "WHERE service_id = " . $this->dependencyInjector['configuration_db']->escape($id);
            $res = $this->dependencyInjector['configuration_db']->query($query);
            if ($res->numRows()) {
                $row = $res->fetchRow();
                $svcTab[$id] = $row['service_alias'];
            }
        }
        if (isset($svcTab[$id])) {
            return $svcTab[$id];
        }
        return null;
    }


    /**
     * Get Service alias
     *
     * @param int $sid
     * @return string
     */
    public function getDescriptions($id = array())
    {
        $arrayReturn = array();

        if (!empty($id)) {
            $where = "";
            foreach ($id as $s) {
                $tmp = explode("_", $s);
                if (isset($tmp[0]) && isset($tmp[1])) {
                    if ($where !== "") {
                        $where .= " OR ";
                    } else {
                        $where .= " AND ( ";
                    }
                    $hostId = $this->dependencyInjector['configuration_db']->escape($tmp[0]);
                    $serviceId = $this->dependencyInjector['configuration_db']->escape($tmp[1]);
                    $where .= " (h.host_id = " . $hostId;
                    $where .= " AND s.service_id = " . $serviceId . " ) ";
                }
            }
            if ($where !== "") {
                $where .= " ) ";
                $query = 'SELECT s.service_description, s.service_id, h.host_name, h.host_id ' .
                    'FROM service s ' .
                    'INNER JOIN host_service_relation hsr ON hsr.service_service_id = s.service_id ' .
                    'INNER JOIN host h ON hsr.host_host_id = h.host_id ' .
                    'WHERE  1 = 1 ' . $where;
                $res = $this->dependencyInjector['configuration_db']->query($query);
                while ($row = $res->fetchRow()) {
                    $arrayReturn[] = array(
                        "service_id" => $row['service_id'],
                        "description" => $row['service_description'],
                        "host_name" => $row['host_name'],
                        "host_id" => $row['host_id']
                    );
                }
            }
        }
        return $arrayReturn;
    }


    /**
     * Check illegal char defined into nagios.cfg file
     *
     * @param string $name
     * @return string
     */
    public function checkIllegalChar($name)
    {
        $dbResult = $this->dependencyInjector['configuration_db']->query(
            "SELECT illegal_object_name_chars FROM cfg_nagios"
        );
        while ($data = $dbResult->fetchRow()) {
            $tab = str_split(html_entity_decode($data['illegal_object_name_chars'], ENT_QUOTES, "UTF-8"));
            foreach ($tab as $char) {
                $name = str_replace($char, "", $name);
            }
        }
        $dbResult->free();
        return $name;
    }


    /**
     * @param $svcId
     * @param $string
     * @param null $antiLoop
     * @param null $instanceId
     * @return mixed
     */
    public function replaceMacroInString($svcId, $string, $antiLoop = null, $instanceId = null)
    {
        $rq = "SELECT service_register FROM service WHERE service_id = '" . $svcId . "' LIMIT 1";
        $dbRes = $this->dependencyInjector['configuration_db']->query($rq);
        if (!$dbRes->numRows()) {
            return $string;
        }
        $row = $dbRes->fetchRow();

        /*
         * replace if not template
         */
        if ($row['service_register'] == 1) {
            if (preg_match('/\$SERVICEDESC\$/', $string)) {
                $string = str_replace("\$SERVICEDESC\$", $this->getServiceDesc($svcId), $string);
            }
            if (!is_null($instanceId) && preg_match("\$INSTANCENAME\$", $string)) {
                $string = str_replace("\$INSTANCENAME\$", $this->pollerObj->getParam($instanceId, 'name'), $string);
            }
            if (!is_null($instanceId) && preg_match("\$INSTANCEADDRESS\$", $string)) {
                $string = str_replace(
                    "\$INSTANCEADDRESS\$",
                    $this->pollerObj->getParam($instanceId, 'ns_ip_address'),
                    $string
                );
            }
        }
        $matches = array();
        $pattern = '|(\$_SERVICE[0-9a-zA-Z\_\-]+\$)|';
        preg_match_all($pattern, $string, $matches);
        $i = 0;
        while (isset($matches[1][$i])) {
            $rq = "SELECT svc_macro_value " .
                "FROM on_demand_macro_service " .
                "WHERE svc_svc_id = '" . $svcId . "' " .
                "AND svc_macro_name LIKE '" . $matches[1][$i] . "'";
            $dbRes = $this->dependencyInjector['configuration_db']->query($rq);
            while ($row = $dbRes->fetchRow()) {
                $string = str_replace($matches[1][$i], $row['svc_macro_value'], $string);
            }
            $i++;
        }
        if ($i) {
            $rq2 = "SELECT service_template_model_stm_id FROM service WHERE service_id = '" . $svcId . "'";
            $dbRes2 = $this->dependencyInjector['configuration_db']->query($rq2);
            while ($row2 = $dbRes2->fetchRow()) {
                if (!isset($antiLoop) || !$antiLoop) {
                    $string = $this->replaceMacroInString(
                        $row2['service_template_model_stm_id'],
                        $string,
                        $row2['service_template_model_stm_id']
                    );
                } elseif ($row2['service_template_model_stm_id'] != $antiLoop) {
                    $string = $this->replaceMacroInString($row2['service_template_model_stm_id'], $string, $antiLoop);
                }
            }
        }
        return $string;
    }


    /**
     * Get list of service templates
     *
     * @return array
     */
    public function getTemplates()
    {
        $res = $this->dependencyInjector['configuration_db']->query(
            "SELECT service_id, service_description " .
            "FROM service " .
            "WHERE service_register = '0' " .
            "ORDER BY service_description"
        );
        $list = array();
        while ($row = $res->fetchRow()) {
            $list[$row['service_id']] = $row['service_description'];
        }
        return $list;
    }

    /**
     * @param $serviceId
     * @param array $macroInput
     * @param array $macroValue
     * @param array $macroPassword
     * @param array $macroDescription
     * @param bool $isMassiveChange
     * @param bool $cmdId
     * @param bool $macroFrom
     */
    public function insertMacro(
        $serviceId,
        $macroInput = array(),
        $macroValue = array(),
        $macroPassword = array(),
        $macroDescription = array(),
        $isMassiveChange = false,
        $cmdId = false,
        $macroFrom = false
    ) {
        $serviceId = $this->dependencyInjector['configuration_db']->escape($serviceId);

        if (false === $isMassiveChange) {
            $this->dependencyInjector['configuration_db']->query(
                "DELETE FROM on_demand_macro_service " .
                "WHERE svc_svc_id = " . $serviceId
            );
        } else {
            $macroList = "";
            foreach ($macroInput as $v) {
                $macroList .= "'\$_SERVICE" .
                    strtoupper($this->dependencyInjector['configuration_db']->escape($v)) . "\$',";
            }
            if ($macroList) {
                $macroList = rtrim($macroList, ",");
                $this->dependencyInjector['configuration_db']->query(
                    "DELETE FROM on_demand_macro_service " .
                    "WHERE svc_svc_id = " . $serviceId . " " .
                    "AND svc_macro_name IN ({$macroList})"
                );
            }
        }

        $stored = array();
        $cnt = 0;
        $macros = $macroInput;
        $macroValues = $macroValue;
        $this->hasMacroFromServiceChanged(
            $this->dependencyInjector['configuration_db'],
            $serviceId,
            $macros,
            $macroValues,
            $cmdId,
            $isMassiveChange,
            $macroFrom
        );
        foreach ($macros as $key => $value) {
            if ($value != "" && !isset($stored[strtolower($value)])) {

                $serviceMacroName = strtoupper($this->dependencyInjector['configuration_db']->escape($value));
                $macroValue = strtoupper($this->dependencyInjector['configuration_db']->escape($macroValues[$key]));
                $macroDescription = $this->dependencyInjector['configuration_db']->escape($macroDescription[$key]);
                $macroDescription = strtoupper($macroDescription);

                $this->dependencyInjector['configuration_db']->query(
                    "INSERT INTO on_demand_macro_service " .
                    "(`svc_macro_name`, `svc_macro_value`, `is_password`, " .
                    "`description`, `svc_svc_id`, `macro_order`) " .
                    "VALUES ('\$_SERVICE" . $serviceMacroName . "\$', '" .
                    $macroValue . "', " . (isset($macroPassword[$key]) ? 1 : 'NULL') .
                    ", '" . $macroDescription . "', " . $serviceId .
                    ", " . $cnt . " )"
                );

                $stored[strtolower($value)] = true;
                $cnt++;
            }
        }
    }

    /**
     * @param null $serviceId
     * @param null $template
     * @return array
     */
    public function getCustomMacroInDb($serviceId = null, $template = null)
    {
        $arr = array();
        $i = 0;
        if ($serviceId) {
            $res = $this->dependencyInjector['configuration_db']->query(
                "SELECT svc_macro_name, svc_macro_value, is_password, description " .
                "FROM on_demand_macro_service " .
                "WHERE svc_svc_id = " . $this->dependencyInjector['configuration_db']->escape($serviceId) . " " .
                "ORDER BY macro_order ASC"
            );
            while ($row = $res->fetchRow()) {
                if (preg_match('/\$_SERVICE(.*)\$$/', $row['svc_macro_name'], $matches)) {
                    $arr[$i]['macroInput_#index#'] = $matches[1];
                    $arr[$i]['macroValue_#index#'] = $row['svc_macro_value'];

                    $valPassword = null;
                    if (isset($row['is_password'])) {
                        if ($row['is_password'] === '1') {
                            $valPassword = '1';
                        } else {
                            $valPassword = null;
                        }
                    }
                    $arr[$i]['macroPassword_#index#'] = $valPassword;

                    $arr[$i]['macroDescription_#index#'] = $row['description'];
                    $arr[$i]['macroDescription'] = $row['description'];
                    if (!is_null($template)) {
                        $arr[$i]['macroTpl_#index#'] = "Service template : " . $template['service_description'];
                    }
                    $i++;
                }
            }
        }
        return $arr;
    }


    /**
     * Get service custom macro
     *
     * @param int $serviceId
     * @return array
     */
    public function getCustomMacro($serviceId = null, $realKeys = false)
    {
        $arr = array();
        $i = 0;
        if (!isset($_REQUEST['macroInput']) && $serviceId) {
            $res = $this->dependencyInjector['configuration_db']->query(
                "SELECT svc_macro_name, svc_macro_value, is_password, description " .
                "FROM on_demand_macro_service " .
                "WHERE svc_svc_id = " . $this->dependencyInjector['configuration_db']->escape($serviceId) . " " .
                "ORDER BY macro_order ASC"
            );
            while ($row = $res->fetchRow()) {
                if (preg_match('/\$_SERVICE(.*)\$$/', $row['svc_macro_name'], $matches)) {
                    $arr[$i]['macroInput_#index#'] = $matches[1];
                    $arr[$i]['macroValue_#index#'] = $row['svc_macro_value'];

                    $valPassword = null;
                    if (isset($row['is_password'])) {
                        if ($row['is_password'] === '1') {
                            $valPassword = '1';
                        } else {
                            $valPassword = null;
                        }
                    }
                    $arr[$i]['macroPassword_#index#'] = $valPassword;

                    $arr[$i]['macroDescription_#index#'] = $row['description'];
                    $arr[$i]['macroDescription'] = $row['description'];
                    $i++;
                }
            }
        } elseif (isset($_REQUEST['macroInput'])) {
            foreach ($_REQUEST['macroInput'] as $key => $val) {
                $index = $i;
                if ($realKeys) {
                    $index = $key;
                }
                $arr[$index]['macroInput_#index#'] = $val;
                $arr[$index]['macroValue_#index#'] = $_REQUEST['macroValue'][$key];

                $valPassword = null;
                if (isset($_REQUEST['is_password'][$key])) {
                    if ($_REQUEST['is_password'][$key] === '1') {
                        $valPassword = '1';
                    } else {
                        $valPassword = null;
                    }
                }
                $arr[$i]['macroPassword_#index#'] = $valPassword;

                $arr[$index]['macroDescription_#index#'] = isset($_REQUEST['description'][$key]) ?
                    $_REQUEST['description'][$key] : null;
                $arr[$index]['macroDescription'] = isset($_REQUEST['description'][$key]) ?
                    $_REQUEST['description'][$key] : null;
                $i++;
            }
        }
        return $arr;
    }


    /**
     * @return array|null
     */
    public function getLockedTemplates()
    {
        static $arr = null;

        if (is_null($arr)) {
            $arr = array();
            $res = $this->dependencyInjector['configuration_db']->query(
                "SELECT service_id " .
                "FROM service " .
                "WHERE service_locked = 1"
            );
            while ($row = $res->fetchRow()) {
                $arr[$row['service_id']] = true;
            }
        }
        return $arr;
    }


    /**
     * @param string $table
     * @param string $host_id_field
     * @param string $service_id_field
     */
    public function cleanRelations($table = "", $host_id_field = "", $service_id_field = "")
    {
        $sql = 'DELETE FROM ' . $table .
            'WHERE NOT EXISTS ( ' .
            '   SELECT hsr1.host_host_id ' .
            '   FROM host_service_relation hsr1 ' .
            '   WHERE hsr1.host_host_id = ' . $table . $host_id_field . ' ' .
            '   AND hsr1.service_service_id = ' . $table . $service_id_field . ' ' .
            ') ' .
            'AND NOT EXISTS ( ' .
            '   SELECT hsr2.host_host_id ' .
            '   FROM host_service_relation hsr2, hostgroup_relation hgr ' .
            '   WHERE hsr2.host_host_id = hgr.host_host_id ' .
            '   AND hgr.host_host_id = ' . $table . $host_id_field . ' ' .
            '   AND hsr2.service_service_id = ' . $table . $service_id_field . ' ' .
            ')';
        $this->dependencyInjector['configuration_db']->query($sql);
    }


    /**
     * @param $service
     * @param int $type
     * @param array $cgSCache
     * @param array $cctSCache
     * @return bool
     */
    public function hasContact($service, $type = 0, $cgSCache = array(), $cctSCache = array())
    {
        static $serviceTemplateHasContactGroup = array();
        static $serviceTemplateHasContact = array();

        if ($type == 0) {
            $staticArr = &$serviceTemplateHasContact;
            $cache = $cctSCache;
        } else {
            $staticArr = &$serviceTemplateHasContactGroup;
            $cache = $cgSCache;
        }

        if (isset($cache[$service['service_id']])) {
            return true;
        }
        while (isset($service['service_template_model_stm_id']) && $service['service_template_model_stm_id']) {
            $serviceId = $service['service_template_model_stm_id'];
            if (isset($cache[$serviceId]) || isset($staticArr[$serviceId])) {
                $staticArr[$serviceId] = true;
                return true;
            }
            $res = $this->dependencyInjector['configuration_db']->query(
                'SELECT service_template_model_stm_id ' .
                'FROM service ' .
                'WHERE service_id = ' . $serviceId
            );
            $service = $res->fetchRow();
        }
        return false;
    }

    /**
     * @param $macroArray
     * @param $form
     * @param $fromKey
     * @param null $macrosArrayToCompare
     */
    public function purgeOldMacroToForm(&$macroArray, &$form, $fromKey, $macrosArrayToCompare = null)
    {
        if (isset($form["macroInput"]["#index#"])) {
            unset($form["macroInput"]["#index#"]);
        }
        if (isset($form["macroValue"]["#index#"])) {
            unset($form["macroValue"]["#index#"]);
        }

        foreach ($macroArray as $key => $macro) {
            if ($macro["macroInput_#index#"] == "") {
                unset($macroArray[$key]);
            }
        }

        if (is_null($macrosArrayToCompare)) {
            foreach ($macroArray as $key => $macro) {
                if ($form['macroFrom'][$key] == $fromKey) {
                    unset($macroArray[$key]);
                }
            }
        } else {
            $inputIndexArray = array();
            foreach ($macrosArrayToCompare as $tocompare) {
                if (isset($tocompare['macroInput_#index#'])) {
                    $inputIndexArray[] = $tocompare['macroInput_#index#'];
                }
            }
            foreach ($macroArray as $key => $macro) {
                if ($form['macroFrom'][$key] == $fromKey) {
                    if (!in_array($macro['macroInput_#index#'], $inputIndexArray)) {
                        unset($macroArray[$key]);
                    }
                }
            }
        }
    }


    /**
     *
     * @param type $pearDB
     * @param integer $service_id
     * @param string $macroInput
     * @param string $macroValue
     * @param boolean $cmdId
     */
    public function setMacroValues(
        $pearDB,
        $serviceId,
        &$macroInput,
        &$macroValue,
        $cmdId = false,
        $isMassiveChange = false,
        $macroFrom = false
    ) {
        $aListTemplate = getListTemplates($pearDB, $serviceId);

        if (!isset($cmdId)) {
            $cmdId = "";
        }

        $aMacros = $this->getMacros($serviceId, $aListTemplate, $cmdId);
        foreach ($aMacros as $macro) {
            foreach ($macroInput as $ind => $input) {
                # Don't override macros on massive change if there is not direct inheritance
                if (($input == $macro['macroInput_#index#'] && $macroValue[$ind] == $macro["macroValue_#index#"])
                    || ($isMassiveChange && $input == $macro['macroInput_#index#'] &&
                        isset($macroFrom[$ind]) && $macroFrom[$ind] != 'direct')
                ) {
                    unset($macroInput[$ind]);
                    unset($macroValue[$ind]);
                }
            }
        }
    }

    /**
     * @param $form
     * @param $fromKey
     * @return array
     */
    public function getMacroFromForm($form, $fromKey)
    {
        $Macros = array();
        if (!empty($form['macroInput'])) {
            foreach ($form['macroInput'] as $key => $macroInput) {
                if ($form['macroFrom'][$key] == $fromKey) {
                    $macroTmp = array();
                    $macroTmp['macroInput_#index#'] = $macroInput;
                    $macroTmp['macroValue_#index#'] = $form['macroValue'][$key];
                    $macroTmp['macroPassword_#index#'] = isset($form['is_password'][$key]) ? 1 : null;
                    $macroTmp['macroDescription_#index#'] = isset($form['description'][$key])
                        ? $form['description'][$key]
                        : null;
                    $macroTmp['macroDescription'] = isset($form['description'][$key])
                        ? $form['description'][$key]
                        : null;
                    $Macros[] = $macroTmp;
                }
            }
        }
        return $Macros;
    }


    /**
     * This method get the macro attached to the service
     *
     * @param int $iServiceId
     * @param array $aListTemplate
     * @param int $iIdCommande
     *
     * @return array
     */
    public function getMacros($iServiceId, $aListTemplate, $iIdCommande, $form = array())
    {

        $macroArray = $this->getCustomMacroInDb($iServiceId);
        $macroArray = array_merge($macroArray, $this->getMacroFromForm($form, "direct"));

        $aMacroInService = $this->getMacroFromForm($form, "fromService");
        //Get macro attached to the host

        // clear current template/service from the list.
        unset($aListTemplate[count($aListTemplate) - 1]);
        foreach ($aListTemplate as $template) {
            if (!empty($template)) {
                $aMacroTemplate[] = $this->getCustomMacroInDb($template['service_id'], $template);
            }
        }
        $aMacroTemplate[] = $this->getMacroFromForm($form, "fromTpl");

        $templateName = "";
        if (empty($iIdCommande)) {
            foreach ($aListTemplate as $template) {
                if (!empty($template['command_command_id'])) {
                    $iIdCommande = $template['command_command_id'];
                    $templateName = "Service template : " . $template['service_description'] . " | ";
                }
            }
        }

        //Get macro attached to the command
        if (!empty($iIdCommande)) {
            $oCommand = new CentreonCommand($this->db);
            $macroTmp = $oCommand->getMacroByIdAndType($iIdCommande, 'service');
            foreach ($macroTmp as $tmpmacro) {
                $tmpmacro['macroTpl_#index#'] = $templateName . ' Commande : ' . $tmpmacro['macroCommandFrom'];
                $aMacroInService[] = $tmpmacro;
            }
        }

        //filter a macro
        $aTempMacro = array();
        if (count($aMacroInService) > 0) {
            for ($i = 0; $i < count($aMacroInService); $i++) {
                $aMacroInService[$i]['macroOldValue_#index#'] = $aMacroInService[$i]["macroValue_#index#"];
                $aMacroInService[$i]['macroFrom_#index#'] = 'fromService';
                $aMacroInService[$i]['source'] = 'fromService';
                $aTempMacro[] = $aMacroInService[$i];
            }
        }

        if (count($aMacroTemplate) > 0) {
            foreach ($aMacroTemplate as $key => $macr) {
                foreach ($macr as $mm) {
                    $mm['macroOldValue_#index#'] = $mm["macroValue_#index#"];
                    $mm['macroFrom_#index#'] = 'fromTpl';
                    $mm['source'] = 'fromTpl';
                    $aTempMacro[] = $mm;
                }
            }
        }

        if (count($macroArray) > 0) {
            foreach ($macroArray as $directMacro) {
                $directMacro['macroOldValue_#index#'] = $directMacro["macroValue_#index#"];
                $directMacro['macroFrom_#index#'] = 'direct';
                $directMacro['source'] = 'direct';
                $aTempMacro[] = $directMacro;
            }
        }

        $aFinalMacro = $this->macroUnique($aTempMacro);

        return $aFinalMacro;
    }

    /**
     * @param $form
     * @return mixed
     */
    public function ajaxMacroControl($form)
    {
        $aMacroInService = array();
        $macroArray = $this->getCustomMacro(null, true);
        $this->purgeOldMacroToForm($macroArray, $form, 'fromTpl');
        $aListTemplate = array();
        if (isset($form['service_template_model_stm_id']) && !empty($form['service_template_model_stm_id'])) {
            $aListTemplate = getListTemplates($this->db, $form['service_template_model_stm_id']);
        }
        //Get macro attached to the template
        $aMacroTemplate = array();

        foreach ($aListTemplate as $template) {
            if (!empty($template)) {
                $aMacroTemplate[] = $this->getCustomMacroInDb($template['service_id'], $template);
            }
        }

        $iIdCommande = $form['command_command_id'];

        $templateName = "";
        if (empty($iIdCommande)) {
            foreach ($aListTemplate as $template) {
                if (!empty($template['command_command_id'])) {
                    $iIdCommande = $template['command_command_id'];
                    $templateName = "Service template : " . $template['service_description'] . " | ";
                }
            }
        }

        //Get macro attached to the command
        if (!empty($iIdCommande)) {
            $oCommand = new CentreonCommand($this->db);

            $macroTmp = $oCommand->getMacroByIdAndType($iIdCommande, 'service');
            foreach ($macroTmp as $tmpmacro) {
                $tmpmacro['macroTpl_#index#'] = $templateName . ' Commande : ' . $tmpmacro['macroCommandFrom'];
                $aMacroInService[] = $tmpmacro;
            }
        }

        $this->purgeOldMacroToForm($macroArray, $form, 'fromService');

        //filter a macro
        $aTempMacro = array();

        if (count($aMacroInService) > 0) {
            for ($i = 0; $i < count($aMacroInService); $i++) {
                $aMacroInService[$i]['macroOldValue_#index#'] = $aMacroInService[$i]["macroValue_#index#"];
                $aMacroInService[$i]['macroFrom_#index#'] = 'fromService';
                $aMacroInService[$i]['source'] = 'fromService';
                $aTempMacro[] = $aMacroInService[$i];
            }
        }

        if (count($aMacroTemplate) > 0) {
            foreach ($aMacroTemplate as $key => $macr) {
                foreach ($macr as $mm) {
                    $mm['macroOldValue_#index#'] = $mm["macroValue_#index#"];
                    $mm['macroFrom_#index#'] = 'fromTpl';
                    $mm['source'] = 'fromTpl';
                    $aTempMacro[] = $mm;
                }
            }
        }

        if (count($macroArray) > 0) {
            foreach ($macroArray as $key => $directMacro) {
                $directMacro['macroOldValue_#index#'] = $directMacro["macroValue_#index#"];
                $directMacro['macroFrom_#index#'] = $form['macroFrom'][$key];
                $directMacro['source'] = 'direct';
                $aTempMacro[] = $directMacro;
            }
        }

        $aFinalMacro = $this->macroUnique($aTempMacro);

        return $aFinalMacro;
    }


    /**
     *
     * @param integer $field
     * @return array
     */
    public static function getDefaultValuesParameters($field)
    {
        $parameters = array();
        $parameters['currentObject']['table'] = 'service';
        $parameters['currentObject']['id'] = 'service_id';
        $parameters['currentObject']['name'] = 'service_description';
        $parameters['currentObject']['comparator'] = 'service_id';

        switch ($field) {
            case 'timeperiod_tp_id':
            case 'timeperiod_tp_id2':
                $parameters['type'] = 'simple';
                $parameters['externalObject']['table'] = 'timeperiod';
                $parameters['externalObject']['id'] = 'tp_id';
                $parameters['externalObject']['name'] = 'tp_name';
                $parameters['externalObject']['comparator'] = 'tp_id';
                break;
            case 'command_command_id':
            case 'command_command_id2':
                $parameters['type'] = 'simple';
                $parameters['externalObject']['table'] = 'command';
                $parameters['externalObject']['id'] = 'command_id';
                $parameters['externalObject']['name'] = 'command_name';
                $parameters['externalObject']['comparator'] = 'command_id';
                break;
            case 'service_template_model_stm_id':
                $parameters['type'] = 'simple';
                $parameters['externalObject']['table'] = 'service';
                $parameters['externalObject']['id'] = 'service_id';
                $parameters['externalObject']['name'] = 'service_description';
                $parameters['externalObject']['comparator'] = 'service_id';
                break;
            case 'service_cs':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['object'] = 'centreonContact';
                $parameters['externalObject']['table'] = 'contact';
                $parameters['externalObject']['id'] = 'contact_id';
                $parameters['externalObject']['name'] = 'contact_name';
                $parameters['externalObject']['comparator'] = 'contact_id';
                $parameters['relationObject']['table'] = 'contact_service_relation';
                $parameters['relationObject']['field'] = 'contact_id';
                $parameters['relationObject']['comparator'] = 'service_service_id';
                break;
            case 'service_cgs':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['object'] = 'centreonContactgroup';
                $parameters['externalObject']['table'] = 'contactgroup';
                $parameters['externalObject']['id'] = 'cg_id';
                $parameters['externalObject']['name'] = 'cg_name';
                $parameters['externalObject']['comparator'] = 'cg_id';
                $parameters['relationObject']['table'] = 'contactgroup_service_relation';
                $parameters['relationObject']['field'] = 'contactgroup_cg_id';
                $parameters['relationObject']['comparator'] = 'service_service_id';
                break;
            case 'service_hPars':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['object'] = 'centreonHost';
                $parameters['externalObject']['table'] = 'host';
                $parameters['externalObject']['id'] = 'host_id';
                $parameters['externalObject']['name'] = 'host_name';
                $parameters['externalObject']['comparator'] = 'host_id';
                $parameters['relationObject']['table'] = 'host_service_relation';
                $parameters['relationObject']['field'] = 'host_host_id';
                $parameters['relationObject']['comparator'] = 'service_service_id';
                break;
            case 'service_hgPars':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['table'] = 'hostgroup';
                $parameters['externalObject']['id'] = 'hg_id';
                $parameters['externalObject']['name'] = 'hg_name';
                $parameters['externalObject']['comparator'] = 'hg_id';
                $parameters['relationObject']['table'] = 'host_service_relation';
                $parameters['relationObject']['field'] = 'hostgroup_hg_id';
                $parameters['relationObject']['comparator'] = 'service_service_id';
                break;
            case 'service_sgs':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['object'] = 'centreonServicegroups';
                $parameters['externalObject']['table'] = 'servicegroup';
                $parameters['externalObject']['id'] = 'sg_id';
                $parameters['externalObject']['name'] = 'sg_name';
                $parameters['externalObject']['comparator'] = 'sg_id';
                $parameters['relationObject']['table'] = 'servicegroup_relation';
                $parameters['relationObject']['field'] = 'servicegroup_sg_id';
                $parameters['relationObject']['comparator'] = 'service_service_id';
                break;
            case 'service_traps':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['table'] = 'traps';
                $parameters['externalObject']['id'] = 'traps_id';
                $parameters['externalObject']['name'] = 'traps_name';
                $parameters['externalObject']['comparator'] = 'traps_id';
                $parameters['relationObject']['table'] = 'traps_service_relation';
                $parameters['relationObject']['field'] = 'traps_id';
                $parameters['relationObject']['comparator'] = 'service_id';
                break;
            case 'graph_id':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['table'] = 'giv_graphs_template';
                $parameters['externalObject']['id'] = 'graph_id';
                $parameters['externalObject']['name'] = 'name';
                $parameters['externalObject']['comparator'] = 'graph_id';
                $parameters['relationObject']['table'] = 'extended_service_information';
                $parameters['relationObject']['field'] = 'graph_id';
                $parameters['relationObject']['comparator'] = 'service_service_id';
                break;
            case 'service_categories':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['table'] = 'service_categories';
                $parameters['externalObject']['id'] = 'sc_id';
                $parameters['externalObject']['name'] = 'sc_name';
                $parameters['externalObject']['comparator'] = 'sc_id';
                $parameters['relationObject']['table'] = 'service_categories_relation';
                $parameters['relationObject']['field'] = 'sc_id';
                $parameters['relationObject']['comparator'] = 'service_service_id';
                break;
        }

        return $parameters;
    }


    /**
     * @param array $values
     * @param array $options
     * @param string $register
     * @return array
     */
    public function getObjectForSelect2($values = array(), $options = array(), $register = '1')
    {
        $hostgroup = false;
        if (isset($options['hostgroup']) && $options['hostgroup'] == true) {
            $hostgroup = true;
        }

        $hostIdList = array();
        $serviceIdList = array();
        foreach ($values as $value) {
            if (strpos($value, '-')) {
                $tmpValue = explode('-', $value);
                $hostIdList[] = $tmpValue[0];
                $serviceIdList[] = $tmpValue[1];
            } else {
                $serviceIdList[] = $value;
            }
        }

        # Construct host filter for query
        $selectedHosts = '';
        if (count($hostIdList) > 0) {
            if ($hostgroup) {
                $selectedHosts .= 'AND hsr.hostgroup_hg_id IN (';
            } else {
                $selectedHosts .= 'AND hsr.host_host_id IN (';
            }
            $implodedValues = implode(',', $hostIdList);
            if (trim($implodedValues) != "") {
                $selectedHosts .= $implodedValues;
            } else {
                $selectedHosts .= "''";
            }
            $selectedHosts .= ") ";
        }

        # Construct service filter for query
        $selectedServices = '';
        $implodedValues = implode(',', $serviceIdList);
        if ((trim($implodedValues)) != "" && (trim($implodedValues) != "-")) {
            $selectedServices .= 'AND hsr.service_service_id IN (';
            $selectedServices .= $implodedValues;
            $selectedServices .= ') ';
        }

        $serviceList = array();
        if (!empty($selectedServices)) {
            if ($hostgroup) {
                $queryService = 'SELECT DISTINCT s.service_description, s.service_id, hg.hg_name, hg.hg_id ' .
                    'FROM hostgroup hg, service s, host_service_relation hsr ' .
                    'WHERE hsr.hostgroup_hg_id = hg.hg_id ' .
                    'AND hsr.service_service_id = s.service_id ' .
                    'AND s.service_register = ' . $register . ' ' .
                    $selectedHosts .
                    $selectedServices .
                    'ORDER BY hg.hg_name ';

                $dbResult = $this->dependencyInjector['configuration_db']->query($queryService);
                while ($data = $dbResult->fetchRow()) {
                    $serviceCompleteName = $data['hg_name'] . ' - ' . $data['service_description'];
                    $serviceCompleteId = $data['hg_id'] . '-' . $data['service_id'];

                    $serviceList[] = array('id' => $serviceCompleteId, 'text' => $serviceCompleteName);
                }
            } else {
                $queryService = 'SELECT DISTINCT s.service_description, s.service_id, h.host_name, h.host_id ' .
                    'FROM host h, service s, host_service_relation hsr ' .
                    'WHERE hsr.host_host_id = h.host_id ' .
                    'AND hsr.service_service_id = s.service_id ' .
                    'AND h.host_register = ' . $register . ' ' .
                    'AND s.service_register = ' . $register . ' ' .
                    $selectedHosts .
                    $selectedServices .
                    'ORDER BY h.host_name ';
                $dbResult = $this->dependencyInjector['configuration_db']->query($queryService);
                while ($data = $dbResult->fetchRow()) {
                    $serviceCompleteName = $data['host_name'] . ' - ' . $data['service_description'];
                    $serviceCompleteId = $data['host_id'] . '-' . $data['service_id'];

                    $serviceList[] = array('id' => $serviceCompleteId, 'text' => $serviceCompleteName);
                }
            }
        }
        return $serviceList;
    }

    /**
     * @param $macroA
     * @param $macroB
     * @param bool $getFirst
     * @return mixed
     */
    private function comparaPriority($macroA, $macroB, $getFirst = true)
    {
        $arrayPrio = array('direct' => 3, 'fromTpl' => 2, 'fromService' => 1);
        if ($getFirst) {
            if ($arrayPrio[$macroA['source']] > $arrayPrio[$macroB['source']]) {
                return $macroA;
            } else {
                return $macroB;
            }
        } else {
            if ($arrayPrio[$macroA['source']] >= $arrayPrio[$macroB['source']]) {
                return $macroA;
            } else {
                return $macroB;
            }
        }
    }

    /**
     * @param $aTempMacro
     * @return array
     */
    public function macroUnique($aTempMacro)
    {
        $storedMacros = array();
        foreach ($aTempMacro as $TempMacro) {
            $sInput = $TempMacro['macroInput_#index#'];
            $storedMacros[$sInput][] = $TempMacro;
        }

        $finalMacros = array();
        foreach ($storedMacros as $key => $macros) {
            $choosedMacro = array();
            foreach ($macros as $macro) {
                if (empty($choosedMacro)) {
                    $choosedMacro = $macro;
                } else {
                    $choosedMacro = $this->comparaPriority($macro, $choosedMacro, false);
                }
            }
            if (!empty($choosedMacro)) {
                $finalMacros[] = $choosedMacro;
            }
        }
        $this->addInfosToMacro($storedMacros, $finalMacros);
        return $finalMacros;
    }

    /**
     * @param $storedMacros
     * @param $finalMacros
     */
    private function addInfosToMacro($storedMacros, &$finalMacros)
    {

        foreach ($finalMacros as &$finalMacro) {
            $sInput = $finalMacro['macroInput_#index#'];
            $this->setInheritedDescription(
                $finalMacro,
                $this->getInheritedDescription($storedMacros[$sInput], $finalMacro)
            );
            switch ($finalMacro['source']) {
                case 'direct':
                    $this->setTplValue($this->findTplValue($storedMacros[$sInput]), $finalMacro);
                    break;
                case 'fromTpl':
                    $this->setTplValue($this->findTplValue($storedMacros[$sInput]), $finalMacro);
                    break;
                case 'fromService':
                    break;
                default:
                    break;
            }

        }
    }

    /**
     * @param $storedMacros
     * @param $finalMacro
     * @return string
     */
    private function getInheritedDescription($storedMacros, $finalMacro)
    {
        $description = "";
        if (empty($finalMacro['macroDescription'])) {
            $choosedMacro = array();
            foreach ($storedMacros as $storedMacro) {
                if (!empty($storedMacro['macroDescription'])) {
                    if (empty($choosedMacro)) {
                        $choosedMacro = $storedMacro;
                    } else {
                        $choosedMacro = $this->comparaPriority($storedMacro, $choosedMacro, false);
                    }
                    $description = $choosedMacro['macroDescription'];
                }
            }
        } else {
            $description = $finalMacro['macroDescription'];
        }
        return $description;
    }

    /**
     * @param $finalMacro
     * @param $description
     */
    private function setInheritedDescription(&$finalMacro, $description)
    {
        $finalMacro['macroDescription_#index#'] = $description;
        $finalMacro['macroDescription'] = $description;
    }

    /**
     * @param $tplValue
     * @param $finalMacro
     */
    private function setTplValue($tplValue, &$finalMacro)
    {

        if ($tplValue !== false) {
            $finalMacro['macroTplValue_#index#'] = $tplValue;
            $finalMacro['macroTplValToDisplay_#index#'] = 1;
        } else {
            $finalMacro['macroTplValue_#index#'] = "";
            $finalMacro['macroTplValToDisplay_#index#'] = 0;
        }
    }

    /**
     * @param $storedMacro
     * @param bool $getFirst
     * @return bool
     */
    private function findTplValue($storedMacro, $getFirst = false)
    {
        if ($getFirst) {
            foreach ($storedMacro as $macros) {
                if ($macros['source'] == 'fromTpl') {
                    return $macros['macroValue_#index#'];
                }
            }
        } else {
            $macroReturn = false;
            foreach ($storedMacro as $macros) {
                if ($macros['source'] == 'fromTpl') {
                    $macroReturn = $macros['macroValue_#index#'];
                }
            }
            return $macroReturn;
        }
        return false;
    }


    /**
     * @param $ret
     * @return mixed
     * @throws \Exception
     */
    public function insert($ret)
    {
        $ret["service_description"] = $this->checkIllegalChar($ret["service_description"]);

        if (isset($ret["command_command_id_arg2"]) && $ret["command_command_id_arg2"] != null) {
            $ret["command_command_id_arg2"] = str_replace("\n", "//BR//", $ret["command_command_id_arg2"]);
            $ret["command_command_id_arg2"] = str_replace("\t", "//T//", $ret["command_command_id_arg2"]);
            $ret["command_command_id_arg2"] = str_replace("\r", "//R//", $ret["command_command_id_arg2"]);
        }
        $rq = 'INSERT INTO service ' .
            '(service_template_model_stm_id, command_command_id, timeperiod_tp_id, command_command_id2, ' .
            'timeperiod_tp_id2, service_description, service_alias, service_is_volatile, service_max_check_attempts, ' .
            'service_normal_check_interval, service_retry_check_interval, service_active_checks_enabled, ' .
            'service_passive_checks_enabled, service_obsess_over_service, service_check_freshness, '.
            'service_freshness_threshold, service_event_handler_enabled, service_low_flap_threshold, ' .
            'service_high_flap_threshold, service_flap_detection_enabled, service_process_perf_data, ' .
            ' service_retain_status_information, service_retain_nonstatus_information, ' .
            'service_notification_interval, service_notification_options, service_notifications_enabled, ' .
            'contact_additive_inheritance, cg_additive_inheritance, service_inherit_contacts_from_host, ' .
            'service_use_only_contacts_from_host, service_stalking_options, service_first_notification_delay, ' .
            'service_comment, command_command_id_arg, command_command_id_arg2, service_register, service_locked, ' .
            'service_activate) ' .
            'VALUES ( ';
        isset($ret["service_template_model_stm_id"]) && $ret["service_template_model_stm_id"] != null
            ? $rq .= "'" . $ret["service_template_model_stm_id"] . "', "
            : $rq .= "NULL, ";
        isset($ret["command_command_id"]) && $ret["command_command_id"] != null
            ? $rq .= "'" . $ret["command_command_id"] . "', "
            : $rq .= "NULL, ";
        isset($ret["timeperiod_tp_id"]) && $ret["timeperiod_tp_id"] != null
            ? $rq .= "'" . $ret["timeperiod_tp_id"] . "', "
            : $rq .= "NULL, ";
        isset($ret["command_command_id2"]) && $ret["command_command_id2"] != null
            ? $rq .= "'" . $ret["command_command_id2"] . "', "
            : $rq .= "NULL, ";
        isset($ret["timeperiod_tp_id2"]) && $ret["timeperiod_tp_id2"] != null
            ? $rq .= "'" . $ret["timeperiod_tp_id2"] . "', "
            : $rq .= "NULL, ";
        isset($ret["service_description"]) && $ret["service_description"] != null
            ? $rq .= "'" . CentreonDB::escape($ret["service_description"]) . "', "
            : $rq .= "NULL, ";
        isset($ret["service_alias"]) && $ret["service_alias"] != null
            ? $rq .= "'" . CentreonDB::escape($ret["service_alias"]) . "', "
            : $rq .= "NULL, ";
        isset($ret["service_is_volatile"]) && $ret["service_is_volatile"]["service_is_volatile"] != 2
            ? $rq .= "'" . $ret["service_is_volatile"]["service_is_volatile"] . "', "
            : $rq .= "'2', ";
        isset($ret["service_max_check_attempts"]) && $ret["service_max_check_attempts"] != null
            ? $rq .= "'" . $ret["service_max_check_attempts"] . "', "
            : $rq .= "NULL, ";
        isset($ret["service_normal_check_interval"]) && $ret["service_normal_check_interval"] != null
            ? $rq .= "'" . $ret["service_normal_check_interval"] . "', "
            : $rq .= "NULL, ";
        isset($ret["service_retry_check_interval"]) && $ret["service_retry_check_interval"] != null
            ? $rq .= "'" . $ret["service_retry_check_interval"] . "', "
            : $rq .= "NULL, ";
        isset($ret["service_active_checks_enabled"]["service_active_checks_enabled"]) &&
        $ret["service_active_checks_enabled"]["service_active_checks_enabled"] != 2
            ? $rq .= "'" . $ret["service_active_checks_enabled"]["service_active_checks_enabled"] . "', "
            : $rq .= "'2', ";
        isset($ret["service_passive_checks_enabled"]["service_passive_checks_enabled"]) &&
        $ret["service_passive_checks_enabled"]["service_passive_checks_enabled"] != 2
            ? $rq .= "'" . $ret["service_passive_checks_enabled"]["service_passive_checks_enabled"] . "', "
            : $rq .= "'2', ";
        isset($ret["service_obsess_over_service"]["service_obsess_over_service"]) &&
        $ret["service_obsess_over_service"]["service_obsess_over_service"] != 2
            ? $rq .= "'" . $ret["service_obsess_over_service"]["service_obsess_over_service"] . "', "
            : $rq .= "'2', ";
        isset($ret["service_check_freshness"]["service_check_freshness"]) &&
        $ret["service_check_freshness"]["service_check_freshness"] != 2
            ? $rq .= "'" . $ret["service_check_freshness"]["service_check_freshness"] . "', "
            : $rq .= "'2', ";
        isset($ret["service_freshness_threshold"]) && $ret["service_freshness_threshold"] != null
            ? $rq .= "'" . $ret["service_freshness_threshold"] . "', "
            : $rq .= "NULL, ";
        isset($ret["service_event_handler_enabled"]["service_event_handler_enabled"]) &&
        $ret["service_event_handler_enabled"]["service_event_handler_enabled"] != 2
            ? $rq .= "'" . $ret["service_event_handler_enabled"]["service_event_handler_enabled"] . "', "
            : $rq .= "'2', ";
        isset($ret["service_low_flap_threshold"]) && $ret["service_low_flap_threshold"] != null
            ? $rq .= "'" . $ret["service_low_flap_threshold"] . "', "
            : $rq .= "NULL, ";
        isset($ret["service_high_flap_threshold"]) && $ret["service_high_flap_threshold"] != null
            ? $rq .= "'" . $ret["service_high_flap_threshold"] . "', "
            : $rq .= "NULL, ";
        isset($ret["service_flap_detection_enabled"]["service_flap_detection_enabled"]) &&
        $ret["service_flap_detection_enabled"]["service_flap_detection_enabled"] != 2
            ? $rq .= "'" . $ret["service_flap_detection_enabled"]["service_flap_detection_enabled"] . "', "
            : $rq .= "'2', ";
        isset($ret["service_process_perf_data"]["service_process_perf_data"]) &&
        $ret["service_process_perf_data"]["service_process_perf_data"] != 2
            ? $rq .= "'" . $ret["service_process_perf_data"]["service_process_perf_data"] . "', "
            : $rq .= "'2', ";
        isset($ret["service_retain_status_information"]["service_retain_status_information"]) &&
        $ret["service_retain_status_information"]["service_retain_status_information"] != 2
            ? $rq .= "'" . $ret["service_retain_status_information"]["service_retain_status_information"] . "', "
            : $rq .= "'2', ";
        isset($ret["service_retain_nonstatus_information"]["service_retain_nonstatus_information"]) &&
        $ret["service_retain_nonstatus_information"]["service_retain_nonstatus_information"] != 2
            ? $rq .= "'" . $ret["service_retain_nonstatus_information"]["service_retain_nonstatus_information"] . "', "
            : $rq .= "'2', ";
        isset($ret["service_notification_interval"]) && $ret["service_notification_interval"] != null
            ? $rq .= "'" . $ret["service_notification_interval"] . "', "
            : $rq .= "NULL, ";
        isset($ret["service_notifOpts"]) && $ret["service_notifOpts"] != null
            ? $rq .= "'" . implode(",", array_keys($ret["service_notifOpts"])) . "', "
            : $rq .= "NULL, ";
        isset($ret["service_notifications_enabled"]["service_notifications_enabled"]) &&
        $ret["service_notifications_enabled"]["service_notifications_enabled"] != 2
            ? $rq .= "'" . $ret["service_notifications_enabled"]["service_notifications_enabled"] . "', "
            : $rq .= "'2', ";
        $rq .= (isset($ret["contact_additive_inheritance"]) ? 1 : 0) . ', ';
        $rq .= (isset($ret["cg_additive_inheritance"]) ? 1 : 0) . ', ';
        isset($ret["service_inherit_contacts_from_host"]["service_inherit_contacts_from_host"]) &&
        $ret["service_inherit_contacts_from_host"]["service_inherit_contacts_from_host"] != null
            ? $rq .= "'" . $ret["service_inherit_contacts_from_host"]["service_inherit_contacts_from_host"] . "', "
            : $rq .= "'NULL', ";
        isset($ret["service_use_only_contacts_from_host"]["service_use_only_contacts_from_host"]) &&
        $ret["service_use_only_contacts_from_host"]["service_use_only_contacts_from_host"] != null
            ? $rq .= "'" . $ret["service_use_only_contacts_from_host"]["service_use_only_contacts_from_host"] . "', "
            : $rq .= "'NULL', ";
        isset($ret["service_stalOpts"]) && $ret["service_stalOpts"] != null
            ? $rq .= "'" . implode(",", array_keys($ret["service_stalOpts"])) . "', "
            : $rq .= "NULL, ";
        isset($ret["service_first_notification_delay"]) && $ret["service_first_notification_delay"] != null
            ? $rq .= "'" . $ret["service_first_notification_delay"] . "', "
            : $rq .= "NULL, ";
        isset($ret["service_comment"]) && $ret["service_comment"] != null
            ? $rq .= "'" . CentreonDB::escape($ret["service_comment"]) . "', "
            : $rq .= "NULL, ";
        $ret['command_command_id_arg'] = $this->getCommandArgs($ret, $ret);
        isset($ret["command_command_id_arg"]) && $ret["command_command_id_arg"] != null
            ? $rq .= "'" . CentreonDB::escape($ret["command_command_id_arg"]) . "', "
            : $rq .= "NULL, ";
        isset($ret["command_command_id_arg2"]) && $ret["command_command_id_arg2"] != null
            ? $rq .= "'" . CentreonDB::escape($ret["command_command_id_arg2"]) . "', "
            : $rq .= "NULL, ";
        isset($ret["service_register"]) && $ret["service_register"] != null
            ? $rq .= "'" . $ret["service_register"] . "', "
            : $rq .= "NULL, ";
        isset($ret["service_locked"]) && $ret["service_locked"] != null
            ? $rq .= "'" . $ret["service_locked"] . "', "
            : $rq .= "0, ";
        isset($ret["service_activate"]["service_activate"]) && $ret["service_activate"]["service_activate"] != null
            ? $rq .= "'" . $ret["service_activate"]["service_activate"] . "'"
            : $rq .= "NULL";
        $rq .= ")";

        try {
            $DBRESULT = $this->db->query($rq);
        } catch (\PDOException $e) {
            throw new \Exception('Error while insert service ' . $ret['service_description']);
        }

        $DBRESULT = $this->db->query("SELECT MAX(service_id) as service_id FROM service");
        $service_id = $DBRESULT->fetchRow();

        $ret['service_service_id'] = $service_id['service_id'];
        $this->insertExtendInfo($ret);

        return $service_id['service_id'];
    }


}
