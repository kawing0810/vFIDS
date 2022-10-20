<?php
// error_reporting(E_ALL);
// error_reporting(0);
$bDebug = FALSE;
// $bDebug = TRUE;
include('config.php');
include('utils.php');

$action = "getdata";
$gFilters = "";
$gStatus = "";
// $action = "getdepart";
if(isset($_REQUEST['action'])) {
    $action = $_REQUEST['action'];
}
if(isset($_REQUEST['filters'])) {
    $filterText = $_REQUEST['filters'];
    $gFilters = explode(',', $filterText);
}
if(isset($_REQUEST['status'])) {
    $gStatus = $_REQUEST['status'];
}

$target_timezone = "UTC";
$target_icao = "VHHH";

$target_timezone = "Asia/Hong_Kong"; $target_icao = "EGLL";

if(isset($_REQUEST['icao'])) {
    $target_icao = $_REQUEST['icao'];
}
if(isset($_REQUEST['tz'])) {
    $target_timezone = $_REQUEST['tz'];
}
$result = array('result'=>'failed', 'icao'=>$target_icao, 'tz'=>$target_timezone);
header('content-type: application/json');

if($action=='getdata') {
    $data = getdata($target_icao, $target_timezone, $bDebug);
    if($data!==FALSE) {
        $result['result'] = 'success';
        $result['data'] = $data;
    }
    
} else if ($action=='getdepart') {
    $data = getdepart($target_icao, $target_timezone, $bDebug);
    // print_r($data);
    if($data!==FALSE) {
        $result['result'] = 'success';
        $result['data'] = $data;
    }
}

// echo "-- result --\n"; print_r($result); echo "\n-- result --\n";
try {
    $json = json_encode($result, JSON_UNESCAPED_UNICODE);
    if($json===FALSE) {
        throw new Exception(json_last_error_msg(), json_last_error());
    }
    echo $json;
} catch(Exception $ex) {
    echo "exception="; print_r($ex);
}
exit();
//////////////////////////////////////////////////
function http_get($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, TRUE);
    # curl_setopt($ch, CURLOPT_NOBODY, TRUE); // remove body
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($res, 0, $headerSize);
    $body = substr($res, $headerSize);
    curl_close($ch);
    return $body;
}

function getCustomAirport() {
    global $target_icao;
    $saAirport = array();
    $filename = "Airport.txt";
    if($target_icao=='VHHX') {
        $filename = 'VHHX_Airport.txt';
    }
    $fp = fopen($filename,"rt");
    if ($fp!==FALSE) {
        $fsize = filesize($filename);
        $content = fread($fp, $fsize);
        fclose($fp);
    
        $lines = explode("\n", $content);
        foreach($lines as $line) {
            $line = str_replace("\r", "", $line);
            $items = explode(",", $line);
            if(count($items)>=2) {
                $airport = array('zh'=>$items[1]);
                if(count($items)>=3) {
                    $airport['en'] = $items[2];
                }
                
                $saAirport[$items[0]] = $airport;
            }
        }
    }
    return $saAirport;
}

function getAirportEx() {
    $saAirport = array();

    $zhAirport = getCustomAirport();

    $filename = "airports.csv";
    $fp = fopen($filename,"rt");
    if ($fp!==FALSE) {
        $fsize = filesize($filename);
        $content = fread($fp, $fsize);
        fclose($fp);

        $lineNo = 0;
        $lines = explode("\n", $content);
        foreach($lines as $line) {
            $lineNo ++;
            if ($lineNo == 1) {
                continue; // skip first line
            }
            $items = explode(",", $line);
            if(count($items)>=8) {
                $icao = $items[1];
                if($icao=="") {
                    continue;
                }
                $airport = array(
                    'en'=>$items[3],
                    'lat'=>$items[4],   // latitude
                    'lon'=>$items[5],  // longitude
                    'alt'=>$items[6]    // altitude
                );
                if (isset($zhAirport[$icao])) {
                    if(isset($zhAirport[$icao]['zh'])) {
                        $airport['zh'] = $zhAirport[$icao]['zh'];
                    }
                    if(isset($zhAirport[$icao]['en'])) {
                        $airport['en'] = $zhAirport[$icao]['en'];
                    }
                }
                $saAirport[$icao] = $airport;
            }
        }
    }
    return $saAirport;
}

function getVatSimData($target_arrival, $bDebug=FALSE) {
    $vatData = FALSE;
    
    $url = "https://data.vatsim.net/v3/vatsim-data.json";
    if ($bDebug===FALSE) {
        $res = http_get($url);
    } else {
        $res = file_get_contents("vatsim-data.json");
    }
    if ($res !== FALSE) {
        $data = json_decode($res, TRUE);
        // print_r($data);
        $saAirport = getAirportEx();
        $vatData = array('vat'=>$data, 'airports'=>$saAirport);
    }
    return $vatData;
}

function getdata($target_arrival, $target_timezone, $bDebug=FALSE) {
    $result = FALSE;

    $res = getVatSimData($target_arrival, $bDebug);
    if($res !== FALSE) {
        $data = $res['vat'];
        $saAirport = $res['airports'];

        $refTime = $data['general']['update_timestamp'];

        $arrival_airport = array('lat'=>0, 'lon'=>0, 'alt'=>0);
        if (isset($saAirport[$target_arrival])) {
            $arrival_airport = array(
                'lat'=>$saAirport[$target_arrival]['lat'],
                'lon'=>$saAirport[$target_arrival]['lon'],
                'alt'=>$saAirport[$target_arrival]['alt']
            );
        }
        $flight_data = array();
        foreach($data['pilots'] as $pilot) {
            $callsign = $pilot['callsign'];
            if(isset($pilot['flight_plan'])) {
                $flight_plan = $pilot['flight_plan'];
                $arrival = $flight_plan['arrival'];
                
                if ($arrival !== $target_arrival) {
                    continue;
                }
                
                $departure = $flight_plan['departure'];
                if (isset($saAirport[$departure])) {
                    $depart_airport = array(
                        'lat'=>$saAirport[$departure]['lat'],
                        'lon'=>$saAirport[$departure]['lon'],
                        'alt'=>$saAirport[$departure]['alt']
                    );
                }
                // $airport = getAirportData();
                // $departure = $pilot["planned_depairport"];
                $departName = array('en'=>$departure, 'zh'=>$departure);

                if(isset($saAirport[$departure])) {
                    if ( isset($saAirport[$departure]['en']) && !empty($saAirport[$departure]['en'])) {
                        $departName['en'] = mb_convert_encoding($saAirport[$departure]['en'], 'UTF-8', 'UTF-8');
                    }
                    if ( isset($saAirport[$departure]['zh']) && !empty($saAirport[$departure]['zh'])) {
                        $departName['zh'] = mb_convert_encoding($saAirport[$departure]['zh'], 'UTF-8', 'UTF-8');
                    }
                } else {
                    $pilot["dep_elevation"] = 0;
                    $pilot["dep_name"] = "N/A";
                    $pilot["dep_lat"] = 0;
                    $pilot["dep_lon"] = 0;
                    $pilot["dep_distance"] = 0;
                }

                $pilot['dest_distance'] = $arrival_airport['alt'];

                $depart_distance = number_format((int) distance(
                    $pilot["latitude"],
                    $pilot["longitude"],
                    $depart_airport["lat"],
                    $depart_airport["lon"],
                    "N"
                ), 0, ",", ".");

                $dest_distance = number_format((int) distance(
                    $pilot["latitude"],
                    $pilot["longitude"],
                    $arrival_airport["lat"],
                    $arrival_airport["lon"],
                    "N"
                ), 0, ",", ".");

                $eta = "";
                $sortTime = "";
                
                $groundspeed = 0;
                if (isset($pilot["groundspeed"])) {
                    $groundspeed = (int)$pilot["groundspeed"];
                }

                if ($groundspeed <> 0) {
                    $minutesToDest = (intval(str_replace('.', '', $dest_distance)) / $groundspeed) * 60;
                    $time = getLocalTime($minutesToDest);
                    $eta = $time->format("H:i");
                    $sortTime = $time->format('YmdHi');

                } else {
                    $minutesToDest = 0;
                    $eta = "";
                }

                $enroute_time = $flight_plan['enroute_time'];
                $status = $eta;
                //Replace ETA with Status
                if ($groundspeed < 40 and intval(str_replace('.', '', $depart_distance)) < 10) {
                    // $status = "DEPARTING";
                    $elapsed = intval(substr($enroute_time, 0, 2)) * 60 + intval(substr(2,2,$enroute_time));
                    $time = getLocalTime($elapsed); 
                    $eta = $time->format("H:i");
                    $sortTime = $time->format('YmdHi');
                }
                if ($groundspeed < 40 and intval(str_replace('.', '', $dest_distance)) < 10) {
                    $status = "ARRIVED";
                    $sortTime = "0";
                } else {
                    $status = $eta;
                }

                $deptime = $flight_plan['deptime'];
                $departHour = intval(substr($deptime, 0, 2));
                $departMin = intval(substr($deptime, 2, 2));

                $departTime = getLocalTime(FALSE, $departHour, $departMin);
                $localDepartTime = $departTime->format('Hi');

                $flight_data[] = array(
                    'sort'=>$sortTime,
                    'eta'=>$eta,
                    'callsign'=>$callsign,
                    'latitude'=>$pilot['latitude'],
                    'longitude'=>$pilot['longitude'],
                    'altitude'=>$pilot['altitude'],
                    'departure'=>$departure,
                    'departName'=>$departName,
                    'arrival'=>$flight_plan['arrival'],
                    'deptime_utc'=>$flight_plan['deptime'],
                    'deptime'=>$localDepartTime,
                    'enroute_time'=>$enroute_time,
                    'status'=>$status,
                    'x_dest_distance'=>$dest_distance,
                    'x_minutes_to_dest'=>$minutesToDest
                );
            }
        }

        if (count($flight_data) > 0) {
            $result = array(
                'pilots'=>$flight_data,
                'refTime'=>$refTime
            );
        }
    }
    return $result;
}

function getLocalTime($mod_minutes=FALSE, $set_hour=FALSE, $set_min=FALSE) {
    global $target_timezone;
    $date_utc = new DateTime("now", new DateTimeZone("UTC"));
    if($set_hour!==FALSE && $set_min!==FALSE) {
        $date_utc = $date_utc->setTime($set_hour, $set_min);
    }
    if ($mod_minutes !== FALSE) {
        $date_utc->modify("+" . (int) $mod_minutes . " minutes");
    }
    if (!empty($target_timezone) && strtoupper($target_timezone) != 'UTC') {
        try {
            $tz = new DateTimeZone($target_timezone);
            $date_utc = $date_utc->setTimeZone($tz);
        } catch(Exception $ex) {

        }
    }
    return $date_utc;
}

function getOffsetTime($time) {
    global $target_timezone;
    $date_utc = new DateTime("now", new DateTimeZone("UTC"));

    if (!empty($target_timezone) && strtoupper($target_timezone) != 'UTC') {
        try {
            $tz = new DateTimeZone($target_timezone);
            $time = $time->setTimeZone($tz);
        } catch(Exception $ex) {

        }
    }
    return $time;
}

function getDiffMinutes($time1, $time2) {
    $diff = $time1->diff($time2);
    $minutes = $diff->d * 86400 + $diff->h * 60 + $diff->i;
    /* echo "getDiffMinutes="; 
    echo $time1->format('Y-m-d H:i:s'); echo "/";
    echo $time2->format('Y-m-d H:i:s'); echo "/";
    echo $minutes; echo "\n"; // $diff->format("%h"); echo $diff->format("%m"); */
    return $minutes;
}

function getdepart($target_depart, $target_timezone, $bDebug=FALSE) {
    global $gFilters;
    global $gStatus;
    $result = FALSE;

    $res = getVatSimData($target_depart, $bDebug);
    if($res !== FALSE) {
        $data = $res['vat'];
        $saAirport = $res['airports'];

        // print_r($res);
        // print_r($saAirport);
        $refTime = $data['general']['update_timestamp'];

        $depart_airport = array('lat'=>0, 'lon'=>0, 'alt'=>0);
        if (isset($saAirport[$target_depart])) {
            $depart_airport = array(
                'lat'=>$saAirport[$target_depart]['lat'],
                'lon'=>$saAirport[$target_depart]['lon'],
                'alt'=>$saAirport[$target_depart]['alt']
            );
        }

        $flight_data = array();
        foreach($data['pilots'] as $pilot) {
            $callsign = $pilot['callsign'];
            if(isset($pilot['flight_plan'])) {
                $flight_plan = $pilot['flight_plan'];
                $departure = $flight_plan['departure'];

                if ($departure != $target_depart) {
                    continue;
                }
    
                $localDepartTime = $flight_plan['deptime'];
                $arrival = $flight_plan['arrival'];
                $arrival_airport = array('lat'=>0, 'lon'=>0, 'alt'=>0);
                if (isset($saAirport[$arrival])) {
                    $arrival_airport = array(
                        'lat'=>$saAirport[$arrival]['lat'],
                        'lon'=>$saAirport[$arrival]['lon'],
                        'alt'=>$saAirport[$arrival]['alt']
                    );
                }
                $arrivalName = array('en'=>$arrival, 'zh'=>$arrival);

                if(isset($saAirport[$departure])) {
                    if ( isset($saAirport[$arrival]['en']) && !empty($saAirport[$arrival]['en'])) {
                        $arrivalName['en'] = mb_convert_encoding($saAirport[$arrival]['en'], 'UTF-8', 'UTF-8');
                    }
                    if ( isset($saAirport[$arrival]['zh']) && !empty($saAirport[$arrival]['zh'])) {
                        $arrivalName['zh'] = mb_convert_encoding($saAirport[$arrival]['zh'], 'UTF-8', 'UTF-8');
                    }
                } else {
                    $pilot["dep_elevation"] = 0;
                    $pilot["dep_name"] = "N/A";
                    $pilot["dep_lat"] = 0;
                    $pilot["dep_lon"] = 0;
                    $pilot["dep_distance"] = 0;
                }

                $pilot['dest_distance'] = $arrival_airport['alt'];

                $depart_distance = number_format((int) distance(
                    $pilot["latitude"],
                    $pilot["longitude"],
                    $depart_airport["lat"],
                    $depart_airport["lon"],
                    "N"
                ), 0, ",", ".");

                $dest_distance = number_format((int) distance(
                    $pilot["latitude"],
                    $pilot["longitude"],
                    $arrival_airport["lat"],
                    $arrival_airport["lon"],
                    "N"
                ), 0, ",", ".");

                // $eta = "00:00";
                $sortTime = "";
                $groundspeed = 0;
                if (isset($pilot["groundspeed"])) {
                    $groundspeed = (int)$pilot["groundspeed"];
                }

                if ($groundspeed <> 0) {
                    $minutesToDest = (intval(str_replace('.', '', $dest_distance)) / $groundspeed) * 60;

                    $time = getLocalTime($minutesToDest);
                    $eta = $time->format("H:i");
                    $sortTime = $time->format('YmdHi');

                } else {
                    $minutesToDest = 0;
                    // $eta = "00:00";
                }
                $status = $eta;
                //Replace ETA with Status
                

                $depart_length = intval(str_replace('.', '', $depart_distance));

                $deptime = $flight_plan['deptime'];
                $departHour = intval(substr($deptime, 0, 2));
                $departMin = intval(substr($deptime, 2, 2));

                $departTime = getLocalTime(FALSE, $departHour, $departMin);
                $localDepartTime = $departTime->format('Hi');

                if ($groundspeed < 5 && $depart_length < 10 ) {
                    // $date_utc = new DateTime("now", new DateTimeZone("UTC"));
                    // $departTime = getOffsetTime($date_utc->setTime($departHour, $departMin));
                    // echo "b-"; print_r($time);
                    $eta = $departTime->format("H:i");
                    $sortTime = $departTime->format('YmdHi');
                    // $status = "SCHEDULE";
                    $status = $eta;

                    $now_local = getLocalTime();
                    $diffMinutes = getDiffMinutes($now_local, $departTime);

                    if ( $departTime < $now_local ) {
                        $status = "DELAY";
                    } 
                    // else if ( $gStatus == 'extra' ) {
                    else if ( $diffMinutes < 5 ) {
                            $status = "FINALCALL";
                        } else if ( $diffMinutes < 15 ) {
                            $status = "BOARDING";
                        } else {
                            $status = "";
                        }
                    // }
                    

                } else if (($groundspeed > 5 and $groundspeed < 40) and $depart_length < 10) {
                    $status = "DEPARTING";

                } else if ($groundspeed > 40) {
                    $status = "DEPARTED";
                }
                
                // if ($pilot["groundspeed"] < 40 and intval(str_replace('.', '', $pilot["dest_distance"])) < 10) {
                if ($groundspeed < 40 and intval(str_replace('.', '', $dest_distance)) < 10) {
                    $status = "ARRIVED";
                }

                if ( is_array($gFilters) && in_array($status, $gFilters) ) {
                    continue;
                }

                $flight_data[] = array(
                    'callsign'=>$callsign,
                    'latitude'=>$pilot['latitude'],
                    'longitude'=>$pilot['longitude'],
                    'altitude'=>$pilot['altitude'],
                    'departure'=>$departure,
                    'arrival'=>$flight_plan['arrival'],
                    'arrivalName'=>$arrivalName,
                    'deptime_utc'=>$flight_plan['deptime'],
                    'deptime'=>$localDepartTime,
                    'enroute_time'=>$flight_plan['enroute_time'],
                    'eta'=>$eta,
                    'status'=>$status,
                    'sort'=>$sortTime,
                    'x_groundspeed'=>$groundspeed,
                    'x_depart_distance'=>$depart_distance,
                    'x_dest_distance'=>$dest_distance,
                    'x_minutes_to_dest'=>$minutesToDest,
                    'x_arrival_airport'=>$arrival_airport
                );
            }
        }
        // echo "flight_data = "; print_r($flight_data); echo "\n";
        if (count($flight_data) > 0) {
            $result = array(
                'pilots'=>$flight_data,
                'refTime'=>$refTime
            );
        }
    }
    return $result;
}
?>