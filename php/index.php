<?php
/**
 * TDC Monitoring PHP srouce-code
 * @Author: taivt - toidicode.com
 * @Date: 09/08/2018
 * @version: 1.0.0
 * @support: PHP >=5.4
 * @license MIT
 * @see: You can setup monitor by email in monitor.toidicode.com
 */
if (!empty($_POST['api']) && $_POST['api'] == true) {
    header('content-type: application/json');

    class TDCMonitoring
    {
        /**
         * Version of code
         *
         * @var string
         */
        private $version = '1.0.0';

        /**
         * Path to info uptime file
         *
         * @var string
         */
        private $uptimeFile = '/proc/uptime';

        /**
         * Path to memory file
         *
         * @var string
         */
        private $memoryFile = '/proc/meminfo';

        /**
         * Path to CPU info file
         *
         * @var string
         */
        private $cpuFile = '/proc/cpuinfo';

        /**
         * Path to system load file
         *
         * @var string
         */
        private $sysloadFile = '/proc/stat';

        /**
         * Init class by static method
         *
         * @return TDCMonitoring
         */
        public static function init()
        {
            return new static();
        }

        /**
         * Get CPU of the system
         *
         * @return array
         */
        private function getCPU()
        {

            if (!is_readable($this->cpuFile)) {
                return [];
            }

            $cpu = @file($this->cpuFile);

            $result = [];

            $cpu = implode("", $cpu);

            // matching value
            @preg_match_all("/model\s+name\s{0,}\:+\s{0,}([\w\s\)\(\@.-]+)([\r\n]+)/s", $cpu, $machineModel);
            @preg_match_all("/cpu\s+MHz\s{0,}\:+\s{0,}([\d\.]+)[\r\n]+/", $cpu, $matchesHz);
            @preg_match_all("/cache\s+size\s{0,}\:+\s{0,}([\d\.]+\s{0,}[A-Z]+[\r\n]+)/", $cpu, $cache);
            @preg_match_all("/bogomips\s{0,}\:+\s{0,}([\d\.]+)[\r\n]+/", $cpu, $bogomips);

            if (is_array($machineModel[1]) !== false) {
                $result['num'] = sizeof($machineModel[1]);

                if ($result['num'] == 1)
                    $x1 = '';
                else
                    $x1 = ' ×' . $result['num'];

                $matchesHz[1][0] = ' | Frequency:' . $matchesHz[1][0];
                $cache[1][0] = ' | Secondary cache:' . $cache[1][0];
                $bogomips[1][0] = ' | Bogomips:' . $bogomips[1][0];

                $result['model'][] = $machineModel[1][0] . $matchesHz[1][0] . $cache[1][0] . $bogomips[1][0] . $x1;

                if (is_array($result['model']) !== false) {
                    $result['model'] = implode("<br />", $result['model']);
                }
            }
            return $result;
        }

        /**
         * Get disk info
         *
         * @return array
         */
        private function getDisk()
        {
            return [
                'total' => round(@disk_total_space('.') / (1024 * 1024 * 1024), 3),
                'free' => round(@disk_free_space('.') / (1024 * 1024 * 1024), 3),
                'ssd' => trim(@file_get_contents('/sys/block/sda/queue/rotational'), "\n") == 0
            ];
        }

        /**
         * Get memory of the System
         *
         * @return array
         */
        private function getMemory()
        {
            if (!is_readable($this->memoryFile)) {
                return [];
            }

            $memory = @file($this->memoryFile);
            $memory = implode("", $memory);

            //matching value
            $pattern = "/MemTotal\s{0,}\:+\s{0,}([\d\.]+).+?MemFree\s{0,}\:+\s{0,}([\d\.]+).+?Cached\s{0,}\:+\s{0,}([\d\.]+).+?SwapTotal\s{0,}\:+\s{0,}([\d\.]+).+?SwapFree\s{0,}\:+\s{0,}([\d\.]+)/s";
            preg_match_all($pattern, $memory, $buf);
            preg_match_all("/Buffers\s{0,}\:+\s{0,}([\d\.]+)/s", $memory, $buffers);

            $result['memTotal'] = round($buf[1][0] / 1024, 2);
            $result['memFree'] = round($buf[2][0] / 1024, 2);
            $result['memBuffers'] = round($buffers[1][0] / 1024, 2);
            $result['memCached'] = round($buf[3][0] / 1024, 2);
            $result['memUsed'] = $result['memTotal'] - $result['memFree'];

            $result['memPercent'] = 0;
            if ((floatval($result['memTotal']) != 0)) {
                $result['memPercent'] = round($result['memUsed'] / $result['memTotal'] * 100, 3);
            }

            $result['memRealUsed'] = $result['memTotal'] - $result['memFree'] - $result['memCached'] - $result['memBuffers'];
            $result['memRealFree'] = $result['memTotal'] - $result['memRealUsed'];

            $result['memCachedPercent'] = 0;
            if (floatval($result['memTotal']) != 0) {
                $result['memCachedPercent'] = round($result['memRealUsed'] / $result['memTotal'] * 100, 2);
            }

            $result['swapTotal'] = round($buf[4][0] / 1024, 2);
            $result['swapFree'] = round($buf[5][0] / 1024, 2);
            $result['swapUsed'] = round($result['swapTotal'] - $result['swapFree'], 2);

            $result['swapPercent'] = 0;
            if (floatval($result['swapTotal']) != 0) {
                $result['swapPercent'] = round($result['swapUsed'] / $result['swapTotal'] * 100, 2);
            }

            return $result;
        }

        /**
         * Get uptime of server
         *
         * @return array
         */
        private function getUptime()
        {

            if (!is_readable($this->uptimeFile)) {
                return [];
            }

            $uptime = @file($this->uptimeFile);

            $uptime = explode(" ", implode("", $uptime));

            $uptime = trim($uptime[0]);

            $min = $uptime / 60; // minute count
            $hours = $min / 60; // hours count

            $days = floor($hours / 24); // floor days
            $hours = floor($hours - ($days * 24)); // floor hours
            $min = floor($min - ($days * 60 * 24) - ($hours * 60)); // floor minute

            return [
                'day' => $days,
                'hour' => $hours,
                'minute' => $min,
            ];
        }

        /**
         * Get server load
         *
         * @return array
         */
        private function getServerLoad()
        {
            if (!is_readable($this->sysloadFile)) {
                return [];
            }

            $stats = @file_get_contents($this->sysloadFile);

            $stats = preg_replace(" / [[:blank:]]+/", " ", $stats);

            $stats = str_replace(array("\r\n", "\n\r", "\r"), "\n", $stats);
            $stats = explode("\n", $stats);

            foreach ($stats as $statLine) {
                $statLineData = explode(" ", trim($statLine));

                // Found!
                if ((count($statLineData) >= 5) && ($statLineData[0] == "cpu")) {
                    return array(
                        $statLineData[1],
                        $statLineData[2],
                        $statLineData[3],
                        $statLineData[4],
                    );
                }
            }

            return [];
        }

        /**
         * Get server load average by percent
         *
         * @return float
         */
        private function getAveragerLoad()
        {
            $first = $this->getServerLoad();
            sleep(1);
            $seconds = $this->getServerLoad();

            // Get difference
            $first[0] -= $seconds[0];
            $first[1] -= $seconds[1];
            $first[2] -= $seconds[2];
            $first[3] -= $seconds[3];

            // Sum up the 4 values for User, Nice, System and Idle and calculate
            // the percentage of idle time (which is part of the 4 values!)
            $cpuTime = $first[0] + $first[1] + $first[2] + $first[3];

            // Invert percentage to get CPU time, not idle time
            $load = 100 - ($first[3] * 100 / $cpuTime);

            return round($load, 3);
        }

        /**
         * Get core load information
         *
         * @return array
         */
        private function getCoreCPU()
        {
            if (!is_readable($this->sysloadFile)) {
                return [];
            }

            $data = @file($this->sysloadFile);
            $cores = [];
            foreach ($data as $line) {
                if (preg_match('/^cpu[0-9]/', $line)) {
                    $info = explode(' ', $line);
                    $cores[] = [
                        'user' => $info[1],
                        'nice' => $info[2],
                        'sys' => $info[3],
                        'idle' => $info[4],
                        'iowait' => $info[5],
                        'irq' => $info[6],
                        'softirq' => $info[7]];
                }
            }

            return $cores;
        }

        private function getHostInfo()
        {
            return gethostname();
        }

        /**
         * Merge all data
         *
         * @return array
         */
        public function getData()
        {
            $result = [];
            $result['host'] = $this->getHostInfo();
            $result['cpu'] = $this->getCPU();
            $result['disk'] = $this->getDisk();
            $result['memory'] = $this->getMemory();
            $result['uptime'] = $this->getUptime();
            $result['serverLoad'] = $this->getAveragerLoad();
            $result['coreInfomation'] = $this->getCoreCPU();

            $output = [
                'status' => 200,
                'data' => $result
            ];

            return $output;
        }

    }

    die(json_encode(TDCMonitoring::init()->getData()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <meta name="description" content="Page Description">
    <meta name="author" content="thanhtai">
    <title>TDC monitoring - v1.0.0</title>

    <!-- Latest compiled and minified CSS & JS -->
    <link rel="stylesheet" media="screen" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <style>
        .navbar-default {
            background-color: #3d454c;
        }

        .navbar-default .navbar-nav > li > a {
            color: #bdb5b5;
        }

        .navbar-default .navbar-nav > li > a:focus, .navbar-default .navbar-nav > li > a:hover {
            color: #3d454c;
            background-color: #b6b9bb;
        }

        footer {
            background-color: #3d454c;
            padding: 20px;
            color: #b6b9bb;
        }

        .progess-bar {
            background-color: #3d454c;
            border-radius: 35px;
            position: fixed;
            bottom: 70px;
            right: 20px;
            z-index: 10000;
            height: 50px;
            width: 50px;
        }

        .progess-bar:hover {
            background-color: #0e0e13;
        }

        .current-progess-status {
            color: #ffffff;
            text-align: center;
            line-height: 50px;
        }

    </style>
</head>
<body>
<header>
    <nav class="navbar navbar-default navbar-fixed-top" role="navigation">
        <!-- Brand and toggle get grouped for better mobile display -->
        <div class="container">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-ex1-collapse">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="#">
                    <img src="https://static.toidicode.com/upload/images/logo.png" style="margin-top: -6px" alt="Toidicode logo" height="34px"></a>
            </div>

            <!-- Collect the nav links, forms, and other content for toggling -->
            <div class="collapse navbar-collapse navbar-ex1-collapse">
                <ul class="nav navbar-nav navbar-right">
                    <li>
                        <a href="#systeminfo" class="goto">System Info</a>
                    </li>
                    <li>
                        <a href="#diskinfo" class="goto">Disk Info</a>
                    </li>
                    <li>
                        <a href="#memoryinfo" class="goto">Memory Info</a>
                    </li>
                    <li>
                        <a href="#cpuchart" class="goto">CPU Chart</a>
                    </li>
                </ul>
            </div><!-- /.navbar-collapse -->
        </div>
    </nav>
</header>

<div class="container" style="margin-top: 60px">
    <div class="row">
        <div class="col-sm-12">
            <div class="row">
                <div class="col-sm-12" id="systeminfo">
                    <div class="panel panel-primary">
                        <div class="panel-heading">
                            <h3 class="panel-title">System infomation</h3>
                        </div>
                        <div class="panel-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <tbody>
                                    <tr>
                                        <td>Host Name:</td>
                                        <td class="server_name"></td>
                                    </tr>
                                    <tr>
                                        <td>System information:</td>
                                        <td class="server_model"></td>
                                    </tr>
                                    <tr>
                                        <td>CPU:</td>
                                        <td class="server_cpu_num"></td>
                                    </tr>
                                    <tr>
                                        <td>Up Time:</td>
                                        <td class="server_uptime"></td>
                                    </tr>
                                    <tr>
                                        <td>Server Load Average:</td>
                                        <td class="server_load_average"></td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-12" id="diskinfo">
                    <div class="panel panel-primary">
                        <div class="panel-heading">
                            <h3 class="panel-title">Disk information</h3>
                        </div>
                        <div class="panel-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <tbody>
                                    <tr>
                                        <td>Disk Type</td>
                                        <td class="disk-type"></td>
                                    </tr>
                                    <tr>
                                        <td>Total Disk</td>
                                        <td class="total-disk"></td>
                                    </tr>
                                    <tr>
                                        <td>Free Disk</td>
                                        <td class="free-disk"></td>
                                    </tr>
                                    <tr>
                                        <td>Used Disk</td>
                                        <td class="used-disk"></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">
                                            <canvas id="diskchart" height="100"></canvas>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-12" id="memoryinfo">
                    <div class="panel panel-primary">
                        <div class="panel-heading">
                            <h3 class="panel-title">Memory infomation</h3>
                        </div>
                        <div class="panel-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered">
                                    <tbody>
                                    <tr>
                                        <td>Total:</td>
                                        <td class="server_memory_total"></td>
                                    </tr>
                                    <tr>
                                        <td>Free:</td>
                                        <td class="server_memory_free"></td>
                                    </tr>
                                    <tr>
                                        <td>Used:</td>
                                        <td class="server_memory_used"></td>
                                    </tr>
                                    <tr>
                                        <td>Real Used:</td>
                                        <td class="server_memory_real_used"></td>
                                    </tr>
                                    <tr>
                                        <td>Real Free:</td>
                                        <td class="server_memory_real_free"></td>
                                    </tr>
                                    <tr>
                                        <td>Buffers:</td>
                                        <td class="server_memory_buffers"></td>
                                    </tr>
                                    <tr>
                                        <td>Cached:</td>
                                        <td class="server_memory_cached"></td>
                                    </tr>
                                    <tr>
                                        <td>Total Swap:</td>
                                        <td class="server_memory_swap_total"></td>
                                    </tr>
                                    <tr>
                                        <td>Free Swap:</td>
                                        <td class="server_memory_swap_free"></td>
                                    </tr>
                                    <tr>
                                        <td>Used Swap:</td>
                                        <td class="server_memory_swap_used"></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">
                                            <canvas id="memorychart" height="100"></canvas>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-12" id="cpuchart">
            <div class="panel panel-danger">
                <div class="panel-heading">
                    <h3 class="panel-title">CORE CPU CHART</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-12 cpu-chart">

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="progess-bar">
    <div class="current-progess-status" data-play="true">
        <i class="glyphicon glyphicon-pause" title="Click to Pause Request"></i>
        <i class="glyphicon glyphicon-play" title="Click to Play Request" style="display: none"></i>
    </div>
</div>
<footer>
    <div class="container">
        <div class="text-right">Copyright &copy; 2018 by <a href="https://toidicode.com" style="color: #b6b9bb">Toidicode.com</a>
            - v1.0.0
        </div>
    </div>
</footer>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.2/Chart.bundle.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script>
    //animation

    $(".goto").click(function () {
        var e = $(this).attr('href');
        $('html, body').animate({
            scrollTop: ($(e).offset().top - 60)
        }, 200);
    });

    //init
    window.chartColors = {
        red: 'rgb(255, 99, 132)',
        orange: 'rgb(255, 159, 64)',
        yellow: 'rgb(255, 205, 86)',
        green: 'rgb(75, 192, 192)',
        blue: 'rgb(54, 162, 235)',
        purple: 'rgb(153, 102, 255)',
        grey: 'rgb(201, 203, 207)'
    };

    //flag
    var isPlaying = true;

    function handleRequest() {
        if (!isPlaying) {
            return;
        }

        $.ajax({
            url: window.location.pathname,
            method: "POST",
            data: {api: true},
            success: function (data) {
                if (data.status != 200) {
                    alert("Has error, please reload this page");
                    return false;
                }

                data = data.data;

                // server name
                $('.server_name').text(data.host);

                // server info
                $('.server_model').text(data.cpu.model);
                $('.server_cpu_num').text(data.cpu.num + ' CPU');

                // uptime
                $('.server_uptime').text(data.uptime.day + ' days ' + data.uptime.hour + ' hour ' + data.uptime.minute + ' minute');

                var loadAvg = '';
                if (data.serverLoad > 90) {
                    loadAvg += '<span class="label label-danger">'
                } else if (data.serverLoad > 70) {
                    loadAvg += '<span class="label label-warning">'
                } else {
                    loadAvg += '<span class="label label-success">';
                }
                loadAvg += (data.serverLoad).toFixed(3) + '%</span>';

                // server load averager
                $('.server_load_average').html(loadAvg);

                //disk
                $('.disk-type').text((data.disk.ssd ? 'SSD' : 'HDD'));
                $('.total-disk').text(data.disk.total + 'GB');
                $('.free-disk').text(data.disk.free + 'GB');


                var diskUsed = (data.disk.total - data.disk.free).toFixed(3) + ' GB ';

                if ((data.disk.total - data.disk.free) > 90) {
                    diskUsed += '<span class="label label-danger">'
                } else if ((data.disk.total - data.disk.free) > 70) {
                    diskUsed += '<span class="label label-warning">'
                } else {
                    diskUsed += '<span class="label label-success">';
                }
                diskUsed += ((data.disk.total - data.disk.free) / data.disk.total * 100).toFixed(3) + '%</span>';

                $('.used-disk').html(diskUsed);

                // memory
                $('.server_memory_total').text((data.memory.memTotal / 1024).toFixed(3) + ' GB');
                $('.server_memory_free').text((data.memory.memFree / 1024).toFixed(3) + ' GB');

                var memUsed = (data.memory.memUsed / 1024).toFixed(3) + ' GB ';

                if (data.memory.memPercent > 90) {
                    memUsed += '<span class="label label-danger">'
                } else if (data.memory.memPercent > 70) {
                    memUsed += '<span class="label label-warning">'
                } else {
                    memUsed += '<span class="label label-success">';
                }

                memUsed += data.memory.memPercent + '%</span>';
                $('.server_memory_used').html(memUsed);

                $('.server_memory_real_used').text((data.memory.memRealUsed / 1024).toFixed(3) + ' GB');
                $('.server_memory_real_free').text((data.memory.memRealFree / 1024).toFixed(3) + ' GB');
                $('.server_memory_buffers').text((data.memory.memBuffers / 1024).toFixed(3) + ' GB');
                $('.server_memory_cached').text((data.memory.memCached / 1024).toFixed(3) + ' GB');
                $('.server_memory_percent').text(data.memory.memCachedPercent + " %");
                $('.server_memory_swap_total').text((data.memory.swapTotal / 1024).toFixed(3) + ' GB');
                $('.server_memory_swap_free').text((data.memory.swapFree / 1024).toFixed(3) + ' GB');
                $('.server_memory_swap_used').text((data.memory.swapUsed / 1024).toFixed(3) + ' GB (' + data.memory.swapPercent + "%)");

                // handle cpu core chart
                var coreLength = data.coreInfomation.length;
                var html = '';

                for (var i = 0; i < coreLength; i++) {
                    html += '<div class="col-sm-6" style="margin-top: 20px">';
                    html += '<canvas id="cpu-chart-' + (i + 1) + '"></canvas>';
                    html += '<h3 class="text-center"> CPU ' + (i + 1);
                    html += '</div>';
                }

                $('.cpu-chart').html(html);

                for (var i = 0; i < coreLength; i++) {
                    var ctx = document.getElementById('cpu-chart-' + (i + 1)).getContext('2d');

                    new Chart(ctx, {
                        type: 'pie',
                        data: {
                            datasets: [{
                                data: [
                                    data.coreInfomation[i].user,
                                    data.coreInfomation[i].nice,
                                    data.coreInfomation[i].sys,
                                    data.coreInfomation[i].idle,
                                    data.coreInfomation[i].iowait,
                                    data.coreInfomation[i].irq,
                                    data.coreInfomation[i].softirq,
                                ],
                                backgroundColor: [
                                    window.chartColors.red,
                                    window.chartColors.orange,
                                    window.chartColors.yellow,
                                    window.chartColors.green,
                                    window.chartColors.blue,
                                    window.chartColors.purple,
                                    window.chartColors.grey,
                                ],
                                label: 'CPU CHART' + (i + 1)
                            }],
                            labels: [
                                'User',
                                'Nice',
                                'sys',
                                'idle',
                                'IOWait',
                                'IRQ',
                                'SOFTIRQ'
                            ]
                        },
                        options: {
                            responsive: true,
                        }
                    });
                }

                // disk chart

                var ctx = document.getElementById('diskchart').getContext('2d');

                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        datasets: [{
                            data: [
                                data.disk.free,
                                (data.disk.total - data.disk.free).toFixed(3),
                            ],
                            backgroundColor: [
                                window.chartColors.green,
                                window.chartColors.red,
                            ],
                            label: 'DISK CHART'
                        }],
                        labels: [
                            'Free Disk',
                            'Used Disk'
                        ]
                    },
                    options: {
                        responsive: true,
                    }
                });

                // memory chart

                var ctx = document.getElementById('memorychart').getContext('2d');
                ctx.height = 200;

                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        datasets: [{
                            data: [
                                data.memory.memFree,
                                data.memory.memUsed.toFixed(2),
                            ],
                            backgroundColor: [
                                window.chartColors.green,
                                window.chartColors.red,
                            ],
                            label: 'MEMORY CHART'
                        }],
                        labels: [
                            'Free Memory',
                            'Used Memory'
                        ]
                    },
                    options: {
                        responsive: true,
                    }
                });

            }
        });
    }

    // runner
    handleRequest();

    setInterval("handleRequest()", 10000);
    $('.current-progess-status').click(function () {
        if ($(this).attr('data-play') == 'true') {
            $('.current-progess-status .glyphicon-play').show();
            $('.current-progess-status .glyphicon-pause').hide();
            $(this).attr('data-play', false);
            isPlaying = false;

            return false;
        }

        $('.current-progess-status .glyphicon-pause').show();
        $('.current-progess-status .glyphicon-play').hide();
        $(this).attr('data-play', true);
        isPlaying = true;

        return false;
    })

</script>
</body>
</html>
