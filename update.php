<?php

require('./config.php');
require('./vendor/autoload.php');
require('./solvers.php');



$key = new \Cloudflare\API\Auth\APIKey($user, $api_key);
$adapter = new Cloudflare\API\Adapter\Guzzle($key);
$user = new \Cloudflare\API\Endpoints\User($adapter);

$newIP = [];

$os = PHP_OS;

foreach ($ips as $type => $config)
{
	$lr = !empty($config['local']) ? 'local' : 'remote';
	
	$func = [
		"getip_{$type}_{$os}_{$lr}",
		"getip_{$type}_{$lr}",
		"getip_{$type}_{$os}",
		"getip_{$type}",
	];
	
	$ip = null;
	
	foreach ($func as $f)
	{
		if (!function_exists($f))
		{
			echo "$f is missing", PHP_EOL;
			continue;
		}
		
		$ip = $f($config);
		
		if ($ip != null)
		{
			echo "Used $f to get IP", PHP_EOL;
			break;
		}
	}
	
	if ($ip == null)
	{	
		die("Failed to solve IP!");
	}
	
	echo "Using $ip for $type", PHP_EOL;
	
	$newIP[$type] = $ip;
}

$filename = './last.json';

if (file_exists($filename))
{
	$lastIP = json_decode(file_get_contents($filename), true);
	
	if ($newIP == $lastIP)
	{
		die("IP has not changed!");
	}
}

$dns = new \Cloudflare\API\Endpoints\DNS($adapter);
$zones = new \Cloudflare\API\Endpoints\Zones($adapter);

foreach ($domains as $domain => $settings)
{
	$zoneID = $zones->getZoneID($domain);
	
	if (!$zoneID)
	{
		echo "Error with $domain", PHP_EOL;
		continue;
	}
	
	$current = [];
		
	foreach ($dns->listRecords($zoneID)->result as $record)
	{
		if (!isset($current[$type]))
			$current[$type] = [];
		
		$current[$type][$record->name] = $record;
	}
	
	foreach ($settings as $type => $subdomains)
	{
		$ip = $newIP[$type];
		
		foreach ($subdomains as $subdomain)
		{
			$name = $subdomain . '.' . $domain;
			
			
			if (!isset($current[$type][$name]))
			{
				echo 'Creating ', $name, PHP_EOL;
				
				if ($dns->addRecord($zoneID, $type, $name, $ip, 0, true) === true) {
					echo "DNS record created.". PHP_EOL;
				}
			}
			else
			{
				echo 'Updating ', $name, PHP_EOL;
				
				$det = [
					'type' => $type,
					'name' => $name,
					'content' => $ip,
					'proxied' => $current[$type][$name]->proxied,
					'ttl' => $current[$type][$name]->ttl
				];
				
				if ($det['content'] == $current[$type][$name]->content)
				{
					echo "DNS record is OK.". PHP_EOL;
					continue;
				}
				
				$res = $dns->updateRecordDetails($zoneID, $current[$type][$name]->id, $det);
								
				if ($res->success) {
					echo "DNS record updated.". PHP_EOL;
				}
				else
				{
					echo "DNS record failed.". PHP_EOL;
				}
			}
		}
	}
}

file_put_contents($filename, json_encode($newIP));
