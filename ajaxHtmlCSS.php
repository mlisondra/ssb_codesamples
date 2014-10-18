<div id="admin" class="tab-content" style="width:1100px;">

	<div class="admin-section" id="set-alert">
		<h3>Set Alert Message</h3>
		<textarea cols="80" class="tinymce" id="alert"><?php echo $alert_message; ?></textarea>
		<input type="submit" class="save_button" id="save-alert" value="Save" />
	</div>

	
	<div class="admin-section" id="set-dash-status">
		<h3>Set Dashboard Status</h3>
		<textarea cols="80" class="tinymce" id="status"><?php echo $status_message; ?></textarea>
		<input type="submit" class="save_button" id="save-dash-status" value="Save" />
	</div>
	
	<div class="admin-section" id="set-activity-status">
		<h3>Set Activity Status</h3>
		<textarea cols="80" class="tinymce" id="activity_status"><?php echo $activity_status; ?></textarea>
		<input type="submit" id="save-activity-status" class="save_button" value="Save" />
	</div>

	<div class="admin-section" id="set-calendar-page-title">
		<h3>Set Calendar Title</h3>
		<input type="text" name="calendar_title" id="calendar_title" style="width:350px;" value="<?php echo $calendar_title; ?>" maxlength="50"><br/>
		<input type="submit" class="save_button" id="save_calendar_title_text" value="Save" />
	</div>     
	<div class="admin-section" id="set-calendar-text">
		<h3>Set Calendar Footer Text</h3>
		<textarea cols="80" class="tinymce" id="calendar_footer_text"><?php echo $calendar_footer_text; ?></textarea>
		<input type="submit" class="save_button" id="save_calendar_footer_text" value="Save" />

	</div> 
    
    <div class="admin-section" id="calendar_activities" style="margin:10px 0px 0px 0px;">
        <h3>Manage Calendar Activities</h3><span class='notification'></span>
       
        <table id="calendar_table">
            <tr>
                <th>Activity</th>
                <th>APAC (SG)</th>
                <th>China (SG)</th>
                <th>EMEIA (CORK)</th>
                <th>AMR (Austin)</th>
                <th>CORP (Cupertino)</th>
                <th>Active</th>
            </tr>
    <?php
        $display_times = $display_times;
        $time_zones_array = $timezones;
    
        foreach($activities as $activity){
    ?>
            <form id="<?php echo $activity->id; ?>">
            <tr id="<?php echo $activity->id; ?>">
            <td><?php echo $activity->activity_title; ?></td>
           <?php $time_zones_array = array("apac","china","emeia","amr","corp"); ?>
            <?php foreach($time_zones_array as $timezone){ ?>
            <td>
            <?php
             // Set display values for time and day depending on the timezone
             switch($timezone){
                 case "apac":
                    $curtime_display = $activity->apac;
                    $curday_display = $activity->apac_day;
                    break;
                 case "china":
                     $curtime_display = $activity->china;
                     $curday_display = $activity->china_day;
                     break;
                 case "emeia":
                     $curtime_display = $activity->emeia;
                     $curday_display = $activity->emeia_day;
                     break;  
                 case "amr":
                     $curtime_display = $activity->amr;
                     $curday_display = $activity->amr_day;
                     break;
                 case "corp":
                     $curtime_display = $activity->corp;
                     $curday_display = $activity->corp_day;
                     break;                 
             }
             
             $curtime_display = date("g A",strtotime($curtime_display)); // Sets format as 10 AM
             ?>
                <select class="time" name="<?php echo $timezone; ?>">
                    <?php 
                        foreach($display_times as $time){
                            if($time != $curtime_display){
                                echo '<option>'.$time.'</option>';
                            }else{
                                echo '<option selected>'.$time.'</option>';
                            }
                        }
                    ?>
                </select>
                <select class="day" name="<?php echo $timezone; ?>_day">
                    <?php
                        for($i = 1; $i<=5;$i++){
                            if($i != $curday_display){
                                echo '<option value="'.$i.'">Day ' . $i .'</option>';
                            }else{
                                echo '<option value="'.$i.'" selected>Day ' . $i .'</option>';
                            }
                        }
                    ?>
                </select>
            </td>
            <?php } ?>
            
            <td align="center"><input type="checkbox" value="1" class="active" name="active" <?php if($activity->active == 1){ echo 'checked'; } ?>></td>
        </tr>
            </form>
    <?php
        }
    ?>
        </table>
    </div>
    <div style="height:20px;"></div>
	
</div> <!-- /admin -->


<!-- Load TinyMCE -->
<script type="text/javascript" src="js/tiny_mce/jquery.tinymce.js"></script>
<script type="text/javascript">
	$().ready(function() {
		$('textarea.tinymce').tinymce({
			// Location of TinyMCE script
			script_url : 'js/tiny_mce/tiny_mce.js',

			// General options
			theme : "advanced",
			//plugins : "autolink,lists,pagebreak,style,layer,table,save,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,template,advlist",

			theme_advanced_buttons1 : "bold,italic,underline,separator,forecolor,backcolor,separator,fontsizeselect,link",
			extended_valid_elements : "a[href],strong/b,em/i,br,p,h1,h2,h3,h4,h5,h6,ul,li,ol,font[color|size|_moz_dirty],u,span[class|style]",
			// Example content CSS (should be your site CSS)
	//		content_css : "css/content.css",


		});
	});

        
        function submitMessage(newData,message_type){
            $.ajax( {
                    url: location.protocol + '//' + location.host + '/admin/save_message',
                    type: "POST",
                    data: {markup: newData,message_type:message_type},
                    success: function(data) {
                            alert('Message saved');
                    }
            });
        }
	
	$('.save_button').click(function(){
            parent_id = $(this).parent().attr("id");
            if($(this).attr("id") != "save_calendar_title_text"){
                textarea_id = $("#" + parent_id + " textarea.tinymce").attr("id");
                submitMessage($("#" + parent_id + " textarea.tinymce").val(),textarea_id);                
            }else{
                submitMessage($("#calendar_title").val(),"calendar_title");
            }
	});

        // Triggers saving for the time and day select fields
        $(".time, .day, .active").on("change",function(){
            var activity_id = $(this).closest("tr").attr("id"); // Get the activity id
            var post_data = $("form#"+ activity_id).serialize() + "&id=" + activity_id;
            var notification = "";
            $.post("/admin/update_activity",post_data,function(data){
                if(data.message == "success"){
                    notification = "Calendar successfully saved";
                    $("#calendar_activities span.notification").css("color","green");
                }else{
                    notification = "Calendar data could not be saved at this time";
                    $("#calendar_activities span.notification").css("color","red");
                }
                
                $("#calendar_activities span.notification").html(notification);
                $("#calendar_activities span.notification").show();
                setTimeout(function(){
                    $("#calendar_activities span.notification").html('');
                },2000);
            },"json");
        });

</script>

<script type="text/javascript">
function init_Admin() {
	//alert ('loadScripts: admin');
}
</script>