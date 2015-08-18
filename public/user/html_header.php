<!DOCTYPE html>
<html lang="en-us">
<head>
    <title>Dashboard</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Store geographical data and make online maps"/>
    <meta name="keywords" content="GIS, geographical data, maps, web mapping, shape file, GPX, MapInfo, WMS, OGC"/>
    <meta name="author" content="Martin Hoegh"/>

    <!--[if lt IE 9]>
    <script src="https://html5shim.googlecode.com/svn/trunk/html5.js"></script>
    <link href="/css/banner-ie.css" rel="stylesheet">
    <![endif]-->

    <script src="https://code.jquery.com/jquery-2.1.4.min.js"></script>

    <!-- Elasticsearch -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/elasticsearch/5.0.0/elasticsearch.jquery.min.js"></script>

    <!-- HighCharts -->
    <script src="https://code.highcharts.com/highcharts.js"></script>

    <!-- Bootstrap -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>

    <script src="/js/jquery-placeholder/jquery.placeholder.js"></script>
    <script src="/js/jqote2/jquery.jqote2.js"></script>

    <link rel="stylesheet" href="/js/bootstrap3/css/bootstrap.css" type="text/css">
    <link rel="stylesheet" href="/css/banner.css" type="text/css">
    <link rel="StyleSheet" href="/css/proximanova.css" type="text/css"/>
    <link rel="StyleSheet" href="/css/dashboard.css" type="text/css"/>
    <script>
        $(function () {
            $('input, textarea').placeholder();

        });
    </script>
    <script type="text/template" id="widgetTemplate">


                <div class="btn-toolbar pull-right" role="toolbar" aria-label="" style="margin-top: 15px">
                    <div class="btn-group" data-toggle="buttons">
                        <button type="button" class="refresh btn btn-xs btn-default">
                            <span class="glyphicon glyphicon-refresh" aria-hidden="true"></span>
                            Refresh
                        </button>
                        <label class="btn btn-xs btn-default">
                            <input type="checkbox" class="auto-refresh" autocomplete="off"> Auto (5s)
                        </label>
                    </div>
                    <div class="btn-group" data-toggle="buttons">
                        <label class="btn btn-xs btn-default active">
                            <input class="range" type="radio" value="hour" checked /> Hour
                        </label>
                        <label class="btn btn-xs btn-default">
                            <input class="range" type="radio" value="day" /> Day
                        </label>
                        <label class="btn btn-xs btn-default">
                            <input class="range" type="radio" value="week" /> Week
                        </label>
                        <label class="btn btn-xs btn-default">
                            <input class="range" type="radio" value="month" /> Month
                        </label>
                    </div>
                </div>
                <div class="graph" style="height:360px;width:758px"></div>


    </script>
    <script>
        /* eslint-env browser */
        /* global jQuery */

        (function( $ ) {

            var AUTODELAY = 5000;
            var ID = 0;

            function makeElasticSearchParams(range, pattern) {
                var subtract;
                var interval;
                switch (range) {
                    case 'hour':
                        subtract = '1h';
                        interval = 'minute';
                        break;
                    case 'day':
                        subtract = '1d';
                        interval = 'hour';
                        break;
                    case 'week':
                        subtract = '1w';
                        interval = 'day';
                        break;
                    case 'month':
                        subtract = '1M';
                        interval = 'day';
                        break;
                    default:
                        throw 'Unkown range type';
                }

                return {
                    index: 'logstash-*',
                    body: {
                        'query': {
                            'filtered': {
                                'query': {
                                    "query_string" : {
                                        "default_field" : "request",
                                        "default_operator": "AND",
                                        "query" : pattern
                                    }
                                },
                                'filter': {
                                    'range': {
                                        '@timestamp': {
                                            'gte': 'now-' + subtract,
                                            'lte': 'now'
                                        }
                                    }
                                }
                            }
                        },
                        aggregations: {
                            'histogram': {
                                'date_histogram': {
                                    field: '@timestamp',
                                    interval: interval,
                                    'min_doc_count': 0,
                                    'extended_bounds': {
                                        min: 'now-' + subtract,
                                        max: 'now'
                                    }
                                }
                            }
                        }
                    }
                };
            }

            function makeHighchartsParams(name, data) {
                return {
                    title: false,
                    plotOptions: {
                        line: {
                            animation: false
                        }
                    },
                    xAxis: {
                        type: 'datetime',
                        title: {
                            text: 'Date'
                        }
                    },
                    yAxis: {
                        title: {
                            text: 'Hits'
                        },
                        plotLines: [{
                            value: 0,
                            width: 1,
                            color: '#808080'
                        }]
                    },
                    legend: {
                        enabled: false,
                        layout: 'vertical',
                        align: 'right',
                        verticalAlign: 'middle',
                        borderWidth: 0
                    },
                    series: [{
                        name: name,
                        data: data
                    }]
                };
            }

            $.fn.logstashWidget = function(url, template, name, pattern) {
                return this.each(function() {
                    var id = ID++;
                    var element = $(this);
                    var html = template.html();
                    var autoTimer = null;
                    element.html(html);
                    element.find('.title-text').text(name);
                    element.find('.range').prop('name', 'range' + id);

                    function fetch(range){
                        var params = makeElasticSearchParams(range, pattern);
                        return $.ajax({
                            type: 'POST',
                            url: url,
                            data: JSON.stringify(params),
                            dataType: 'json',
                            contentType: 'application/json'
                        });
                    }

                    function visualize(data){
                        var params = makeHighchartsParams(name, data);
                        element.find('.graph').highcharts(params);
                    }

                    function update(){
                        var range = element.find('.range:checked').val();
                        fetch(range).then(visualize);
                    }
                    function autoupdate(){
                        var enabled = element.find('.auto-refresh').is(':checked');
                        element.find('.refresh').prop('disabled', enabled);
                        if (enabled) {
                            autoTimer = setTimeout(function(){
                                update();
                                autoupdate();
                            }, AUTODELAY);
                        } else if (autoTimer) {
                            clearTimeout(autoTimer);
                            autoTimer = null;
                        }
                    }

                    element.find('.range').on('change', update);
                    element.find('.refresh').on('click', update);
                    element.find('.auto-refresh').on('change', autoupdate);
                    setTimeout(update, 400);
                    //update();
                });
            };

        }( jQuery ));
    </script>
</head>
<body>
<?php include_once("../../../app/conf/analyticstracking.php") ?>
<div id="corner">
    <a href="<?php echo (\app\conf\App::$param['homepage']) ?: "http://www.mapcentia.com/en/geocloud/geocloud.htm"; ?>"></a>
</div>
<div style="position: absolute; right: 5px; top: 3px; z-index: 2">
    <div>
        <?php if (!$_SESSION['auth'] || !$_SESSION['screen_name']) { ?>
            <a href="/user/login">Sign In</a>
        <?php
        } else {
            ?>
            <a href="/user/login/p"><?php echo $_SESSION['screen_name'] ?></a>
            <?php if ($_SESSION['subuser']) echo " ({$_SESSION['subuser']})" ?>
            <?php if (!$_SESSION['subuser']) { ?>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a class="hide-in-basic" href="/user/new">New Sub-User</a>
            <?php } ?>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="/user/edit">Change
                Password</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a target="_blank"
                href="http://mapcentia.screenstepslive.com/">Help</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a
                href="/user/logout">Log Out</a>&nbsp;&nbsp;&nbsp;
        <?php } ?>
    </div>
</div>