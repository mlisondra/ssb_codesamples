<?php
ini_set('display_errors',1);
error_reporting('E_ALL');
date_default_timezone_set ("America/Los_Angeles");

// This file retrieves visistor information from Livechat system and 
// stores in local database
// Each record contains a unique id in property 'id'

$conn;

// Setup db connection
$conn = new mysqli("localhost", "chatjfj_admin", "fFK67GIRTANu", "chatjfj_chatdb");
if (!$conn) {
	$message = "An error occured trying to connect to the Chat database" . "\r\n";
	$message .= "Errors:\r\n";
	$message .= "Error number: " . mysqli_connect_errno() . "\r\n";
	$message .= "Error description: " . mysqli_connect_error() . "\r\n";
	mail("milder.lisondra@jewsforjesus.org","An error occured during data export request for Chat",$message);


}else{
	//mail("milder.lisondra@jewsforjesus.org","data export for chat requested","Someone has requested data regarding visitors");
}


require_once '../vendor/autoload.php';

use LiveChat\Api\Client as LiveChat;

$LiveChatAPI = new LiveChat('web@jewsforjesus.org', '5e191e7817a1186db337627593cab804'); // New api object


$data = $LiveChatAPI->visitors->get(); // Retrieve all visitor data

extract($data);
$count = 0;

foreach($data as $item){
$count++;

	
	//print '<pre>';
	$visit_path_string = print_r(json_encode($item->visit_path),true);
	$operator_string = print_r(json_encode($item->operators),true);
	$prechat_survey_string = print_r(json_encode($item->prechat_survey),true); 
	//print_r($item->prechat_survey);  
	if(count($item->prechat_survey) > 0){
		$prechat_survey_string = print_r(json_encode($item->prechat_survey),true); 
	}


	//print '</pre>';
	

	
	/*
$sqlprep = $conn->prepare("INSERT INTO visitors (`browser`,`lc_id`,`created_date`,`name`,`city`,`region`,`country`,`country_code`,`ipaddress`,`language`,`page_title`,`page_address`,`invitation`,`page_views`,`chat_state`,`last_visit`,`page_time`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
$sqlprep->bind_param("sssssssssssssisss",$item->browser,
$item->id,$item->page_entered,$item->name,$item->city,$item->region,$item->country,$item->country_code,$item->ip,$item->language,$item->page_title,$item->page_address,$item->invitation,$item->page_views,$item->chat_state,$item->last_visit,$item->page_time);
	$sqlprep->execute();
*/

// Added on 4/13 to take care of duplicates

$sqlprep = $conn->prepare("INSERT INTO visitors (`browser`,
	`lc_id`,
	`created_date`,
	`name`,
	`city`,
	`region`,
	`country`,
	`country_code`,
	`ipaddress`,
	`language`,
	`page_title`,
	`page_address`,
	`invitation`,
	`page_views`,
	`chat_state`,
	`last_visit`,
	`page_time`,
	`group`,
	`visit_path`,
	`queue_start_time`,
	`referrer`,
	`visits`,
	`chats`,
	`latitude`,
	`longitude`,
	`timezone`,
	`page_current`,
	`chat_id`,
	`chat_start_time`,
	`chat_start_time_ts`,
	`greetings_accepted`,
	`page_entered_ts`,
	`page_time_ts`,
	`last_visit_ts`,
	`operators`,`prechat_survey`) 
	VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
	ON DUPLICATE KEY UPDATE
	name = VALUES(name),
	city = VALUES(city),
	region = VALUES(region),
	country = VALUES(country),
	country_code= VALUES(country_code),
	ipaddress= VALUES(ipaddress)");
	
	$sqlprep->bind_param("sssssssssssssisssissssssssssssssssss",$item->browser,$item->id,$item->page_entered,$item->name,$item->city,$item->region,$item->country,$item->country_code,$item->ip,$item->language,$item->page_title,$item->page_address,$item->invitation,$item->page_views,$item->state,$item->last_visit,$item->page_time,$item->group,	$visit_path_string,$item->queue_start_time,$item->referrer,$item->visits,$item->chats,$item->latitude,$item->longitude,$item->timezone,$item->page_current,$item->chat_id,$item->chat_start_time,$item->chat_start_time_ts,$item->greetings_accepted,$item->page_entered_ts,$item->page_time_ts,$item->last_visit_ts, $operator_string,$prechat_survey_string);
	
	
	
	$sqlprep->execute();
	print $conn->error;
}

print $count;