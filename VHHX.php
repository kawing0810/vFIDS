<?php
error_reporting(0);

include "CRT_config.php";

$target_icao = "VHHX";
$target_icao = "EGLL";
$target_css = $target_icao;

if(isset($_REQUEST['icao'])) {
    $target_icao = $_REQUEST['icao'];
}
if(isset($_REQUEST['css'])) {
    $target_css = $_REQUEST['css'];
}
if(isset($_REQUEST['tz'])) {
    $target_tz = $_REQUEST['tz'];
}
if(isset($_REQUEST['refresh'])) {
    $refresh_time = $_REQUEST['refresh'];
}
if(isset($_REQUEST['lang'])) {
    $default_lang = $_REQUEST['lang'];
}
if(isset($_REQUEST['switch'])) {
    $lang_switch = $_REQUEST['switch'];
}
if(isset($_REQUEST['pageSize'])) {
    $page_size = $_REQUEST['pageSize'];
}
if(isset($_REQUEST['pageTime'])) {
    $page_switch = $_REQUEST['pageTime'];
}
?>
<!DOCTYPE html>
<head>
<title>Simple VATBoard</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://unpkg.com/bootstrap-table@1.16.0/dist/bootstrap-table.min.css">
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" crossorigin="anonymous">
<link href="https://cdn.jsdelivr.net/gh/gitbrent/bootstrap4-toggle@3.6.1/css/bootstrap4-toggle.min.css" rel="stylesheet">
<?php
$localStyleSheet = $target_css .".css";
if (!file_exists($localStyleSheet)) {
    $localStyleSheet = "vatsim.css";
}
echo <<<ENDHTML
<link href="$localStyleSheet" rel="stylesheet">
ENDHTML;
?>
<style>
/*
https://fonts.google.com/specimen/PT+Mono?category=Monospace#standard-styles
PT Mono
*/
@font-face {
    font-family: 'PTMono';
    src: url('fonts/PTMono-Regular.ttf');
}
body { font-family:PTMono; }
.tvframe { z-index:1000; position:fixed; background-color:transparent; top:0px; left:0px; width:100%; padding-top:75%; background-image:url(oldtv.png); background-size: cover; background-repeat:no-repeat; }
table { position:fixed; width:90%; top:0px; left:0px; margin:5% 5%; }

.crt::before {
    content: " ";
    display: block;
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    right: 0;
    background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.25) 50%), linear-gradient(90deg, rgba(255, 0, 0, 0.06), rgba(0, 255, 0, 0.02), rgba(0, 0, 255, 0.06));
    z-index: 2;
    background-size: 100% 2px, 3px 100%;
    pointer-events: none;
}
.table thead th {
    border-bottom:0px;
}
</style>
</head>

<body>
<div class="tvframe crt"></div>
<table class="table <?php echo $default_lang; ?>" id="table" cellspacing="0" cellpadding="0">
    <thead>
        <tr>
            <th class="lang" data-lang="DEPARTURE" data-lang-init="zh,en" colspan="3" style="text-align:left;">Departure</th>
            <th class="lang" data-lang="TIME" data-lang-init="zh">Time</th>
            <th class="lang" data-lang="STATUS" data-lang-init="zh">Status</th>
        </tr>
        <tr>
            <th class="lang" data-lang="FLIGHT" data-lang-init="zh,en">Flight</th>
            <th class="lang" data-lang="DESTINATION" data-lang-init="zh">Destination</th>
            <th class="lang" data-lang="TO" data-lang-init="en">To</th>
            <th class="lang" data-lang="TIME" data-lang-init="en">Time</th>
            <th class="lang" data-lang="STATUS" data-lang-init="en">Status</th>
            <!-- th class="lang" data-lang="STATUS">Status</th -->
        </tr>
    </thead>
    <tbody>
    </tbody>
</table>
</body>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/bootstrap-table@1.16.0/dist/bootstrap-table.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/gh/gitbrent/bootstrap4-toggle@3.6.1/js/bootstrap4-toggle.min.js"></script>
<script type="text/javascript">
$(function() {
    var nLangSwitch = '<?php echo $lang_switch; ?>'; 
    var nDataRefresh = '<?php echo $refresh_time; ?>'; 
    var gLang = <?php echo json_encode($saLang); ?>;
    var rsPilotData = null;
    var rsNextData = null;
    var nPage = 0;
    var nPageSize = 0;
    var nPageMax = 0;
    var nDefaultPageSize = parseInt('<?php echo $page_size; ?>', 10);
    var nPageSwitch = parseInt('<?php echo $page_switch; ?>', 10);
    var tmFlip = null;
    var tmLang = null;

    $.each($('.lang'), function(idx) {
        var el = $(this);
        var key = el.data('lang');
        var init = el.data('lang-init');
        if (key) {
            el.html('');
            if (typeof init != 'undefined' && init != '' ) {
                var sa = init.split(',');
                console.log(sa);
                for(var i=0; i < sa.length; i++) {
                    var lang = sa[i];
                    $('<span/>', {class:lang +'x', html:gLang[lang][key]}).appendTo(el);
                    $('<span/>', {html:'&nbsp;'}).appendTo(el);
                }
            } else {
                $('<span/>', {class:'en', html:gLang['en'][key]}).appendTo(el);
                $('<span/>', {class:'zh', html:gLang['zh'][key]}).appendTo(el);
            }
        }
    });

    function doGetData() {
        $.ajax({
            url: 'vatsim.php',
            data: 'action=getdepart'
                + '&filters=ARRIVED'
                + '&status=extra'
                + '&icao='+ encodeURIComponent('<?php echo $target_icao; ?>') 
                + '&tz='+ encodeURIComponent('<?php echo $target_tz; ?>')
        }).done(function(json) {
            // console.log(json);
            if(json.result=='success' && typeof json.data != 'undefined') {
                cbGetData(json.data);
            }
        });
    }

    function cbGetData(data) {
        var reLogo = /([a-zA-Z]+)?(\d+)/g;
        var refTime = data.refTime;
        var tsRef = new Date(refTime);
        // console.log('ref', refTime, tsRef);
        var rsRecordset = [];
        
        if(typeof data.pilots != 'undefined' && data.pilots.length > 0) {
            console.log('%d pilots data retrieved', data.pilots.length);
            for(var idx in data.pilots) {
                var item = data.pilots[idx];
                var callsign = item['callsign'];
                var icon = "";
                var matched = reLogo.exec(callsign);
                var fromText = callsign;

                var deptime = item['deptime'];
                var enroute_time = item['enroute_time'];
                var eta = item['eta'];
                var sort = parseInt(item['sort'], 10);
                var status = item['status'];
                if(matched!=null && matched.length>=2) {
                    if (matched.length>=2) {
                        icon = "resources/logos/"+ matched[1] +".png";
                    }
                }
                var etaText = eta;
                if(etaText==null) {
                    etaText = '';
                }
                var eta = etaText.replace(":","");
                rsRecordset.push({
                    'eta':eta,
                    'etaText':etaText,
                    'callsign':fromText,
                    'icon':icon,
                    'depart':item['depart'],
                    'departName':item['arrivalName'],
                    'deptime':item['deptime'],
                    'status':status,
                    'sort':sort
                });
            }
            rsRecordset.sort(function(a, b) {
                return a.sort - b.sort;
            });

            rsNextData = rsRecordset;
            if(rsPilotData == null) {
                showTable();
                rsNextData = null;
            }
        } else {
            console.log('no pilot data');
        }
    }

    function updateNextData() {
        rsPilotData = rsNextData;
        // recalculate paging parameters
        var winHeight = $(window).height();
        var rowHeight = Math.ceil($('#table th').outerHeight());
        if ( nDefaultPageSize > 0) {
            nPageSize = nDefaultPageSize;
        } else {
            nPageSize = Math.floor(winHeight / rowHeight) - 1;
        }
        nPageMax = Math.ceil(rsPilotData.length / nPageSize);
        if (nPage < nPageMax) {
        } else {
            nPage = 0;
        }
        rsNextData = null;
        // console.log('rows=', rsPilotData.length, 'size=', nPageSize, 'max=', nPageMax);
    }

    function showTable(tbody) {
        if ( rsNextData != null ) {
            updateNextData();
        }

        var tbody = $('#table > tbody');
        tbody.html('');
        if (rsPilotData==null) {
            return;
        }

        var spos = nPage * nPageSize;
        var epos = spos + nPageSize;
        if(epos > rsPilotData.length) {
            epos = rsPilotData.length;
        }
        console.log('page %d of %d @%d', nPage, nPageMax, nPageSize);
        nPage ++;
        if (nPage >= nPageMax) {
            nPage = 0;
        }

        for(var idx=spos; idx < epos; idx++) {
            var row = rsPilotData[idx];
            var tr = $('<tr/>');

            var tdCallSign = $('<td/>', {html:row.callsign});
            if(typeof row.icon != 'undefined' && row.icon!='') {
            //     $('<img/>', {class:'callsign', src:row.icon}).prependTo(tdCallSign);
            }
            var tdDepartZh = $('<td/>', {html:row.departName});
            $('<div/>', {class:'', html:row.departName['zh']}).appendTo(tdDepartZh);
            var tdDepartEn = $('<td/>', {html:row.departName});
            $('<div/>', {class:'', html:row.departName['en']}).appendTo(tdDepartEn);

            var tdTime = $('<td/>', {html:row.deptime.replace(':', '')});
            // $('<div/>', {class:'time', html:}).appendTo(tdTime);

            var tdStatus = $('<td/>', {html:row.status});
            tdStatus.addClass(row.status.toLowerCase());
            /* if(row.status=='ARRIVED') {
                tdStatus.addClass('arrived');
            } else if(row.status=='DEPARTING') {
                tdStatus.addClass('departing');
            } else if(row.status=='FINALCALL') {
                tdStatus.addClass('finalcall');
            } else {
                tdStatus.html('');
                $('<div/>', {class:'time', html:row.status.replace(':', '')}).appendTo(tdStatus);
                $('<span/>', {class:'en', html:gLang['en']['ESTAT']}).appendTo(tdStatus);
                $('<span/>', {class:'zh', html:gLang['zh']['ESTAT']}).appendTo(tdStatus);
            } */
            appendLang(row.status, tdStatus);

            // change column order here
            tdCallSign.appendTo(tr);
            tdDepartZh.appendTo(tr);
            tdDepartEn.appendTo(tr);
            tdTime.appendTo(tr);
            tdStatus.appendTo(tr);
            tr.appendTo(tbody);
        }
        for(var i=idx; i < spos+nPageSize; i++) {
            var tr = $('<tr/>');
            $('<td/>', {colspan:3, html:"&nbsp;"}).appendTo(tr);
            tr.appendTo(tbody);
        }
    }

    function appendLang(key, parent) {
        if (typeof gLang['en'][key] != 'undefined' || typeof gLang['zh'][key] != 'undefined') {
            parent.html('');
            if (typeof gLang['en'][key] != 'undefined') {
                $('<span/>', {class:'en', html:gLang['en'][key]}).appendTo(parent);
            }
            if (typeof gLang['zh'][key] != 'undefined') {
                $('<span/>', {class:'zh', html:gLang['zh'][key]}).appendTo(parent);
            }
        }
    }

    function setupTimer() {
        if(tmFlip!=null) {
            clearInterval(tmFlip);
            tmFlip = null;
        }
        if(tmLang!=null) {
            clearInterval(tmLang);
            tmLang = null;
        }
        if ( nLangSwitch > 0 ) {
            setInterval(function() {
                // console.log('switch lang');
                $('#table').toggleClass('en');
                $('#table').toggleClass('zh');
            }, nLangSwitch * 1000);
        }
        tmFlip = setInterval(showTable, nPageSwitch * 1000);
    }
    
    setupTimer();

    setInterval(function() {
        doGetData();
    }, nDataRefresh * 1000);

    doGetData();

});
</script>
</html>