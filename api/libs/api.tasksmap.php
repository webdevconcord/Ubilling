<?php

/**
 * Tasks manager map-view rendering class
 */
class TasksMap {

    /**
     * Contains all available users data
     *
     * @var array
     */
    protected $allUserData = array();

    /**
     * Contains system maps configuration as key=>value
     *
     * @var array
     */
    protected $mapsCfg = array();

    /**
     * Contains selected year to show
     *
     * @var int
     */
    protected $showYear = '';

    /**
     * Contains selected month to show
     *
     * @var int
     */
    protected $showMonth = '';

    /**
     * Contains default tasks data source table
     *
     * @var string
     */
    protected $dataTable = 'taskman';

    /**
     * Contains count of users without build geo assigned
     *
     * @var int
     */
    protected $noGeoBuilds = 0;

    /**
     * Contains count of users whitch is not present currently in database or tasks without login
     *
     * @var int
     */
    protected $deletedUsers = 0;

    /**
     * Contains count of tasks by period
     *
     * @var int
     */
    protected $tasksExtracted = 0;

    /**
     * Contains available jobtypes
     *
     * @var array
     */
    protected $allJobTypes = array();

    /**
     * System message helper object placeholder
     *
     * @var object
     */
    protected $messages = '';

    /**
     * Creates new report instance
     */
    public function __construct() {
        $this->setDateData();
        $this->initMessages();
        $this->loadMapsConfig();
        $this->loadUsers();
        $this->loadJobTypes();
    }

    /**
     * Loads system maps configuration file
     * 
     * @global object $ubillingConfig
     * 
     * @return void
     */
    protected function loadMapsConfig() {
        global $ubillingConfig;
        $this->mapsCfg = $ubillingConfig->getYmaps();
    }

    /**
     * Inits system message helper object instance for further usage
     * 
     * @return void
     */
    protected function initMessages() {
        $this->messages = new UbillingMessageHelper();
    }

    /**
     * Loads all available jobtypes into protected property
     * 
     * @return void
     */
    protected function loadJobTypes() {
        $this->allJobTypes = ts_GetAllJobtypes();
    }

    /**
     * Loads all users cached data
     * 
     * @return void
     */
    protected function loadUsers() {
        $this->allUserData = zb_UserGetAllDataCache();
    }

    /**
     * Sets selected year/month properties of current as defaults
     * 
     * @return void
     */
    protected function setDateData() {
        if (wf_CheckPost(array('showyear'))) {
            $this->showYear = vf($_POST['showyear'], 3);
        } else {
            $this->showYear = curyear();
        }

        if (wf_CheckPost(array('showmonth'))) {
            $this->showMonth = vf($_POST['showmonth'], 3);
        } else {
            $this->showMonth = date('m');
        }
    }

    /**
     * Returns array of tasks filtered by year/month
     * 
     * @return array
     */
    protected function getPlannedTasks() {
        $monthFilter = ($this->showMonth != '1488') ? $this->showMonth : '';
        $query = "SELECT * from `" . $this->dataTable . "` WHERE `startdate` LIKE '" . $this->showYear . "-" . $monthFilter . "%'";
        $result = simple_queryall($query);
        return ($result);
    }

    /**
     * Returns array of tasks planned for current day
     * 
     * @return array
     */
    public function getTodayTasks() {
        $query = "SELECT * from `" . $this->dataTable . "` WHERE `startdate` LIKE '" . curdate() . "%'";
        $result = simple_queryall($query);
        return($result);
    }

    /**
     * Returns list of formatted placemarks for map rendering
     * 
     * @param array $userTasks
     * 
     * @return string
     */
    public function getPlacemarks($userTasks) {
        $result = '';
        $buildsData = array();
        $buildsCounters = array();
        if (!empty($userTasks)) {
            foreach ($userTasks as $io => $each) {
                if (isset($this->allUserData[$each['login']])) {
                    $userData = $this->allUserData[$each['login']];
                    if (!empty($userData['geo'])) {
                        $taskDate = $each['startdate'];
                        $userLink = '';
                        $userLink .= $taskDate . ': ' . wf_Link('?module=taskman&edittask=' . $each['id'], web_bool_led($each['status']) . ' ' . @$this->allJobTypes[$each['jobtype']]);
                        $userLink = trim($userLink) . ', ';
                        $userLink .= wf_Link('?module=userprofile&username=' . $each['login'], web_profile_icon() . ' ' . $userData['fulladress']);
                        $userLink = trim($userLink);
                        if (!isset($buildsData[$userData['geo']])) {
                            $buildsData[$userData['geo']]['data'] = $userLink;
                            $buildsData[$userData['geo']]['count'] = 1;
                        } else {
                            $buildsData[$userData['geo']]['data'] .= trim(wf_tag('br')) . $userLink;
                            $buildsData[$userData['geo']]['count'] ++;
                        }
                    } else {
                        $this->noGeoBuilds++;
                    }
                } else {
                    $this->deletedUsers++;
                }
                $this->tasksExtracted++;
            }

            if (!empty($buildsData)) {

                foreach ($buildsData as $coords => $usersInside) {
                    if ($usersInside['count'] > 1) {
                        if ($usersInside['count'] > 3) {
                            $placeMarkIcon = 'twirl#redIcon';
                        } else {
                            $placeMarkIcon = 'twirl#yellowIcon';
                        }
                    } else {
                        $placeMarkIcon = 'twirl#lightblueIcon';
                    }
                    $result .= generic_mapAddMark($coords, $usersInside['data'], __('Tasks') . ': ' . $usersInside['count'], '', $placeMarkIcon, '', $this->mapsCfg['CANVAS_RENDER']);
                }
            }
        }
        return ($result);
    }

    /**
     * Returns year/month filtering form
     * 
     * @return string
     */
    public function renderDateForm() {
        $result = '';
        $inputs = wf_YearSelectorPreset('showyear', __('Year'), false, $this->showYear) . ' ';
        $inputs .= wf_MonthSelector('showmonth', __('Month'), $this->showMonth, false, true) . ' ';
        $inputs .= wf_Submit(__('Show'));
        $result .= wf_Form('', 'POST', $inputs, 'glamour');

        return ($result);
    }

    /**
     * Renders report as map
     * 
     * @return string
     */
    public function renderMap() {
        $result = '';
        $allTasks = $this->getPlannedTasks();
        $placemarks = $this->getPlacemarks($allTasks);
        $result .= generic_MapContainer();
        $result .= generic_MapInit($this->mapsCfg['CENTER'], $this->mapsCfg['ZOOM'], $this->mapsCfg['TYPE'], $placemarks, '', $this->mapsCfg['LANG'], 'ubmap');
        return ($result);
    }

    /**
     * Renders deleted users or unknown geo builds stats if they available
     * 
     * @return string
     */
    public function renderStats() {
        $result = '';
        if ($this->tasksExtracted) {
            $result .= $this->messages->getStyledMessage(__('Total tasks') . ': ' . $this->tasksExtracted, 'success');
        }
        if ($this->tasksExtracted AND $this->noGeoBuilds) {
            $result .= $this->messages->getStyledMessage(__('Tasks rendered on map') . ': ' . ($this->tasksExtracted - $this->noGeoBuilds - $this->deletedUsers), 'info');
        }
        if ($this->noGeoBuilds) {
            $result .= $this->messages->getStyledMessage(__('Builds without geo location assigned') . ': ' . $this->noGeoBuilds, 'warning');
        }
        if ($this->deletedUsers) {
            $result .= $this->messages->getStyledMessage(__('Already deleted users or tasks without user') . ': ' . $this->deletedUsers, 'error');
        }

        return ($result);
    }

}
