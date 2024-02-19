<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use App\Models\GameSessions;
use App\Models\User;

class GameTunnelAPI extends Controller
{
    public function in(Request $request)
    {

    }

    public function out(Request $request)
    {
       
    }

    public function mixed(Request $request)
    {
        $command = $request->command;
        $urlFullUrl = $request->fullUrl();
        $urlReplaceToReal = str_replace(env('APP_URL').'/api/game_tunnel/mixed/booongo/', 'https://box7-stage.betsrv.com/gate-stage1/gs/', $urlFullUrl);
        $url = $urlReplaceToReal;

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $headers = array(
           "Host: box7-stage.betsrv.com",
           "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:100.0) Gecko/20100101 Firefox/100.0",
           "Accept: */*",
           "Accept-Language: en-US,en;q=0.5",
           "Accept-Encoding: gzip, deflate, br",
           "Content-Type: application/json",
           "DNT: 1",
           "Connection: keep-alive",
           "Sec-Fetch-Dest: empty",
           "Sec-Fetch-Mode: cors",
           "Sec-Fetch-Site: cross-site",
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $resp = curl_exec($curl);
        curl_close($curl);

        return response()->json($resp);
    }


    public function pragmaticCurlRespin($data)
    {
        $url = "https://demogamesfree.ppgames.net/gs2c/v3/gameService";

        $response = Http::retry(3, 500, function ($exception, $request) {
                return $exception instanceof ConnectionException;
        })->withBody(
            $data, 'application/x-www-form-urlencoded'
        )->post($url);

        return $response;
    }


    public function pragmaticCurlRequest(Request $request)
    {

        $urlFullUrl = $request->fullUrl();
        $urlReplaceToReal = str_replace(env('APP_URL').'/api', 'https://demogamesfree.ppgames.net', $urlFullUrl);
        $url = $urlReplaceToReal;
        $data = $request->getContent();

        $response = Http::retry(3, 500, function ($exception, $request) {
                return $exception instanceof ConnectionException;
        })->withBody(
            $data, 'application/x-www-form-urlencoded'
        )->post($url);

        return $response;
    }

    public function oldcurl(Request $request)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT_MS, 2000);

        $headers = array(
           "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:100.0) Gecko/20100101 Firefox/100.0",
           "Accept: */*",
           "Content-Type: application/x-www-form-urlencoded",
           "Origin: https://demogamesfree.ppgames.net",
           "Referer: https://demogamesfree.ppgames.net/",
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $data = $request->getContent();

        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        //for debug only!
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $resp = curl_exec($curl);

        curl_close($curl);

    }

    public function pragmaticplaySettingsStateCurl(Request $request) 
    {

        $url = 'https://demogamesfree.pragmaticplay.net/gs2c/saveSettings.do';
        $data = $request->getContent();

        $response = Http::retry(1, 1000, function ($exception, $request) {
                return $exception instanceof ConnectionException;
        })->withBody(
            $data, 'application/x-www-form-urlencoded'
        )->post($url);

        return $response;

    }

    public function pragmaticplayBalanceOnly(Request $request)
    {   
        $realToken = $request['mgckey'];
        $getSession = GameSessions::where('token_original', $realToken)->first();

        if($getSession) {
            $balanceFinal = self::generalizedBalanceCall($getSession->player_id, $getSession->currency) / 100;
            return 'balance='.$balanceFinal;
        }
    }

    public function pragmaticplayMixed(Request $request)
    {   
    
        // Select session id from request, not sure if this is wrong way cause 'isset' on query is php heavy
        if(isset($request['mgckey'])) {
            $realToken = $request['mgckey'];
        } elseif(isset($request['id'])) {
            $realToken = $request['id'];
        } else {
            return $request;
        }
        $getSession = GameSessions::where('token_original', $realToken)->first();
        if($getSession) {
        
        //Execution time, for delaying return if we're too fast (before spin lands on front)
        $time_start = microtime(true);

        //Curl forward to pragmatic server
        $query_string = self::pragmaticCurlRequest($request);

        parse_str($query_string, $q_arr);
        $balanceCallNeeded = true;


        if(isset($q_arr['c'])) {
            if($q_arr['na'] === 's') {
                $betAmount = $q_arr['c'] * $q_arr['l'] * 100;
                $balanceCallNeeded = false;
                $sendBetSignal = self::generalizedBetCall($getSession->player_id, $getSession->currency, $getSession->extra_meta, $betAmount, 0);
                if(is_numeric($sendBetSignal)) {
                    $balanceFinal = $sendBetSignal / 100;
                } else {

                    //Insufficient Balance Error
                    if($sendBetSignal->status() === 402) {
                        $q_arr['balance'] = -1;
                        $q_arr['balance_cash'] = -1;
                        $resp = http_build_query($q_arr);
                        $resp = urldecode($resp);
                        return '-1&balance=-1&balance_cash=-1';
                    } else {
                        Log::notice('Unknown bet processing error occured: '.$request);
                        return 'unlogged';
                    }
                }
            }
        }

        if(isset($q_arr['w'])) {
            $selectWinArgument = $q_arr['w'];
            $winRaw = floatval($selectWinArgument);
            if($winRaw !== '0.00') {

                // Respin on big win, this should be set in presets most likely in database
                // This respin is only done once, that means there is still a chance the respin triggers another win (while unlikely)
                if($winRaw > '2122121.00') {
                    $data = $request->getContent();
                    parse_str($data, $q_arr_request);
                    $q_arr_request['counter'] = $q_arr_request['counter'] + 2;
                    $q_arr_request['index'] = $q_arr_request['index'] + 1;
                    $resp = http_build_query($q_arr_request);
                    $resp = urldecode($resp);            
                    $query_string = self::pragmaticCurlRespin($resp);
                    parse_str($query_string, $q_arr);

                    if(isset($q_arr['w'])) {
                        $selectRespinWin = $q_arr['w'];
                        if(is_numeric($selectRespinWin)) {
                            $winAmount = $selectRespinWin * 100;
                            $balanceFinal = self::generalizedBetCall($getSession->player_id, $getSession->currency, $getSession->extra_meta, 0, $winAmount) / 100;
                        } else {
                            $balanceFinal = self::generalizedBalanceCall($getSession->player_id, $getSession->currency) / 100;
                        }
                        $balanceCallNeeded = false;
                    }
                } else { 
                $winAmount = $selectWinArgument * 100;
                $balanceFinal = self::generalizedBetCall($getSession->player_id, $getSession->currency, $getSession->extra_meta, 0, $winAmount) / 100;
                $balanceCallNeeded = false;
                }
            }
        }

        if($balanceCallNeeded === true) {
            $balanceFinal = self::generalizedBalanceCall($getSession->player_id, $getSession->currency) / 100;
        }

        $q_arr['balance'] = $balanceFinal;
        $q_arr['balance_cash'] = $balanceFinal;

        //generate new query string
        $resp = http_build_query($q_arr);
        $resp = urldecode($resp);
        
        $timestart = number_format(microtime(true) - $time_start, 4);

        if($timestart < config('app.game_delay_trigger')) {
            $delayAdd = (int) config('app.game_delay_extra') * 1000000;
            usleep($delayAdd); // 0.1s sleep/delay added to game if under 0.3s
        }

        return $resp;


        } else {
            return 'unlogged';
            die;
        }
    }

    public function bgamingMixed(Request $request)
    {
        $game = $request->game_slug;
        $realToken = $request->token;
        $command = $request->command;

        $urlFullUrl = $request->fullUrl();
        $urlReplaceToReal = str_replace(env('APP_URL').'/api/game_tunnel/bgaming/', 'https://bgaming-network.com/api/', $urlFullUrl);
        $url = $urlReplaceToReal;

        Log::debug($urlReplaceToReal);
        $data = $request->getContent();
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Accept: application/json'));
        curl_setopt($curl, CURLOPT_POST, 1); 
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $resp = curl_exec($curl);
        curl_close($curl);
        Log::debug('Response from BGAMING '.$request->command.' method: '.$resp);


        $data_origin = json_decode($resp, true);
        $getSession = GameSessions::where('token_original', $realToken)->first();
        if($getSession) {

        if(isset($data_origin['api_version'])) {
        if($data_origin['api_version'] === "2"){

            // Init is initial load, though can also be intermediary, when you for example switch tabs or are inactive for a while
            if($request->command === 'init') {
                $data_origin['options']['currency']['code'] = $getSession->currency;
            }

            // Spin bet amount (bet minus) should probably be in front of the actual cURL to bgaming above, but as we don't pay any ggr anyway, we might aswell cancel it afterwards for ease
            if($request->command === 'spin') {
                $betAmount = $data_origin['outcome']['bet'];
                $winAmount = $data_origin['outcome']['win'];

                if(isset($data_origin['flow']['purchased_feature']['name'])) {
                    if($data_origin['flow']['purchased_feature']['name'] === 'freespin_chance') {
                        if($game === 'AlohaKingElvis') {
                            $betAmount = $betAmount * 1.33;
                        }
                        $betAmount = $betAmount * 1.5;
                    }
                    if($data_origin['flow']['purchased_feature']['name'] === 'bonus_chance') {
                        $betAmount = $betAmount * 1.33;
                    }

                    if($data_origin['flow']['purchased_feature']['name'] === 'freespin_and_bonus_chance') {
                        $betAmount = $betAmount * 1.6;
                    }
                }

                //Bonus buy on bgaming api version 2
                if(isset($request['options']['purchased_feature'])) {
                    if($request['options']['purchased_feature'] === "freespin_buy") {
                        if($game === 'ZorroWildHeart') {
                            $bonusBuyPrice = 50;
                        } elseif($game === 'DigDigDigger') {
                            $bonusBuyPrice = 110;
                        } else {
                            $bonusBuyPrice = 100;
                        }


                        $betAmount = $request['options']['bet'] * $bonusBuyPrice;
                        $winAmount = $data_origin['outcome']['win'];
                    }
                }

                $data_origin['options']['currency']['code'] = $getSession->currency;
                $data_origin['balance']['wallet'] = self::generalizedBetCall($getSession->player_id, $getSession->currency, $getSession->gameid, $betAmount, $winAmount);
            }

            if($request->command === 'freespin') {
                $betAmount = $data_origin['outcome']['bet'];
                $winAmount = $data_origin['outcome']['win'];

                $data_origin['options']['currency']['code'] = $getSession->currency;
                $data_origin['balance']['wallet'] = self::generalizedBetCall($getSession->player_id, $getSession->currency, $getSession->gameid, 0, $winAmount);
            }

        } else {
            abort(500, 'BGaming API version not 1 neither api version is 2, new game engine possibly added?');
        }

        $data_origin['balance']['wallet'] = self::generalizedBalanceCall($getSession->player_id, $getSession->currency);
        $data_origin['options']['currency']['code'] = $getSession->currency;

    } else {
        if($request->command === 'init' || $request->command === 'finish') {
            if(isset($data_origin['balance'])) {
                $data_origin['options']['currency']['code'] = $getSession->currency;
                $data_origin['balance'] = self::generalizedBalanceCall($getSession->player_id, $getSession->currency);
            }
        }

        if($request->command === 'spin' || $request->command === 'flip' || $request->command === 'start' || $request->command === 'stop' || $request->command === 'step') {
                
                // heads or tails game
                if($request->command === 'flip') {
                        $betAmount = (int) $request['options']['bet'];
                        $winAmount = 0;

                        if(isset($data_origin['result']['total'])) {
                            $winAmount = $data_origin['result']['total'];
                        }
                        if(isset($data_origin['game']['state'])) {
                            if($data_origin['game']['state'] === 'closed') {
                            $data_origin['balance'] = self::generalizedBetCall($getSession->player_id, $getSession->currency, $getSession->gameid, $betAmount, $winAmount);
                            }
                        }
                }

                // minesweeper xy
                if($request->command === 'start' || $request->command === 'stop' || $request->command === 'step') {
                        $winAmount = 0;
                        $betAmount = 0;

                        if(isset($request['options']['bet'])) {
                            $betAmount = (int) $request['options']['bet'];
                        }
                        if(isset($data_origin['game']['action'])) {
                            if($data_origin['game']['action'] === 'stop') {
                                $winAmount = $data_origin['result'];
                            }

                        }
                        if(isset($data_origin['game']['state'])) {
                            if($data_origin['game']['state'] === 'closed' || $data_origin['game']['action'] === 'start') {
                            $data_origin['balance'] = self::generalizedBetCall($getSession->player_id, $getSession->currency, $getSession->gameid, $betAmount, $winAmount);
                            }
                        }
        
                    $data_origin['balance'] = self::generalizedBalanceCall($getSession->player_id, $getSession->currency);
                    

                    return response()->json($data_origin);                
                }


                // Old BGAMING api, where you can set individual betlines when placing bet (* bet amount per betline)
                if(isset($request['extra_data']) || isset($request['options']['bets'])) {
                        $multiplier = count($request['options']['bets']);
                        $betAmount = (int) ($multiplier * $request['options']['bets']['0']);
                        $winAmount = 0;

                        if(isset($request['options']['buy_feature'])) {
                            $betAmount = $betAmount * 100;
                        } elseif(isset($data_origin['game']['state'])) {
                            if($data_origin['game']['state'] === 'freespin') {
                                $betAmount = 0;
                            }
                        }

                        if(isset($data_origin['result']['total'])) {
                            $winAmount = $data_origin['result']['total'];
                        }
                        //$winAmount = $data_origin['result']['total'];
                        $data_origin['balance'] = self::generalizedBetCall($getSession->player_id, $getSession->currency, $getSession->gameid, $betAmount, $winAmount);

                } elseif(isset($request['options']['skin'])) {
                        $payload = '{"command":"'.$request['command'].'","options":{"bet":'.$request['options']['bet'].', "skin":"'.$request['options']['skin'].'" }}';
                        $multiplier = 1 * $request['options']['bet']; 
                } else {
                        $payload = '{"command":"'.$request['command'].'","options":{"bet":'.$request['options']['bet'].'}}';
                        $multiplier = 1 * $request['options']['bet']; 
                }


        }
        }
    } else {
            abort(404, 'Internal Session not found.');
    }

    return response()->json($data_origin);

    }


    public function generalizedBalanceCall($playerid, $currency, $type = NULL) 
    {
        if($type === NULL) {
            $type = 'internal';
            $player = User::where('id', $playerid)->first();

            if($currency === 'USD') {
                return (int) $retrieveBalance = $player->balance_usd * 100;
            } elseif($currency === 'EUR') {
                return (int) $retrieveBalance = $player->balance_eur * 100;
            } else {
                abort(400, 'balance not supported');            
            }
        } else {
            // Here we will add later on external balance/bet callbacks, outside of own system (for example i have in mind to make 'full api' & 'internal' mode)
            $type = $type;
        }
    }


    public function generalizedBetCall($playerid, $currency, $gameid, $betAmount, $winAmount, $type = NULL) 
    {
        if($type === NULL) {
            $type = 'internal';
            $player = User::where('id', $playerid)->first();

            if($currency === 'USD') {
                $playerCurrentBalance = self::generalizedBalanceCall($playerid, $currency);
                
                // To add error response for insufficient balance on bgaming
                if($betAmount > $playerCurrentBalance) {
                    return response()->json([
                        'error' => 'nobalance'
                    ], 402);
                }

                $processBetCalculation = $playerCurrentBalance - $betAmount;
                $processWinCalculation = $processBetCalculation + $winAmount;
                $transformToOurBalanceFormat = floatval($processWinCalculation / 100);
                $player->update(['balance_usd' => $transformToOurBalanceFormat]);

                return $processWinCalculation;


            } elseif($currency === 'EUR') {
                return (int) $retrieveBalance = $player->balance_eur * 100;
            } else {
                abort(400, 'balance not supported');            
            }
        } else {
            // Here we will add later on external balance/bet callbacks, outside of own system (for example i have in mind to make 'full api' & 'internal' mode)
            $type = $type;
        }
    }
}
