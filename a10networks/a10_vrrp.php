#!/usr/bin/php
<?php
// a10_vrrp.php -s test -u root -p fff -v 0 -e active
$options = getopt("s:m:u:p:v:d:");
if(sizeof($options) < 5)
{
        die("usage: php a10_vrrp.php -m HOSTNAME -s HOSTNAME  -u USER -p PASSWORD -v VRID" . PHP_EOL);
}

global $user;
global $pass;
global $master;
global $slave;
global $vrid;
global $debug;

$user   = $options['u'];
$pass   = $options['p'];
$master = $options['m'];
$slave  = $options['s'];
$vrid   = $options['v'];
$debug  = $options['d'];

checkVrridStatus();

function checkVrridStatus()
{
        global $user;
        global $pass;
        global $debug;
        global $vrid;
        global $master;
        global $slave;
        $ch = curl_init('https://'.$master.'/axapi/v3/vrrp-a/vrid/'.$vrid.'/oper');
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
                echo "CRITICAL - error retrieving vrrid status" . PHP_EOL;
                exit(2);
        }
        $out = json_decode($result);
        $mstatus->unit = $out->vrid->oper->unit;
        $mstatus->state = $out->vrid->oper->state;

        $ch = curl_init('https://'.$slave.'/axapi/v3/vrrp-a/vrid/'.$vrid.'/oper');
        if(isset($debug))
        {
                curl_setopt($ch, CURLOPT_VERBOSE, true);
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization:A10 '.getAuthtoken($slave).'',
        ));

        $result = curl_exec($ch);
        $info = curl_getinfo($ch);
        if(!$result || $info['http_code'] != 200)
        {
                echo "CRITICAL - error retrieving vrrid status" . PHP_EOL;
                exit(2);
        }
        $out = json_decode($result);

        $sstatus->unit = $out->vrid->oper->unit;
        $sstatus->state = $out->vrid->oper->state;

        if(isset($debug))
        {
                var_dump($mstatus); var_dump($sstatus);
        }

        if($sstatus->state == "Active" && $mstatus->state == "Active")
        {
                echo "CRITICAL - Unit 0 and 1 are both master for VRRID $vrid!" . PHP_EOL;
                exit(2);
        }
        elseif($sstatus->state == "Active" && $mstatus->state == "Standby")
        {
                echo "OK - Unit $sstatus->unit and $mstatus->unit are Active/Standby for VRRID $vrid" . PHP_EOL;
                exit(0);
        }
        elseif($mstatus->state == "Active" && $sstatus->state == "Standby")
        {
                echo "OK - Unit $mstatus->unit and $sstatus->unit are Active/Standby for VRRID $vrid" . PHP_EOL;
                exit(0);
        }
        else
        {
                echo "CRITICAL - Unknown unit state for VRRID $vrid!" . PHP_EOL;
                exit(2);
        }
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
