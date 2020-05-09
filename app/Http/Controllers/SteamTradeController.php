<?php

namespace App\Http\Controllers;
use vemarun\Steamtrade\SteamTrade;


class SteamTradeController extends Controller
{
    protected $tradableOnly = true;

    protected $steam;
    protected $steamLogin;

    public function __construct()
    {
        $this->steamLogin = app(SteamLoginController::class);
        $this->steam = new SteamTrade();
        $this->set();
    }

    public function set()
    {
        $login = $this->steamLogin::login();
        if (is_array($login))
            $this->steam->setup($login['sessionId'], $login['cookies']);
        else
            return response()->json(['success' => false, 'message' => 'Error while login'], 400);
    }

    public function loadSelfInventory()
    {
        $options = [
            'appId' => 440,
            'contextId' => 2,
            'language' => 'english',
            'tradableOnly' => $this->tradableOnly,
        ];
        $inventory = $this->steam->loadMyInventory($options);

        return response()->json($inventory, 200);
    }

    public function loadTraderInventory()
    {
        $options = [
            'partnerSteamId' => 76561198156852158,
            'appId' => 440,
            'contextId' => 2,
            'language' => 'english',
            'tradeOfferId' => false,
        ];
        $inventory = $this->steam->loadPartnerInventory($options);
        return response()->json($inventory, 200);
    }


    public function makeOffer()
    {
        $options = [
            'partnerSteamId' => "76561198196107719",
            'partnerAccountId' => false,
            'accessToken' => false,
            'itemsFromMe' => [
                [
                    "appid" => 440,
                    "contextid" => 2,
                    "amount" => 1,
                    "assetid" => "6754806006"
                ],
                [
                    "appid" => 440,
                    "contextid" => 2,
                    "amount" => 1,
                    "assetid" => "6770774833"
                ],
            ],
            'itemsFromThem' => [],
            'message' => 'trade_1',
            'counteredTradeOffer' => false,
        ];
        $offer = $this->steam->makeOffer($options);
        $offer = json_decode(json_encode($offer), true);
        if (array_key_exists('tradeofferid', $offer)) {
            return $this->$this->steamLogin->confirmTrade($offer['tradeofferid']);
        }
        return response()->json($offer, 200);
    }

    public function confirmTrade($tradeId = '')
    {
        return $this->steamLogin->confirmTrade($tradeId, 'allow');
    }
}
