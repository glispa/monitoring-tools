#!/usr/bin/php
<?php
$options = getopt("m:u:p:g:");
if(sizeof($options) < 4)
{
        die("usage: php a10_perfToGraphite.php -m HOSTNAME -u USER -p PASSWORD -g GRAPHITESERVER" . PHP_EOL);
}

global $user;
global $pass;
global $master;

$user   = $options['u'];
$pass   = $options['p'];
$master = $options['m'];
$graph  = $options['g'];

$stats = getL4Stats();


$g_hostname = str_replace(".","_",$master);
$g_hostname = str_replace("-","_",$g_hostname);

$fp = fsockopen($graph, 2003, $errno, $errstr, 30);
if (!$fp) {
    die("unable to connect to graphite server" . PHP_EOL);
}


foreach($stats->perf->stats as $key => $val)
{
        $g_string = $g_hostname."." .$key.' '.$val.' '.time();
        echo $g_string . PHP_EOL;
        fwrite($fp, $g_string."\r\n");
}


fclose($fp);

function getL4Stats()
{
        global $user;
        global $pass;
        global $master;
        // /axapi/v3/slb/l4/stats
        $ch = curl_init('https://'.$master.'/axapi/v3/slb/perf/stats');
        if(isset($debug))
        {
                curl_setopt($ch, CURLOPT_VERBOSE, true);
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization:A10 '.getAuthtoken($master).'',
        ));

        $result = curl_exec($ch);
        $info = curl_getinfo($ch);
        if(!$result || $info['http_code'] != 200)
        {
                echo "CRITICAL - error retrieving stats" . PHP_EOL;
                exit(2);
        }
        $out = json_decode($result);
        return $out;
}

function getAuthToken($host)
{
        global $user;
        global $pass;
        global $debug;
        $data_string = '{"credentials":{"username":"'.$user.'","password":"'.$pass.'"}}';

        $ch = curl_init('https://'.$host.'/axapi/v3/auth');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        if(isset($debug))
        {
                curl_setopt($ch, CURLOPT_VERBOSE, true);
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string))
        );

        $result = curl_exec($ch);
        $info = curl_getinfo($ch);
        if(!$result || $info['http_code'] != 200)
        {
                echo "CRITICAL - unable to obtain auth token" . PHP_EOL;
                exit(2);
        }
        $out = json_decode($result);
        return $out->authresponse->signature;
}
