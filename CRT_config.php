<?php
// error_reporting(E_ALL);
error_reporting(0);

// default language show, en or zh
$default_lang = "en";

// 
$refresh_time = 60;

// default language switch time, default is 30s, use 0 to disable
$lang_switch = 5;

// default timezone, see https://www.php.net/manual/en/timezones.php
$target_timezone = "UTC";

// destination airport, default VHHH 
$target_icao = "VHHH";

// how many rows show in table (use 0 to auto calculate)
$page_size = 15;

// how many seconds switch to next page
$page_switch = 10;

// translation
$saLang = array(
    "en"=>array(
        "FLIGHT"=>"Flight",
        "FROM"=>"From",
        "STATUS"=>"Status",
        "ARRIVED"=>"Arrived",
        "DEPARTING"=>"Departing",
        "DEPARTED"=>"Departed",
        "DELAY"=>"Delay",
        "ESTAT"=>"",
        "DESTINATION"=>"Destination",
        "FINALCALL"=>"Final Call",
        "BOARDING"=>"Boarding",
        "TO"=>"To",
        "TIME"=>"Time",
        "DEPARTURE"=>"Departure",
        "ARRIVAL"=>"Arrival"
    ),
    "zh"=>array(
        "FLIGHT"=>"航班",
        "FROM"=>"出發地",
        "STATUS"=>"現況",
        "ARRIVED"=>"到達",
        "DEPARTING"=>"起飛",
        "DEPARTED"=>"已離場",
        "DELAY"=>"延誤",
        "ESTAT"=>"",
        "DESTINATION"=>"目的地",
        "FINALCALL"=>"最後召集",
        "BOARDING"=>"現正登機",
        "TO"=>"TO",
        "TIME"=>"時間",
        "DEPARTURE"=>"&nbsp;離境",
        "ARRIVAL"=>"到達"
    )
);
?>