<?php
error_reporting(E_ERROR);

$env = 'sandbox'; // Set the environment to be used for processing payments via Braintree - use "sandbox" or "production"

include("PHPMailer/class.phpmailer.php");
$mail = new PHPMailer();
$mail->IsSMTP();
$mail->Host       = "mail.sfmb.org";
$mail->SMTPDebug  = 0;                    
$mail->SMTPAuth   = true;
$mail->Host       = "mail.sfmb.org";
$mail->Port       = 2525;
$mail->Username   = "notifications@sfmb.org";
$mail->Password   = "Rugby888";

require_once 'braintree/lib/Braintree.php';

$message_body = "";
if($env == 'sandbox'){
// Braintree API configuration
	Braintree_Configuration::environment('sandbox');
	Braintree_Configuration::merchantId('***');
	Braintree_Configuration::publicKey('***');
	Braintree_Configuration::privateKey('***');
}else{
	Braintree_Configuration::environment('production');
	Braintree_Configuration::merchantId('***');
	Braintree_Configuration::publicKey('***');
	Braintree_Configuration::privateKey('***');
}

// Transaction for sale that includes Customer information
$payor = explode(" ", $_POST['cc_name']);
$payor_first_name = trim($payor[0]);
$payor_last_name = trim($payor[1]);

$result = Braintree_Transaction::sale(array(
    'amount' => $_POST['cc_amount'],
    'creditCard' => array(
    'number' => $_POST['cc_number'],
    'expirationMonth' =>$_POST['cc_month'],
    'expirationYear' => $_POST['cc_year']
    ),
  'customer' => array(
    'firstName' => $payor_first_name,
    'lastName' => $payor_last_name
  ),	
	"billing" => array(
		'postalCode' => $_POST['postalCode']
	),
    "options" => array(
        "submitForSettlement" => true,
		'storeInVault' => true
    )
));


if ($result->success) {
   $subject = "Payment notification from SFMB.org";
   $message_body = '<table>';
   $message_body .= '<tr><td colspan="2">This is a dynamically generated message from the "Pay Online" page for sfmb.org</td></tr>';
   $message_body .= "<tr><td>Name:</td><td>".$_POST['cc_name']."</td></tr>";
   $message_body .= "<tr><td>Amount:</td><td>$".number_format($_POST['cc_amount'],2)."</td></tr>";
   $message_body .= "<tr><td>Notes:</td><td>".$_POST['notes']."</td></tr>";
	$mail_objects = array("from_email"=>"notifications@sfmb.org","from_name"=>"Online Payment via SFMB.org","to_email"=>"webmaster@alamocapital.com","subject"=>$subject,"message_body"=>$message_body);
	mail_out($mail_objects);
} else if ($result->transaction) {
  //  print_r("Error processing transaction:");
  ///  print_r("\n  message: " . $result->message);
   // print_r("\n  code: " . $result->transaction->processorResponseCode);
   // print_r("\n  text: " . $result->transaction->processorResponseText);
   $subject = 'Transaction failed';
   $message_body = $result->message . '' . $result->transaction->processorResponseCode . ' ' . $result->transaction->processorResponseText;
	$mail_objects = array("from_email"=>"notifications@sfmb.org","from_name"=>"Online Payment via SFMB.org","to_email"=>"webmaster@alamocapital.com","subject"=>$subject,"message_body"=>$message_body);
	mail_out($mail_objects);   
} else {
    //print_r("Message: " . $result->message);
    //print_r("\nValidation errors: \n");
    //print_r($result->errors->deepAll());
   $subject = 'Transaction failed';
   $message_body = $result->message;
	$mail_objects = array("from_email"=>"notifications@sfmb.org","from_name"=>"Online Payment via SFMB.org","to_email"=>"webmaster@alamocapital.com","subject"=>$subject,"message_body"=>$message_body);
	mail_out($mail_objects);  	
}


print json_encode($result);

/**
* mail_out
* Sends HTML email message; milder.lisondra@yahoo.com and victor.aquino@sbcglobal.net are on BCC
* @param array $mail_objects
* List of array elements:
* string from_email
* string from_name
* string to_email
* string subject
* string message_body
*/
function mail_out($mail_objects){

	global $mail;
	global $env;
	$mail->SetFrom($mail_objects["from_email"],$mail_objects["from_name"]);
	//$mail->FromName = $mail_objects["from_name"];
	
	if($env == 'sandbox'){
		$mail->AddAddress($mail_objects["to_email"]);
	}else{
		$mail->AddAddress('ekong@sfmb.org'); // Production only
		$mail->AddAddress('bill@sfmb.org'); // Production only
		$mail->AddAddress('webmaster@alamocapital.com'); // Production only
	}
	$mail->AddBCC("webmaster@alamocapital.com");
	$mail->Subject  = $mail_objects["subject"];
	$mail->Body     = $mail_objects["message_body"];
	$mail->WordWrap = 50;
	$mail->IsHTML(true);
	$mailresponse = $mail->Send();
	$mail->ClearAllRecipients();
	
}

