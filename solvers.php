<?php

// Get IPV6 for Linux Locally
function getip_AAAA_Linux_local($config)
{
	$data = exec('ip -o -6 addr show ' . $config['device'], $rows);
	
	foreach ($rows as $row)
	{
		if (preg_match('@inet6 (.*)/(\d+) scope global@', $row, $out))
			return $out[1];
	}
	
	return null;
}

// Get IPV6 for Windows Locally
// This uses always first IP
function getip_AAAA_WINNT_local($config)
{
	$data = exec('ipconfig', $rows);
		
	foreach ($rows as $row)
	{
		if (preg_match('@IPv6 Address(.+):\s+?(.*)@', $row, $out))
			return $out[2];
	}
	
	return null;
}