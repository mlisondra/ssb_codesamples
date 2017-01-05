<?php
ini_set('display_errors',1);
error_reporting('E_ALL');
date_default_timezone_set ("America/Los_Angeles");


define('EOL',(PHP_SAPI == 'cli') ? PHP_EOL : '<br />');

require_once dirname(__FILE__) . '/Classes/PHPExcel.php';

// Create new PHPExcel object
$objPHPExcel = new PHPExcel();


// Set document properties

// Create a first sheet
$objPHPExcel->setActiveSheetIndex(0);			
$objPHPExcel->getActiveSheet()->setCellValue('A1', "LivechatID");
$objPHPExcel->getActiveSheet()->setCellValue('B1', "Name");
$objPHPExcel->getActiveSheet()->setCellValue('C1', "Browser");
$objPHPExcel->getActiveSheet()->setCellValue('D1', "IP Address");
$objPHPExcel->getActiveSheet()->setCellValue('E1', "Language");
$objPHPExcel->getActiveSheet()->setCellValue('F1', "City");
$objPHPExcel->getActiveSheet()->setCellValue('G1', "Region");
$objPHPExcel->getActiveSheet()->setCellValue('H1', "Country");
$objPHPExcel->getActiveSheet()->setCellValue('I1', "Country Code");
$objPHPExcel->getActiveSheet()->setCellValue('J1', "Created Date");
$objPHPExcel->getActiveSheet()->setCellValue('K1', "Page Entered");
$objPHPExcel->getActiveSheet()->setCellValue('L1', "Page Entered URL");	

$objPHPExcel->getActiveSheet()->setCellValue('M1', "Page Views");	
$objPHPExcel->getActiveSheet()->setCellValue('N1', "Chat State");
$objPHPExcel->getActiveSheet()->setCellValue('O1', "Last Visit");
$objPHPExcel->getActiveSheet()->setCellValue('P1', "Page Time");
$objPHPExcel->getActiveSheet()->setCellValue('Q1', "Group");

$objPHPExcel->getActiveSheet()->setCellValue('R1', "Visit Path");
$objPHPExcel->getActiveSheet()->setCellValue('S1', "Queue Start Time");
$objPHPExcel->getActiveSheet()->setCellValue('T1', "Referrer");
$objPHPExcel->getActiveSheet()->setCellValue('U1', "Visits");
$objPHPExcel->getActiveSheet()->setCellValue('V1', "Chats");

$objPHPExcel->getActiveSheet()->setCellValue('W1', "Latitude");
$objPHPExcel->getActiveSheet()->setCellValue('X1', "Longitude");
$objPHPExcel->getActiveSheet()->setCellValue('Y1', "Timezone");
$objPHPExcel->getActiveSheet()->setCellValue('Z1', "Page Current");
$objPHPExcel->getActiveSheet()->setCellValue('AA1', "Chat ID");

$objPHPExcel->getActiveSheet()->setCellValue('AB1', "Chat Start Time");
$objPHPExcel->getActiveSheet()->setCellValue('AC1', "Chart Start Timestamp");
$objPHPExcel->getActiveSheet()->setCellValue('AD1', "Greetings Accepted");
$objPHPExcel->getActiveSheet()->setCellValue('AE1', "Page Entered Timestamp");
$objPHPExcel->getActiveSheet()->setCellValue('AF1', "Page Timestamp");

$objPHPExcel->getActiveSheet()->setCellValue('AG1', "Last Visit Timestamp");
$objPHPExcel->getActiveSheet()->setCellValue('AH1', "Operators");
$objPHPExcel->getActiveSheet()->setCellValue('AI1', "Prechat Survey");
$objPHPExcel->getActiveSheet()->setCellValue('AJ1', "Invitation");



// Set outline levels
$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setOutlineLevel(1)
                                                       ->setVisible(false)
                                                       ->setCollapsed(true);

// Rows to repeat at top
$objPHPExcel->getActiveSheet()->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);

$response;
$conn;

$conn = new mysqli("localhost", "chatjfj_admin", "fFK67GIRTANu", "chatjfj_chatdb");
if (!$conn) {
	$message = "An error occured trying to connect to the Chat database" . "\r\n";
	$message .= "Errors:\r\n";
	$message .= "Error number: " . mysqli_connect_errno() . "\r\n";
	$message .= "Error description: " . mysqli_connect_error() . "\r\n";
	mail("milder.lisondra@jewsforjesus.org","An error occured during data export request for Chat",$message);
	
	$response = array("message"=>"There was a problem with the export request. The system administrator has been notified.");

}else{
	//mail("milder.lisondra@jewsforjesus.org","data export for chat requested","Someone has requested data regarding visitors");
}

//if(!isset($_POST['fromdate']) && !isset($_POST['todate']) ){ // date range not given

	if( $_POST['fromdate'] != '' && $_POST['todate'] != '' ){
		extract($_POST);
		$sql = "SELECT * FROM visitors WHERE created_date >= '" . $fromdate . "' AND created_date <= '".$todate."' order by created_date DESC";
	}else{
		// Select all data from db
		$sql = "SELECT * FROM visitors order by created_date DESC";	
	}

	$data = $conn->query($sql);
	$row_num = 2;
	if($data->num_rows > 0){
		while($row = $data->fetch_object()){

			$display_chat_start_time_ts = 'N/A';
			$display_page_entered_ts = 'N/A';
			$display_page_time_ts = 'N/A';
			$display_last_visit_ts = 'N/A';
			
			if($row->chat_start_time_ts <> '0'){
				$display_chat_start_time_ts = date("Y-m-d h:i:s",$row->chat_start_time_ts);
			
			}
			if($row->page_entered_ts<> '0'){
				$display_page_entered_ts = date("Y-m-d h:i:s",$row->page_entered_ts);
			
			}

			if($row->page_time_ts<> '0'){
				$display_page_time_ts = date("Y-m-d h:i:s",$row->page_time_ts);
			
			}
			if($row->last_visit_ts<> '0'){
				$display_last_visit_ts = date("Y-m-d h:i:s",$row->last_visit_ts);
			
			}

			$objPHPExcel->getActiveSheet()->setCellValue('A' . $row_num, $row->lc_id);
			$objPHPExcel->getActiveSheet()->setCellValue('B' . $row_num, $row->name);
			$objPHPExcel->getActiveSheet()->setCellValue('C' . $row_num, $row->browser);
			$objPHPExcel->getActiveSheet()->setCellValue('D' . $row_num, $row->ipaddress);
			$objPHPExcel->getActiveSheet()->setCellValue('E' . $row_num, $row->language);
			$objPHPExcel->getActiveSheet()->setCellValue('F' . $row_num, $row->city);
			$objPHPExcel->getActiveSheet()->setCellValue('G' . $row_num, $row->region);
			$objPHPExcel->getActiveSheet()->setCellValue('H' . $row_num, $row->country);
			$objPHPExcel->getActiveSheet()->setCellValue('I' . $row_num, $row->country_code);
			$objPHPExcel->getActiveSheet()->setCellValue('J' . $row_num, $row->created_date);
			$objPHPExcel->getActiveSheet()->setCellValue('K' . $row_num, $row->page_title);
			$objPHPExcel->getActiveSheet()->setCellValue('L' . $row_num, $row->page_address);
			$objPHPExcel->getActiveSheet()->setCellValue('M' . $row_num, $row->page_views);
			$objPHPExcel->getActiveSheet()->setCellValue('N' . $row_num, $row->chat_state);
			$objPHPExcel->getActiveSheet()->setCellValue('O' . $row_num, $row->last_visit);
			$objPHPExcel->getActiveSheet()->setCellValue('P' . $row_num, $row->page_time);
			$objPHPExcel->getActiveSheet()->setCellValue('Q' . $row_num, $row->group);
			$objPHPExcel->getActiveSheet()->setCellValue('R' . $row_num, $row->visit_path);
			$objPHPExcel->getActiveSheet()->setCellValue('S' . $row_num, $row->queue_start_time);
			$objPHPExcel->getActiveSheet()->setCellValue('T' . $row_num, $row->referrer);
			$objPHPExcel->getActiveSheet()->setCellValue('U' . $row_num, $row->visits);
			$objPHPExcel->getActiveSheet()->setCellValue('V' . $row_num, $row->chats);
			
			$objPHPExcel->getActiveSheet()->setCellValue('W' . $row_num, $row->latitude);
			$objPHPExcel->getActiveSheet()->setCellValue('X' . $row_num, $row->longitude);
			$objPHPExcel->getActiveSheet()->setCellValue('Y' . $row_num, $row->timezone);
			$objPHPExcel->getActiveSheet()->setCellValue('Z' . $row_num, $row->page_current);
			$objPHPExcel->getActiveSheet()->setCellValue('AA' . $row_num, $row->chat_id);
			$objPHPExcel->getActiveSheet()->setCellValue('AB' . $row_num, $row->chat_start_time);
			$objPHPExcel->getActiveSheet()->setCellValue('AC' . $row_num, $display_chat_start_time_ts );
			$objPHPExcel->getActiveSheet()->setCellValue('AD' . $row_num, $row->greetings_accepted);
			$objPHPExcel->getActiveSheet()->setCellValue('AE' . $row_num, $display_page_entered_ts );
			$objPHPExcel->getActiveSheet()->setCellValue('AF' . $row_num, $display_page_time_ts);
			$objPHPExcel->getActiveSheet()->setCellValue('AG' . $row_num, $display_last_visit_ts);
			$objPHPExcel->getActiveSheet()->setCellValue('AH' . $row_num, $row->operators);
			$objPHPExcel->getActiveSheet()->setCellValue('AI' . $row_num, $row->prechat_survey);
			$objPHPExcel->getActiveSheet()->setCellValue('AJ' . $row_num, $row->invitation);
			
			
            $row_num++;
		}
		
		$response = array("message"=>"Here is the link to your data");
	}else{
		$response = array("message"=>"No data found");
	}
//}



// Set active sheet index to the first sheet, so Excel opens this as the first sheet
$objPHPExcel->setActiveSheetIndex(0);


// Save Excel 2007 file
//echo date('H:i:s') , " Write to Excel2007 format" , EOL;
$callStartTime = microtime(true);

$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
$savetofile = "visitor_report_".time().".xlsx";
$objWriter->save($savetofile);
$callEndTime = microtime(true);
$callTime = $callEndTime - $callStartTime;

// Echo done


$pathtofile = $savetofile ;

$response = array("message"=>$pathtofile );

print json_encode($response);