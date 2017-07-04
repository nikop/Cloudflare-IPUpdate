<?php

# Config Start

$device = 'eth0';

$user = '[cloudflare email]';
$api_key = '[cloudflare api key]';

$domains = [

	'domain.com' => [
		'AAAA' => [
			'@',
			'www',
		]
	]
];

$ipv6_cache = dirname(__FILE__) . '/ipv6_cache';

# Config End

require('class_cloudflare.php');

$cf = new cloudflare_api($user, $api_key);

$data = exec('ip -o -6 addr show ' . $device, $rows);

$ipv6 = null;

$old_ipv6 = file_exists($ipv6_cache) ? file_get_contents($ipv6_cache) : null;

foreach ($rows as $row)
{
	if (preg_match('@inet6 (.*)/(\d+) scope global@', $row, $out))
		$ipv6 = $out[1];
}

$has_errors = false;

echo 'IPv6: ', $ipv6, "\n";

if ($ipv6 == null)
{
	echo "No Ipv6 Found\n";
	@unlink($ipv6_cache);
	exit();
}

if ($old_ipv6 == $ipv6)
{
	echo "Cached Ipv6 is same, skipping.";
	exit();
}

foreach ($domains as $domain => $settings)
{
	echo 'Updating: ', $domain, "\n";
	$data = $cf->rec_load_all($domain);

	if ($data->result != "success")
	{
		$has_errors = true;
		echo 'Error: ', $data->result, "\n";
		continue;
	}

	#var_dump($data);

	foreach ($data->response->recs->objs as $record)
	{
		$subdomain = $record->display_name;

		if ($subdomain == $domain)
			$subdomain = '@';

		if (!isset($settings[$record->type]))
			continue;

		if (!in_array($subdomain, $settings[$record->type]))
			continue;

		$to = null;

 		if ($record->type == 'AAAA')
                	$to = $ipv6;

		else
		{
			echo "Unsupported Type {$record->type}\n";
			continue;
		}

		if ($record->content == $to)
		{
			echo "{$record->display_name} {$record->type} is correct.\n";
			continue;
		}

		echo "Updating Record {$record->display_name} {$record->type}\n", 
"Old Value: {$record->content}\n",
"New Value: {$to}\n";

		$result = $cf->rec_edit($domain, $record->type, $record->rec_id, $record->name, $to);

		if (!$result->result == "success")
		{
			echo 'ERROR!', "\n";
			$has_errors = true;
		}
	}
}

if (!$has_errors)
	file_put_contents($ipv6_cache, $ipv6);
