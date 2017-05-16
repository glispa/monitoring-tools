#!/usr/bin/php
<?php
$options = getopt("m:u:p:s:w:c:");

if(sizeof($options) < 5)
{
	die("usage: php a10_slbcheck.php -m HOSTNAME -u USER -p PASSWORD -s slbname -w num_members_warning -c num_members_crit" . PHP_EOL);
}

global $user;
global $pass;
global $master;
global $slb;
$user   = $options['u'];
$pass   = $options['p'];
$master = $options['m'];
$slb    = $options['s'];
$warn   = $options['w'];
$crit   = $options['c'];

$info = getSLBInfo();
$state  = $info->{'service-group'}->oper->state;
$s_up 	= $info->{'service-group'}->oper->servers_up;
$s_down = $info->{'service-group'}->oper->servers_down;
$s_tot  = $info->{'service-group'}->oper->servers_total;

//echo "State: $state UP: $s_up DOWN: $s_down TOTAL: $s_tot" . PHP_EOL;

if($state == "Down")
{
	echo "CRITICAL - $state - $s_up of $s_tot members online" . PHP_EOL;
	exit(2);
}

if($s_up == $s_tot)
{
	echo "OK - $state - $s_up of $s_tot members online" . PHP_EOL;
	exit(0);
}
elseif($s_up == 0)
{
	echo "CRITICAL - $state - $s_up of $s_tot members online" . PHP_EOL;
	exit(2);
}
elseif($s_up < $s_tot)
{
	if($s_down == $warn)
	{
		echo "WARNING - $state - $s_up of $s_tot members online" . PHP_EOL;
		exit (1);
	}
	else
	{
		if($s_down == $crit)
		{
			echo "CRITICAL - $state - $s_up of $s_tot members online" . PHP_EOL;
			exit (2);
		}
		else
		{
			echo "OK - $state - $s_up of $s_tot members online" . PHP_EOL;
			exit(0);
		}	
	}
}
else
{
	echo "UNKNOWN - $state - $s_up of $s_tot members online";
	exit(3);
}



function getSLBInfo()
{
	global $user; 
	global $pass;
	global $master;
	global $slb;
	// /axapi/v3/slb/l4/stats 
	$ch = curl_init('https://'.$master.'/axapi/v3/slb/service-group/'.$slb.'/oper'); 
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
		var_dump($result);
		exit(2);
	}
	$out = json_decode($result);
	return $out;
}

function getAuthToken($host)
{
	$cur_time = time();
	if(is_file("/tmp/a10token_$host"))
	{
		$sp = $cur_time - filemtime ("/tmp/a10token_$host");
		if($sp < 60)
		{
			return file_get_contents("/tmp/a10token_$host");
		}
	}
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
		var_dump($result);
		exit(2);
	}
	$out = json_decode($result);
	$fp = fopen('/tmp/a10token_'.$host, 'w');
	fwrite($fp, $out->authresponse->signature);
	fclose($fp);
	return $out->authresponse->signature;
}
