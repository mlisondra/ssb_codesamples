<?php
$parse_uri = explode( 'wp-content', $_SERVER['SCRIPT_FILENAME'] );
require_once( $parse_uri[0] . 'wp-load.php' );
global $wpdb;

if(isset($_GET['branch'])) {
	$sql_query = "SELECT `id`,`display_name` FROM wp_users WHERE id IN (SELECT user_id FROM wp_cimy_uef_data WHERE value = \"" . $_GET['branch'] . "\");";

	$sql = $wpdb->get_results($sql_query); 
	$parsed_sql = json_decode(json_encode($sql), True);
	echo '<span id="staffList">';
	foreach ($parsed_sql as $staffer) {
	
		$user_id = $staffer['id'];
		
		// Get USER Meta data
		$user_meta_data = get_user_meta($user_id);
		$user_fname = $user_meta_data['first_name'][0];
		$user_lname = $user_meta_data['last_name'][0];	
		
		$name = explode(" ", $staffer['display_name']);

		echo '<span style="border-style:2px;" id="' . $user_fname . str_replace(' ', '' , $user_lname) . 'card"></span><br>';
	
	
	$branch_sql = "SELECT value FROM wp_cimy_uef_data WHERE user_id =" . $user_id . " AND field_id = 4";
	$donation_sql = "SELECT value FROM wp_cimy_uef_data WHERE user_id =" . $user_id . " AND field_id = 2";
	$linkedin_sql = "SELECT value FROM wp_cimy_uef_data WHERE user_id =" . $user_id . " AND field_id = 11";
	$role_sql = "SELECT value FROM wp_cimy_uef_data WHERE user_id =" . $user_id . " AND field_id = 16";
	$dome_sql = "SELECT value FROM wp_cimy_uef_data WHERE user_id =" . $user_id . " AND field_id = 14";
	$phone_sql = "SELECT value FROM wp_cimy_uef_data WHERE user_id =" . $user_id . " AND field_id = 12";
	$skype_sql = "SELECT value FROM wp_cimy_uef_data WHERE user_id =" . $user_id . " AND field_id = 10";

	$branch = $wpdb->get_results($branch_sql);
	$donation = $wpdb->get_results($donation_sql);
	$linkedin = $wpdb->get_results($linkedin_sql);
	$role = $wpdb->get_results($role_sql);
	$dome = $wpdb->get_results($dome_sql);
	$phone = $wpdb->get_results($phone_sql);
	$skype = $wpdb->get_results($skype_sql);

	$parsed_branch = json_decode(json_encode($branch), True);
	$parsed_donation = json_decode(json_encode($donation), True);
	$parsed_linkedin = json_decode(json_encode($linkedin), True);
	$parsed_role = json_decode(json_encode($role), True);
	$parsed_dome = json_decode(json_encode($dome), True);
	$parsed_phone = json_decode(json_encode($phone), True);
	$parsed_skype = json_decode(json_encode($skype), True);	
	
	$calling_area = $parsed_dome[0][value];
	$skype_id = $parsed_skype[0][value];
	$phone_num = $parsed_phone[0][value];
	
	// Profile Picture Prefix
	$profile_image_prefix = strtolower(str_replace(' ' , '-',$user_fname)) . '-' . strtolower(str_replace(' ' , '-',$user_lname));
			
			
echo '<div class="row" id="theCard">
	<div style="max-width:1280px;margin:auto;">
		<div class="jfj-card jfj-person-card col-xs-12">
			<div class="col-xs-3">
				<div class="jfj-person-card-image" id="image" style=\'background-image:url("/wp-content/uploads/' . $profile_image_prefix . '.jpg");\'>
				</div>
			</div>
			<div class="jfj-person-card-info col-xs-9">';
				if($user_meta_data[first_name][0] == $user_meta_data[last_name][0]) {
				echo '<h2>' . $user_meta_data[first_name][0] . '</h2>'; 
				} else {
				echo '<h2>' . $user_meta_data[first_name][0] . ' ' . $user_meta_data[last_name][0] . '</h2>'; 
				}

				if($parsed_role != ""){
				echo '<p class="jfj-person-card-role">' . $parsed_role[0][value] . '</p>'; 
				}

				echo '<p class="jfj-person-card-bio" id="bio">' . $user_meta_data[description][0] . '</p>'; 
				
				// Dome Calling Area
				if($parsed_dome[0][value] != ""){
					echo '<p class="jfj-person-card-calling-area"><span class="jfj-person-card-calling-area-label">Calling Area</span> <span class="jfj-person-card-calling-area-info">' . $calling_area . '</span></p>';
				};
				// Skype ID
				if($parsed_skype[0][value] != ""){
					echo '<p class="jfj-person-card-skype"><span class="jfj-person-card-skype-label">Skype</span> <span class="jfj-person-card-skype-info">' . $skype_id . '</span></p>';
				};				
				// Phone Number
				if($parsed_phone[0][value] != ""){
					echo '<p class="jfj-person-card-phone-number"><span class="jfj-person-card-phone-number-label">Phone</span> <span class="jfj-person-card-phone-number-info">' . $phone_num . '</span></p>';
				};
				
				// Email/Contact
				echo "<a class=\"jfj-person-card-email col-xs-4 col-sm-2\" href=\"/contact-staff/?ref=" . $user_meta_data[first_name][0] . "%20" . $user_meta_data[last_name][0] . "\"><img src=\"/wp-content/themes/jews-for-jesus/images/contact-envelope.svg\"></a> ";
				
				// Facebook
				if($user_meta_data[facebook][0] != ""){
					echo "<a class=\"jfj-person-card-facebook col-xs-4 col-sm-2\" href=\"" . $user_meta_data[facebook][0] . "\"><img id=\"facebook\" <img src=\"/wp-content/themes/jews-for-jesus/images/social_facebook-white.svg\"></a> ";
				};
				// Twitter
				if($user_meta_data[twitter][0] != ""){
					echo "<a class=\"jfj-person-card-twitter col-xs-4 col-sm-2\" href=\"http://www.twitter.com/" . $user_meta_data[twitter][0] . "\"><img src=\"/wp-content/themes/jews-for-jesus/images/social_twitter-white.svg\"></a> ";
				};
				// LinkedIN
				if($parsed_linkedin[0][value] != ""){
					echo "<a class=\"jfj-person-card-linkedin col-xs-4 col-sm-2\" href=\"http://linkedin.com/in/" . $parsed_linkedin[0][value] . "\"><img src=\"/wp-content/themes/jews-for-jesus/images/social_linkedin-white.svg\"></a> ";
				};

				// Articles
				echo "<a class=\"jfj-person-card-articles col-xs-4 col-sm-2\" href=\"/articles/?authors=" . $user_meta_data[first_name][0] . "-" . $user_meta_data[last_name][0] . "\"><img src=\"/wp-content/themes/jews-for-jesus/images/contact-list.svg\"></a>";	
							
				// Events
				echo "<a class=\"jfj-person-card-events col-xs-4 col-sm-2\" href=\"/connect/attend-events/?speaker=" . $user_meta_data[first_name][0] . "+" . $user_meta_data[last_name][0] . "\"><img src=\"/wp-content/themes/jews-for-jesus/images/contact-calendar.svg\"></a> ";	
				
				// Donate
				if($parsed_donation[0][value] != ""){
					echo "<a class=\"jfj-person-card-donate col-xs-4 col-sm-2\" href=\"http://" . $parsed_donation[0][value] . "\"><img src=\"/wp-content/themes/jews-for-jesus/images/contact-donate.svg\"></a> ";
				} else {
					echo "<a class=\"jfj-person-card-donate col-xs-4 col-sm-2\" href=\"https://store.jewsforjesus.org/" . substr(strtolower($user_meta_data[first_name][0]), 0, 2) . substr(strtolower($user_meta_data[last_name][0]), 0, 4) . "\"><img src=\"/wp-content/themes/jews-for-jesus/images/contact-donate.svg\"></a> ";
				};

			echo '</div>
		</div>
	</div>
</div>
';			
	}
	
	
	
	print '</span>';
}

?>