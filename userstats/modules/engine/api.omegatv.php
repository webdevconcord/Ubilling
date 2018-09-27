<?php

class OmegaTvFrontend {

    /**
     * Contains available omegatv service tariffs id=>tariffdata
     *
     * @var array
     */
    protected $allTariffs = array();

    /**
     * Contains all tariff names as tariffid=>name
     *
     * @var array
     */
    protected $tariffNames = array();

    /**
     * Contains all of internet users data as login=>data
     *
     * @var array
     */
    protected $allUsers = array();

    /**
     * Contains available and active omegatv service subscriptions as customerid=>data
     *
     * @var array
     */
    protected $allSubscribers = array();

    /**
     * Contains system config as key=>value
     *
     * @var array
     */
    protected $usConfig = array();

    /**
     * Current instance user login
     *
     * @var string
     */
    protected $userLogin = '';

    /**
     * Contains Ubilling RemoteAPI URL
     *
     * @var string
     */
    protected $apiUrl = '';

    /**
     * Contains Ubilling RemoteAPI Key
     *
     * @var string
     */
    protected $apiKey = '';

    public function __construct() {
        $this->loadUsConfig();
        $this->setOptions();
        $this->loadTariffs();
        $this->loadUsers();
        $this->loadUserSubscriptions();
    }

    /**
     * Loads userstats config into protected usConfig variable
     * 
     * @return void
     */
    protected function loadUsConfig() {
        $this->usConfig = zbs_LoadConfig();
    }

    /**
     * Sets required object options
     * 
     * @return void
     */
    protected function setOptions() {
        $this->apiUrl = $this->usConfig['API_URL'];
        $this->apiKey = $this->usConfig['API_KEY'];
    }

    /**
     * Loads available users from database
     * 
     * @return void
     */
    protected function loadUsers() {
        $query = "SELECT * from `users`";
        $all = simple_queryall($query);
        if (!empty($all)) {
            foreach ($all as $io => $each) {
                $this->allUsers[$each['login']] = $each;
            }
        }
    }

    /**
     * Loads existing users profiles/subscriptions
     * 
     * @return void
     */
    protected function loadUserSubscriptions() {
        $query = "SELECT * from `om_users`";
        $all = simple_queryall($query);
        if (!empty($all)) {
            foreach ($all as $io => $each) {
                $this->allSubscribers[$each['customerid']] = $each;
            }
        }
    }

    /**
     * Sets current user login
     * 
     * @return void
     */
    public function setLogin($login) {
        $this->userLogin = $login;
    }

    /**
     * Loads existing tariffs from database
     * 
     * @return void
     */
    protected function loadTariffs() {
        $query = "SELECT * from `om_tariffs` ORDER BY `type` ASC";
        $all = simple_queryall($query);
        if (!empty($all)) {
            foreach ($all as $io => $each) {
                $this->allTariffs[$each['id']] = $each;
                $this->tariffNames[$each['tariffid']] = $each['tariffname'];
            }
        }
    }

    /**
     * Checks is user subscribed for some tariff or not?
     * 
     * @param string $login
     * @param int $tariffid
     * 
     * @return bool
     */
    protected function isUserSubscribed($login, $tariffid) {
        $result = false;
        if (!empty($this->allSubscribers)) {
            $tariffExternalId=  $this->allTariffs[$tariffid]['tariffid'];
            foreach ($this->allSubscribers as $io => $each) {
                if (($each['login'] == $login)) {
                    if ($each['basetariffid'] == $tariffExternalId) {
                        $result = true;
                        break;
                    }
                }
            }
        }
        return ($result);
    }

    /**
     * Returns user login transformed to some numeric hash
     * 
     * @param string $login
     * 
     * @return int
     */
    public function generateCustormerId($login) {
        $result = '';
        if (!empty($login)) {
            $result = crc32($login);
        }
        return($result);
    }

    /**
     * Runs default subscribtion routine
     * 
     * @return void/string on error
     */
    public function pushSubscribeRequest($tariffid) {
        $action = $this->apiUrl . '?module=remoteapi&key=' . $this->apiKey . '&action=mgcontrol&param=subscribe&userlogin=' . $this->userLogin . '&tariffid=' . $tariffid;
        @$result = file_get_contents($action);
        return ($result);
    }

    /**
     * Checks is user protected from his own stupidity?
     * 
     * @param int $tariffId
     * @return bool
     */
    protected function checkUserProtection($tariffId) {
        $tariffId = vf($tariffId, 3);
        $result = true;
        if (isset($this->usConfig['OM_PROTECTION'])) {
            if ($this->usConfig['OM_PROTECTION']) {
                if (isset($this->allTariffs[$tariffId])) {
                    $tariffFee = $this->allTariffs[$tariffId]['fee'];
                    $tariffData = $this->allTariffs[$tariffId];
                    $userData = $this->allUsers[$this->userLogin];
                    $userBalance = $userData['Cash'];

                    if ($tariffData['freeperiod']) {
                        if ($this->checkFreePeriodAvail($this->userLogin)) {
                            $result = true;
                        } else {
                            if ($userBalance < $tariffFee) {
                                $result = false;
                            }
                        }
                    } else {
                        if ($userBalance < $tariffFee) {
                            $result = false;
                        }
                    }
                } else {
                    $result = false;
                }
            }
        }
        return ($result);
    }

    /**
     * Renders tariffs list with subscribtion form
     * 
     * @return string
     */
    public function renderSubscribeForm() {
        $result = '';
        $iconsPath = zbs_GetCurrentSkinPath($this->usConfig) . 'iconz/';

        if (!empty($this->allTariffs)) {

            foreach ($this->allTariffs as $io => $each) {
                $headerType = ($each['type'] == 'base') ? 'mgheaderprimary' : 'mgheader';
                $freeAppend = la_delimiter();
                $tariffFee = $each['fee'];
                $primaryLabel = ($each['type'] == 'base') ? la_img($iconsPath . 'ok_small.png') : la_img($iconsPath . 'unavail_small.png');
                
                $tariffInfo = la_tag('div', false, $headerType) . $each['tariffname'] . la_tag('div', true);
                $cells = la_TableCell(la_tag('b') . __('Fee') . la_tag('b', true));
                $cells.= la_TableCell($tariffFee . ' ' . $this->usConfig['currency']);
                $rows = la_TableRow($cells);
                $cells = la_TableCell(la_tag('b') . __('Primary') . la_tag('b', true));
                $cells.= la_TableCell($primaryLabel);
                $rows.= la_TableRow($cells);
                $tariffInfo.=la_TableBody($rows, '100%', 0);
                $tariffInfo.=$freeAppend;

                if ($this->checkBalance()) {
                    if ($this->isUserSubscribed($this->userLogin, $each['id'])) {
                        $subscribeControl = la_Link('?module=omegatv&unsubscribe=' . $each['id'], __('Unsubscribe'), false, 'mgunsubcontrol');
                    } else {
                        if ($this->checkUserProtection($each['id'])) {
                            $subscribeControl = la_Link('?module=omegatv&subscribe=' . $each['id'], __('Subscribe'), false, 'mgsubcontrol');
                        } else {
                            $subscribeControl = __('The amount of money in your account is not sufficient to process subscription');
                        }
                    }


                    $tariffInfo.=$subscribeControl;
                } else {
                    $tariffInfo.=__('The amount of money in your account is not sufficient to process subscription');
                }

                $result.=la_tag('div', false, 'mgcontainer') . $tariffInfo . la_tag('div', true);
            }
        }
        return ($result);
    }

    /**
     * Check user balance for subscribtion availability
     * 
     * @return bool
     */
    protected function checkBalance() {
        $result = false;
        if (!empty($this->userLogin)) {
            if (isset($this->allUsers[$this->userLogin])) {
                $userData = $this->allUsers[$this->userLogin];
                $userBalance = $this->allUsers[$this->userLogin]['Cash'];
                if ($userBalance >= 0) {
                    $result = true;
                }
            }
        }
        return ($result);
    }

}

?>