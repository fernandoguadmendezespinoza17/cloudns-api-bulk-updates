<?php

// Auth ID and Password
define("AUTH_ID", 0);
define("AUTH_PASS", "xxxx");

// old Slave IP which will be replaced - it can be both IPv4 or IPv6
define("OLD_SLAVE_IP", "xxx.xxx.xxx.xxx");
define("NEW_SLAVE_IP", "xxx.xxx.xxx.yyy");

// list with zones where the slave ip should be removed
$zones = "example.com
example.net";

// function to connect to the API
function apiCall ($url, $data) {
	$url = "https://api.cloudns.net/{$url}";
	$data = "auth-id=".AUTH_ID."&auth-password=".AUTH_PASS."&{$data}";
	$init = curl_init();
	curl_setopt($init, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($init, CURLOPT_URL, $url);
	curl_setopt($init, CURLOPT_POST, true);
	curl_setopt($init, CURLOPT_POSTFIELDS, $data);
	curl_setopt($init, CURLOPT_USERAGENT, 'cloudns_api_script/0.1 (+https://github.com/ClouDNS/cloudns-api-bulk-updates/tree/master/secondary-ips-management/update.php)');
	$content = curl_exec($init);
	curl_close($init);
	return json_decode($content, true);
}

// checking if we can log in successfully
$login = apiCall('dns/login.json', "");
if (isset($login['status']) && $login['status'] == 'Failed') {
	die($login['statusDescription']);
}

foreach (explode("\n", $zones) as $zone) {
	$zone = trim($zone);
	if (empty($zone)) {
		continue;
	}
	
	$list = apiCall('dns/axfr-list.json', "domain-name={$zone}");
	if (isset($list['status'])) {
		echo "{$zone}: {$list['status']}\n";
		continue;
	}
		
	$exist = false;
	foreach ($list as $axfr) {
		if ($axfr['server'] != OLD_SLAVE_IP) {
			continue;
		}
		
		$exist = true;
		$response = apiCall('dns/axfr-remove.json', "domain-name={$zone}&id={$axfr['id']}");
		if (isset($response['status'])) {
			echo "{$zone}: {$response['statusDescription']}\n";
		} else {
			echo "{$zone}: old slave IP removed\n";
		}

		$response = apiCall('dns/axfr-add.json', "domain-name={$zone}&ip=".NEW_SLAVE_IP);
		if (isset($response['status'])) {
			echo "{$zone}: {$response['statusDescription']}\n";
		} else {
			echo "{$zone}: new slave IP added\n";
		}
		break;
	}
	
	if (!$exist) {
		echo "{$zone}: slave IP doesn't exists in the list with slave IPs\n";
	}
	
}
