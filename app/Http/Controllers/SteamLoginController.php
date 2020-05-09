<?php

namespace App\Http\Controllers;

use Exception;
use vemarun\login\SteamLogin;
use vemarun\Steamtrade\Unirest\Request;

use SteamTotp\SteamTotp;


class SteamLoginController extends Controller
{


    protected $cookies;
    protected $steamid;
    public $time;

    public function __construct()
    {
        $login = self::login();
        $login = json_decode(json_encode($login), true);
        if (!array_key_exists('steamid', $login))
            dd($login);
        $this->steamid = $login['steamid'];
        $this->cookies = $login['cookies'];
        $this->time = time();
    }

    /***************************************************************
     * Method to login to steam and generate cookies
     *
     * @return void
     */
    public static function login()
    {
        $SteamLogin = new SteamLogin(array(
            'username' => 'u_suk6',
            'password' => 'qwertyuiop789#',
            'datapath' => dirname(__FILE__) //path to saving cache files
        ));
        if ($SteamLogin->success) {
            $code = SteamTotp::getAuthCode(config('secrets.shared_secret'));
            $loginData = $SteamLogin->login('', $code);
            return $loginData;
            if ($SteamLogin->error != '') echo $SteamLogin->error;
        } else {
            echo $SteamLogin->error;
        }
    }

    public function getConfirmationKey($tag = 'conf')
    {
        $confirmationCode = SteamTotp::getConfirmationKey(config('secrets.identity_secret'), $this->time, $tag);
        return $confirmationCode;
    }

    /*******************************************************
     * Confirm a trade by trade ID or confirm all pending confirmations
     *
     * @param string $op  can be cancel or allow or conf
     * @return void
     */
    public function confirmTrade($tradeId = '', $op = 'allow')
    {
        $confirmations = $this->fetchConfirmations();
        $status = [];
        if (sizeof($confirmations) > 0) {
            foreach ($confirmations as $confirmation) {
                if (!empty($tradeId)) {
                    if ($confirmation['confOfferId'] == $tradeId) {
                        return $this->_ajaxConfirm($confirmation['confId'], $op);
                    }
                }
                $status[] = $this->_ajaxConfirm($confirmation['confId'], $op);
            }
            return response()->json($status, 200);
        }
        return response()->json("Nothing to confirm", 200);
    }

    protected function _ajaxConfirm($confId, $op)
    {
        $url = 'https://steamcommunity.com/mobileconf/ajaxop?op=' . $op . '&' . $this->generateConfirmationQueryParams($op) . '&cid=' . $confId . '&ck='.$this->getConfirmationKey();
        $response = '';
        try {
            $headers = array('Cookie' => $this->cookies, 'Timeout' => Request::timeout(30));
            $response = Request::post($url, $headers);
            dd($response);
        } catch (\Exception $ex) {
        }
        if (!empty($response)) {
            $json = json_decode(json_encode($response), true);
            return isset($json['success']) && $json['success'];
        }
        return false;
    }

    public function fetchConfirmations()
    {
        $url = $this->generateConfirmationUrl();
        $confirmations = [];
        $response = '';
        $headers = array('Cookie' => $this->cookies, 'Timeout' => Request::timeout(5));
        $response = Request::get($url, $headers)->body;

        if (strpos($response, '<div>Nothing to confirm</div>') === false) {
            $confIdRegex = '/data-confid="(\d+)"/';
            $confKeyRegex = '/data-key="(\d+)"/';
            $confOfferRegex = '/data-creator="(\d+)"/';
            $confDescRegex = '/<div>((Confirm|Trade|Sell)+)<\/div>/';

            preg_match_all($confIdRegex, $response, $confIdMatches);
            preg_match_all($confKeyRegex, $response, $confKeyMatches);
            preg_match_all($confOfferRegex, $response, $confOfferMatches);

            //dd($confIdMatches,$confKeyMatches,$confOfferMatches);

            if (count($confIdMatches) > 0 && count($confKeyMatches) > 0 && count($confOfferMatches) > 0) {
                $checkedConfIds = [];

                for ($i = 0; $i < count($confIdMatches[1]); $i++) {
                    $confId = $confIdMatches[1][$i];

                    if (in_array($confId, $checkedConfIds)) {
                        continue;
                    }

                    $confKey = $confKeyMatches[1][$i];
                    $confOfferId = $confOfferMatches[1][$i];
                    $confirmations[] = [
                        'confId' => $confId,
                        'confKey' => $confKey,
                        'confOfferId' => $confOfferId,
                    ];

                    $checkedConfIds[] = $confId;
                }
            } else {
                throw new Exception('Invalid session');
            }
        }
        return $confirmations;
    }

    public function generateConfirmationUrl($tag = 'conf')
    {
        return 'https://steamcommunity.com/mobileconf/conf?' . $this->generateConfirmationQueryParams($tag);
    }


    public function generateConfirmationQueryParams($tag)
    {
        $time = $this->GetSteamTime();
        return 'p=' . SteamTotp::getDeviceId($this->steamid) . '&a=' . $this->steamid . '&k=' . $this->getConfirmationKey() . '&t=' . $time . '&m=android&tag=' . $tag;
    }

    public function getConfirmationTradeOfferId($confId)
    {
        $url = 'https://steamcommunity.com/mobileconf/details/' . $confId . '?' . $this->generateConfirmationQueryParams('details');
        $response = '';
        try {
            $response = $this->mobileAuth->steamCommunity()->cURL($url);
        } catch (\Exception $ex) {
        }
        if (!empty($response)) {
            $json = json_decode($response, true);
            if (isset($json['success']) && $json['success']) {
                $html = $json['html'];
                if (preg_match('/<div class="tradeoffer" id="tradeofferid_(\d+)" >/', $html, $matches)) {
                    return $matches[1];
                }
            }
        }
        return '0';
    }


    public function GetSteamTime()
    {
        return $this->time + $this->GetTimeDifference();
    }

    public function GetTimeDifference()
    {
        try {
            $response = Request::post('http://api.steampowered.com/ITwoFactorService/QueryTime/v0001', null, ['steamid' => $this->steamid]);
            $json = json_decode($response, true);
            if (isset($json['response']) && isset($json['response']['server_time'])) {
                return (int) $json['response']['server_time'] - time();
            }
        } catch (\Exception $ex) {
        }
        return 0;
    }
}
