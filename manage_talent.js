$(document).ready(function(){
	//Global variables
	var skill_id = $("#edit_talent_form #id").val();

	get_media("allvideos","Active");
	get_media("allvideos","Archive");

	get_media("allaudio","Active");
	get_media("allaudio","Archive");

	get_media("documents","Active");
	get_media("documents","Archive");

	get_images();
	get_images('Archive');

	get_websites();
	get_websites("Archive");	

	$(".add_talent_auto").autocomplete({
		minLength: 1,
		source: function(request,response){
			element_id = $(this.element).attr("id"); //get the input that is currently binded
			if(element_id == "industry"){
				data_to_get = "get_industries";
			}else if(element_id == "category"){
				data_to_get = "get_categories";
			}
			$.ajax({
				url: autocomplete_controller,
				dataType: "json",
				data:{
					term: request.term,
					action: data_to_get
				},
				type: "POST",
				success: function(data){
					if(data != 0){
						 response($.map(data, function (item) {
                            return {
                                label: item.label,
                                value: item.value
                            }
                        }));									
					}else{ //if there are no results hide list
						$("ul").css("class","ui-autocomplete").hide();	
					}
				}
			});
		},
		autoFocus : true			
	});			

	//Validate Edit Talent form
	$("#edit_talent_form").validate({
		rules: {
			industry: {
				required : true
			},
			category: {
				required : true
			},
			title : {
				required : true,
				validTalentName : true
			},
			rate : {
				required : true,
				money : true 
			},
			experience_years : "required",
			contract_agreement : "required",
			rate_type : "required"
		},
		messages:{
			industry: "Choose a Field/Industry",
			category: "Choose a Category",
			title : {
				required : "Enter a Talent Name",
				validTalentName : "Only letters, numbers, spaces, and parentheses are allowed"	
			},
			rate: "Enter a Rate",
			experience_years : "Select Years of Experience",
			contract_agreement: "Please read and check",
			rate_type : "Select Rate Type"
			
		},
		submitHandler : function(form){
      var tagIds = [];
      $.each($('#keywords').val().split(','),
        function(idx, tagLabel) {
          tagIds.push($('#keywords').data(tagLabel));
        }
      );
      $('#keywords_ids').val(tagIds.join(','));

      var to_redirect = $('#title').val() != $('#prev_title').val();
			post_data = $(form).serialize();
			$.post(skills_controller,post_data,
				function(data){
					if(data.status == "success"){
            if (to_redirect) {
              window.location.href = '/accounts/?view=manage_talent&talent=' + $('#title').val();
            }

						$("#talent_title").val($("#edit_talent_form #title").val()); //add new talent title to hidden input
						new_link_to_talent = "../" + $('#logged_in_username').val() + "/" + $('#talent_title').val();
						$('#finish_button').attr('href',new_link_to_talent); //Change href attribute for Finish Button						
						$("#talent_overview h2").html($("#edit_talent_form #title").val());
						category = "<b>Category:</b> " + $("#edit_talent_form #category").val();
						industry = "<b>Industry:</b> " + $("#edit_talent_form #industry").val();
						experience = $("#edit_talent_form #experience").val();

            if ($("#edit_talent_form #experience_years").length) {
              experience_years_selected_value = $("#edit_talent_form #experience_years").val();
              $("#talent_overview span#experience_years").html(
                $("#edit_talent_form #experience_years option[value='"+ experience_years_selected_value +"']").text()
              );
            }

            if ($("#edit_talent_form #rate").length) {
              rate = $("#edit_talent_form #rate").val();
              rate = rate.replace("$",""); //Strip out dollar sign
              rate = new Number(rate); //Create new Number object
              rate_formatted = rate.formatMoney(2, '.', ','); //08/16/2012 Changed to include 2 numbers after decimal Milde Lisondra

              rate_type_selected = $("#edit_talent_form #rate_type").val(); //Added 08/16/2012 Milder Lisondra
              rate_formatted = rate_formatted + "/" + rate_type_selected; //Added 08/16/2012 Milder Lisondra

              if ($('#hourly_rate_chkbox').is(':checked')) { //If user selected Negotiable check box adjust display text
                $("#talent_overview #rate").html("$" + rate_formatted + " - Negotiable");
              } else {
                $("#talent_overview #rate").html("$" + rate_formatted);
              }
            }

						$("#talent_overview #category").html(category);
						$("#talent_overview #industry").html(industry);
						formatted_experience = nl2br(experience);
						$("#talent_overview #experience").html(formatted_experience);

						if($('#edit_talent_form #public_view').is(':checked')){
							$("#talent_overview #public_view #setting").html("Yes"); 
						}else{
							$("#talent_overview #public_view #setting").html("No");	
						}
						if($('#edit_talent_form #public_view_media').is(':checked')){
							$("#talent_overview #public_view_media #setting").html("Yes"); 
						}else{
							$("#talent_overview #public_view_media #setting").html("No");	
						}						
						$("#edit_confirm").show();
						close_edit_talent_modal();
					}
				},"json"
			); //end ajax post
			return false;
		} 				
	}); //End Validate Edit Talent form

  $('#keywords').tagit({
    removeConfirmation: true,
    tagSource: function(search, showChoices) {
      $.ajax({
        url: autocomplete_controller,
        dataType: 'json',
        data: {
          action: 'get_keywords',
          term: search.term
        },
        success: function(data) {
          var choices = [];
          $.each(data, function(idx, tag) {
            choices.push(tag.value);
            $('#keywords').data(tag.value, tag.id);
          });
          showChoices(choices);
        }
      });
    },
    beforeTagAdded: function(event, ui) {
      return ($('#keywords').data(ui.tagLabel) != undefined);
    }
  });

  var keywords = $('#talent_keywords').val();
  var keywords_obj = $.parseJSON(keywords);
  if (keywords_obj.length) {
    for (var i = 0; i < keywords_obj.length; i++) {
      $('#keywords').data(keywords_obj[i].name, keywords_obj[i].id);
      $('#keywords').tagit('createTag', keywords_obj[i].name);
    }
  }

	//Setup Edit Talent Modal
	$(".edit_talent_modal").dialog({
		modal: true,
		closeOnEscape: true,
		autoOpen: false,
		resizable: false,
		draggable: false,
		minWidth: 750,
		height: 1000, //modified on 03/04/2013. Originally set at 720
		open: function(event, ui) { 
			$(".ui-dialog-titlebar-close").hide();
			window.setTimeout(function() {
	            jQuery(document).unbind('mousedown.dialog-overlay')
	                            .unbind('mouseup.dialog-overlay');
	        }, 100);			
		}
	}); //End Setup Edit Talent Modal;    

	//Setup Upload Media Modal
     $('.upload_modal').dialog({
		modal: true,
		show : 'fade',
		hide : 'fade',
		autoOpen: false,
		resizable: false,
		draggable: false,
		minWidth: 750,
		height: 600,
		open: function(event, ui) { 
			$(".ui-dialog-titlebar-close").hide();
		}			        
    }); //End Setup Upload Media Modal    
    
    //Setup Alert Modal
     $('.alert_modal').dialog({
		modal: true,
		closeOnEscape: false,
		hide : 'fade',
		autoOpen: false,
		resizable: false,
		draggable: false,
		minWidth: 619,
		open: function(event, ui) { 
			$(".ui-dialog-titlebar-close").hide();
		}
	});   

    //Setup Edit Talent Button
    $(".blue_button").click(function(){
    	$("#talent_info").show();
		$("#edit_confirm").hide();	    	
    	open_edit_talent_modal();
    });

    //Setup buttons inside Edit Talent Modal
    $("#edit_talent_form .grey_button_95").click(function(){ //Cancel
    	document.edit_talent_form.reset();
    	close_edit_talent_modal();
    });

    //Open Media Upload modal
    $(".add_media_button").click(function(){
    	elem_id = $(this).attr("id");
    	skill_id = $("#edit_talent_form #id").val();
    	account_id = $("#logged_in_userid").val();

    	switch(elem_id){
    		case "videos":
    			h2_text = "Videos &amp; Animations";
    			media_type = "videos";
    			break;
    		case "images":
    			h2_text = "Photos &amp; Images";
    			media_type = "images";
    			break;
    		case "audio_clips":
    			h2_text = "Audio Clips";
    			media_type = "audio_clips";
    			break;
    		case "documents":
    			h2_text = "Documents";
    			media_type = "documents";
    			break;
    	}

    	parameters = "account_id="+ account_id +"&skill_id=" + skill_id + "&media_type=" + media_type+"&type=skill";
    	if(elem_id == "videos"){
    		content = '<iframe src="../upload/add_video.php?'+ parameters + '" name="add_iframe" id="add_iframe" frameborder="0" width="505" height="450"></iframe>';
    	}else if(elem_id == "images"){
    		content = '<iframe src="../upload/add_photo.php?'+ parameters + '" name="add_iframe" id="add_iframe" frameborder="0" width="500" height="450"></iframe>';
    	}else{
    		content = '<iframe src="../upload/index_alt.php?'+ parameters + '" name="add_iframe" id="add_iframe" frameborder="0" width="500" height="450"></iframe>';
    	}

    	$(".upload_modal .modal_middle").html(content);
    	setTimeout(open_upload_modal,100);
    });

    //Open Edit Media Upload modal
    //Applies to Videos/Animations, Photos/Images, Audio Clips, and Documents only
    $(".links a.edit").live("click",function(){
    	elem_id = $(this).attr("id");
    	account_id = $("#logged_in_userid").val();
    	$("#edit_upload_modal").dialog().parents(".ui-dialog").find(".ui-dialog-titlebar").remove();

    	containing_div = ($(this).closest('ul.ui-sortable').parent('div').attr("class"));

    	if(containing_div == "list_box_right_0" || containing_div == "list_box_right_1" || containing_div == "list_box_right_2" || containing_div == "list_box_right_3"){
			$(".alert_modal #modal_alert_middle h1").html('Move from Archive to edit');
			$(".alert_modal #modal_alert_middle p").html('');			
			open_alert_modal();	
			$(".alert_modal a.green_button_95").hide();
			$(".alert_modal a.grey_button_95").hide();
			setTimeout(close_alert_modal,1500);
		}else{
	    	//Determine media type from container that holds the sortable list (UL)
			if(containing_div == "list_box_left_0" || containing_div == "list_box_left_0"){
				h2_text = "Videos &amp; Animations";
				media_type = "videos";
			}else if(containing_div == "list_box_left_1" || containing_div == "list_box_left_1"){
				h2_text = "Photos &amp; Images";
				media_type = "images";			
			}else if(containing_div == "list_box_left_2" || containing_div == "list_box_left_2"){
				h2_text = "Audio Clips";
				media_type = "audio_clips";			
			}else if(containing_div == "list_box_left_3" || containing_div == "list_box_left_3"){
				h2_text = "Documents";
				media_type = "documents";			
			}

			parameters = "account_id="+ account_id +"&skill_id=" + skill_id + "&media_type=" + media_type+"&type=skill&media_id=" + elem_id;
			if(media_type == "videos"){
        if ($(this).data('media') == 'embed_videos') {
          content = '<iframe src="../upload/edit_embedded_videos.php?'+ parameters + '" name="add_iframe" id="add_iframe" frameborder="0" width="500" height="450"></iframe>';
        } else {
          content = '<iframe src="../upload/edit_videos.php?'+ parameters + '" name="add_iframe" id="add_iframe" frameborder="0" width="500" height="450"></iframe>';
        }
			}else if(media_type == "images"){
				content = '<iframe src="../upload/edit.php?'+ parameters + '" name="add_iframe" id="add_iframe" frameborder="0" width="500" height="450"></iframe>';
			}else{
	    		content = '<iframe src="../upload/edit_alt.php?'+ parameters + '" name="add_iframe" id="add_iframe" frameborder="0" width="500" height="450"></iframe>';
	   		}

	    	$(".upload_modal .modal_middle").html(content);
	    	setTimeout(open_upload_modal,100);  
    	}
    });

	//Validate Add website
	$("#add_website_form").validate({
		rules: {
			website : {
				validateURL : true
			}
		},
		messages:{
			website: "<br/>Enter a valid website url (Example: http://www.talentearth.com or www.talentearth.com)"
		},
		submitHandler : add_url				
	}); //end Validate Add website

	//Setup Delete for Media
	$(".links a.delete").live("click",function(){
		containing_div = $(this).closest('.ui-sortable').parent('div').attr("class");
		url_id = $(this).attr("id");
		media_id = url_id;

		$("#modal_alert #modal_top h1").html("");

		//check to see which list the delete request came from
		if(containing_div == "list_box_left_5" || containing_div == "list_box_right_5"){
			content = "Are you sure you want to delete this URL?";	
		}else if(containing_div == "list_box_left_3" || containing_div == "list_box_right_3"){
			content = "Are you sure you want to delete this document?";
		}else if(containing_div == "list_box_left_1" || containing_div == "list_box_right_1"){
			content = "Are you sure you want to delete this image?";
			containing_ul = $(this).closest('ul').attr("id"); //Get the category id
		}else if(containing_div == "category_list_container"){
			content = "Are you sure you want to delete this category? <br/>All associated media will be deleted";
		}else if(containing_div == "list_box_left_0" || containing_div == "list_box_right_0"){
			content = "Are you sure you want to delete this video?";
		}else if(containing_div == "list_box_left_2" || containing_div == "list_box_right_2"){
			content = "Are you sure you want to delete this audio clip?";
		}else if(containing_div == "list_box_left_4" || containing_div == "list_box_right_4"){ //References
			content = "Are you sure you want to delete this Reference?";
		}		
		$("#modal_alert #modal_alert_middle h1").html(content);
		open_alert_modal();
	});		

	//bind modal alert grey button to close modal
	$("#modal_alert .grey_button_95").click(function(){
		close_alert_modal();
	});	

	//Bind Alert Modal green button to actually delete requested Media asset
	//As of 12/30/2011 handles Photos/Images, Websites, and Documents
	$("#modal_alert .green_button_95").click(function(){
		success_delete_message = "";
		failed_delete_message = "";
		controller_to_use = "";
		recalc_this_div = "";

		if(containing_div == "list_box_left_1" || containing_div == "list_box_right_1"){ //images
			success_delete_message = "Image deleted";
			failed_delete_message = "Image could not be deleted";
			post_data = "action=delete_media&media_id=" + media_id;
			controller_to_use = media_controller;
			recalc_this_div = "images";
		}else if(containing_div == "list_box_left_5" || containing_div == "list_box_right_5"){ //websites
			success_delete_message = "URL deleted";
			failed_delete_message = "URL could not be deleted";
			post_data = "action=delete_url&url_id=" + url_id;
			controller_to_use = skills_controller;
			recalc_this_div = "websites";
		}else if(containing_div == "list_box_left_3" || containing_div == "list_box_right_3"){ //documents
			success_delete_message = "Document deleted";
			failed_delete_message = "Document could not be deleted";			
			post_data = "action=delete_media&media_id=" + media_id;
			controller_to_use = media_controller;
			recalc_this_div = "documents";
		}else if(containing_div == "category_list_container"){ //categories
			success_delete_message = "Category deleted";
			failed_delete_message = "Category could not be deleted";
			post_data = "action=delete_skill_media_category&category_id=" + media_id + "&skill_id=" + skill_id;
			controller_to_use = media_controller;
		}else if(containing_div == "list_box_left_0" || containing_div == "list_box_right_0"){
			success_delete_message = "Video deleted";
			failed_delete_message = "Video could not be deleted";
			post_data = "action=delete_media&media_id=" + media_id;
			controller_to_use = media_controller;
			recalc_this_div = "videos";
		}else if(containing_div == "list_box_left_2" || containing_div == "list_box_right_2"){
			success_delete_message = "Audio Clip deleted";
			failed_delete_message = "Audio Clip could not be deleted";
			post_data = "action=delete_media&media_id=" + media_id;
			controller_to_use = media_controller;
			recalc_this_div = "audio_clips";
		}else if(containing_div == "list_box_left_4" || containing_div == "list_box_right_4"){
			success_delete_message = "Reference deleted";
			failed_delete_message = "Reference could not be deleted";
			post_data = "action=delete_reference&media_id=" + media_id + "&skill_id=" + skill_id; //alert(post_data);
			controller_to_use = references_controller;
			recalc_this_div = "reference_expand";
		}		

			//AJAX post to delete Media asset
			$.post(controller_to_use,post_data,
				function(data){
					if(data.status == "success"){
						$("#modal_alert .green_button_95").hide();
						$("#modal_alert .grey_button_95").hide();
						$("#modal_alert #modal_alert_middle h1").html(success_delete_message);
						$("." + containing_div + " ul li#id_" +url_id).remove(); //Remove media from list

						recalc(recalc_this_div);

						if(containing_div == "category_list_container"){ //If the item removed came from the Categories list in the Modal update Photos/Images main list
							update_images_list();
						}
							if(containing_div == "list_box_left_1" || containing_div == "list_box_right_1"){ //images
								if($('#' + containing_ul + ' li').size() == 0){
									none_content = '<li class="none"><div class="multi_media"></div><div class="links"></div></li>';
									$("#" + containing_ul).hide().append(none_content).fadeIn('slow');								
								}								
							}else{
								if($('.' + containing_div + ' li').size() == 0){
									none_content = '<li class="none"><div class="title">None found</div><div class="links"></div><div class="list_hr"></div></li>';
									$("." + containing_div + " ul").hide().append(none_content).fadeIn('slow');								
								}
							}		

						setTimeout(close_alert_modal,500);
					}else{
						$("#modal_alert #modal_alert_middle h1").html(failed_delete_message);
					}
				},"json"
			);	

                update_dashboard();
	});

    //Setup image gallery modal
     $('#image_gallery_modal').dialog({
		modal: true,
		closeOnEscape: true,
		hide : 'fade',
		autoOpen: false,
		resizable: false,
		draggable: false
	});

	///////////////////////////////////////////////////////////Categories////////////////////////////////////////////////////////////
	//Setup Upload Media Modal
     $('.category_modal').dialog({
		modal: true,
		//show : 'fade',
		hide : 'fade',
		close: function(){
			document.add_category_form.reset();
			$('#add_category_form').hide();
		},
		closeOnEscape: true,
		autoOpen: false,
		resizable: false,
		draggable: false,
		minWidth: 750,
		minHeight: 450,
		open: function(event, ui) { 
			$(".ui-dialog-titlebar-close").hide();
		}			        
    }); //End Setup Upload Media Modal

	//Setup Close button in Category Modal
	$('.category_modal .modal_middle #close_category_modal_btn').click(function(){
		close_category_modal();
	});

	//Setup Manage Category link/button
	//Skill id is set by a global JS variable
	//located at top of Doc Ready
	$("#manage_categories").click(function(){
		open_category_modal();
		post_data = "action=get_skills_media_categories&skill_id=" + skill_id;
		$.post(media_controller,post_data,
			function(data){
				if(data.num > 0){
					$('.category_modal #category_list').html(data.content);
					$('.category_modal div.title').editable(function(value,settings){ //Create editable input area for Category
						li_id = $(this).closest('li').attr("id");
						category_id = li_id.substring(3); //Begin substring extraction after the underscore
						$.post(media_controller,{"action":"update_skills_media_category","name":value,"id":category_id,"skill_id":skill_id});
						update_images_list();
						return(value);
					
					});
				}
			},"json"
		);
	});

	//Create Category sortable
    $(".category_modal ul").sortable({
		//connectWith: ".list_box_left_1 ul,.list_box_right_1 ul", //connect with other Image categories and the Archive category
		items: 'li:not(.none)',
		dropOnEmpty: true,
		revert: true,
		opacity: 0.6,
		update: function(event,ui){
			$(ui.item).attr("id");
			action = "sort_categories";
			post_data = $(this).sortable("serialize") + "&action=" + action + "&skill_id=" + skill_id;
			$.post(media_controller,post_data,
				function(data){
					update_images_list();	
				},"json"	
			);
		}
	}).disableSelection();

	//Setup Add Category link in Category Modal
	$('.category_modal .modal_middle #add_category').click(function(){
		$('#add_category_form').show();
	});

	//Setup Add Category Form submit
	$('form#add_category_form').submit(function(form){
		form.preventDefault();
		if($("#add_category_form #name").val() != ""){
			//check to see if category name already exists
			post_data = "action=add_skill_media_category&skill_id=" + skill_id + "&name=" + $('#add_category_form #name').val();
			$.post(media_controller,post_data,
				function(data){
					if(data.status == "success"){
						message = "Your Category has been added";
						$('#add_category_form').hide();
						document.add_category_form.reset();
						get_skill_media_categories(); //Retrieve categories and refresh modal list
						update_images_list(); //Update Photos/Images main list
					}else{
						message = data.message;
					}

					$('#add_category_notification').html(message);
					$('#add_category_notification').fadeIn('fast',function(){
					$('#add_category_notification').fadeOut(5000);  
					});
				},"json"
			);			
		}else{
			message = "Enter a Category Name";
			$('#add_category_notification').html(message);
			$('#add_category_notification').fadeIn('fast',function(){
				$('#add_category_notification').fadeOut(5000);
			}); 
		}
	});

	//Setup Add Category form buttons
	$('#add_category_form .grey_button_95').click(function(){
		document.add_category_form.reset();
		$('#add_category_form').hide();
	});
}); ////////////////////////////////////////////////////////// end doc ready ////////////////////////////////////////////////////////////

	function save_category_name(name){
		//check to see if category name already exists
		post_data = "action=add_skill_media_category&skill_id=" + skill_id + "&name=" + $('#add_category_form #name').val();
		$.post(media_controller,post_data,
			function(data){
				if(data.status == "success"){
					message = "Your Category has been added";
					$('#add_category_form').hide();
					document.add_category_form.reset();
					get_skill_media_categories(); //Retrieve categories and refresh modal list
					update_images_list(); //Update Photos/Images main list
				}else{
					message = data.message;
				}

				$('#add_category_notification').html(message);
				$('#add_category_notification').fadeIn('fast',function(){
				$('#add_category_notification').fadeOut(5000);  
				});
			},"json"
		);
	//});
	}

	//Helper functions for opening/closing modals
    function open_no_category_modal(){
	$("#no_category_modal").dialog().parents(".ui-dialog").find(".ui-dialog-titlebar").remove();
	$("#no_category_modal").dialog('open');
    }

    function close_no_category_modal(){
        $("#no_category_modal").dialog('close');
    }

	function open_category_modal(){
		$('.category_modal').dialog().parents(".ui-dialog").find(".ui-dialog-titlebar").remove();
		$('.category_modal').dialog('open');
	}

	function close_category_modal(){
		$('.category_modal').dialog('close');
	}

	function open_edit_talent_modal(){
		$(".edit_talent_modal").dialog().parents(".ui-dialog").find(".ui-dialog-titlebar").remove();
		$('.edit_talent_modal').dialog('open');	
	}

	function close_edit_talent_modal(){
		$('.edit_talent_modal').dialog('close');	
	}

	function open_upload_modal(){
		$(".upload_modal").dialog().parents(".ui-dialog").find(".ui-dialog-titlebar").remove();
		$(".upload_modal").dialog('open');	
	}	

	function close_upload_modal(){
		$(".upload_modal").dialog('close');	
	}

	function open_edit_upload_modal(){
		$('#edit_upload_modal').dialog('open');
	}
	function close_edit_upload_modal(){
		$("#edit_upload_modal").dialog("close");		
	}

	function open_alert_modal(){
		$("#modal_alert").dialog().parents(".ui-dialog").find(".ui-dialog-titlebar").remove();
		$("#modal_alert").dialog('open');
	}
	function close_alert_modal(){
		$(".alert_modal").dialog('close');
		$(".alert_modal #modal_alert_middle h1").html('');
		$(".alert_modal .green_button_95").show();
		$(".alert_modal .grey_button_95").show();		
	}
	//End Helper functions for opening/closing modals

	/**
	 * 
	 */
	function get_skill_media_categories(){
		skill_id = $("#edit_talent_form #id").val();
		post_data = "action=get_skills_media_categories&skill_id=" + skill_id;
		$.post(media_controller,post_data,
			function(data){
				if(data.num > 0){
					$('.category_modal #category_list').html(data.content);
					$('.category_modal').dialog().parents(".ui-dialog").find(".ui-dialog-titlebar").remove();
					$('.category_modal').dialog('open');
				}
			},"json"
		);		
	}

	/*
	 * Recalculate the individual lists for the given media time
	 */
	function recalc(media_type){
		switch(media_type){
			case "videos":
				$("#video_expand .list_box_left_0").css("height","auto");
				$("#video_expand .list_box_right_0").css("height","auto");		
				mediaLeftBox = $('#video_expand .list_box_left_0').height();
				mediaRightBox = $('#video_expand .list_box_right_0').height();

				if (mediaLeftBox > mediaRightBox){
					$('#video_expand .list_box_left_0').height(mediaLeftBox);	
				} else {
					$('#video_expand .list_box_right_0').height(mediaRightBox);
				}
				break;
			case "websites":
				$("#website_expand .list_box_left_5").css("height","auto");
				$("#website_expand .list_box_right_5").css("height","auto");		
				websiteLeftBox = $('#website_expand .list_box_left_5').height();
				websiteRightBox = $('#website_expand .list_box_right_5').height();
				if (websiteLeftBox > websiteRightBox){
					$('#website_expand .list_box_right_5').height(websiteLeftBox);	
				} else {
					$('#website_expand .list_box_left_5').height(websiteRightBox);
				}
				break;
			case "documents":
				$("#document_expand .list_box_left_3").css("height","auto");
				$("#document_expand .list_box_right_3").css("height","auto");		
				mediaLeftBox = $('#document_expand .list_box_left_3').height();
				mediaRightBox = $('#document_expand .list_box_right_3').height();	
				if (mediaLeftBox > mediaRightBox){
					$('#document_expand .list_box_right_3').height(mediaLeftBox);	
				} else {
					$('#document_expand .list_box_left_3').height(mediaRightBox);
				}			
				break;
			case "images":
				imagesLeftBox = $('#image_expand .list_box_left_1').height();
				imagesRightBox = $('#image_expand .list_box_right_1').height();
				if (imagesLeftBox > imagesRightBox){
					$('#image_expand .list_box_right_1').height(imagesLeftBox);	
				} else {
					$('#image_expand .list_box_left_1').height(imagesRightBox);
				}			
				break;
			case "audio_clips":
				$("#audio_expand .list_box_left_2").css("height","auto");
				$("#audio_expand .list_box_right_2").css("height","auto");		
				mediaLeftBox = $('#audio_expand .list_box_left_2').height();
				mediaRightBox = $('#audio_expand .list_box_right_2').height();
				if (mediaLeftBox > mediaRightBox){
					$('#audio_expand .list_box_right_2').height(mediaLeftBox);	
				} else {
					$('#audio_expand .list_box_left_2').height(mediaRightBox);
				}
				break;
			case "references":
				$("#references_expand .list_box_left_4").css("height","auto");
				$("#references_expand .list_box_right_4").css("height","auto");		
				mediaLeftBox = $('#audio_expand .list_box_left_2').height();
				mediaRightBox = $('#audio_expand .list_box_right_2').height();
				if (mediaLeftBox > mediaRightBox){
					$('#audio_expand .list_box_right_2').height(mediaLeftBox);	
				} else {
					$('#audio_expand .list_box_left_2').height(mediaRightBox);
				}			
				break;
		}

		$('.vertically_center').vAlign();		
	}

	function open_media_div(media_type){
		switch(media_type){
			case "videos":
				div_to_expand = "video_expand";
				break;
			case "images":
				div_to_expand = "image_expand";
				break;
			case "audio_clips":
				div_to_expand = "audio_expand";
				break;
			case "documents":
				div_to_expand = "document_expand";
				break;
			case "websites":
				div_to_expand = "website_expand";
				break;			
		}

		//Open the media div if necessary
		if($("#"+div_to_expand).css('display') == "none"){
			$("#"+div_to_expand).show();
			$(".expand_button a[href='" + div_to_expand + "']").html("Collapse"); //change the inner html						
		}			
	}

	function close_media_div(media_type){
		switch(media_type){
			case "videos":
				div_to_expand = "video_expand";
				break;
			case "images":
				div_to_expand = "image_expand";
				break;
			case "audio_clips":
				div_to_expand = "audio_expand";
				break;
			case "documents":
				div_to_expand = "document_expand";
				break;
			case "websites":
				div_to_expand = "website_expand";
				break;			
		}
		//Close the media div if necessary
		//if($("#"+div_to_expand).is(':visible')){
			$("#"+div_to_expand).hide();
			$(".expand_button a[href='" + div_to_expand + "']").html("Expand"); //change the inner html						
		//}		
	}	

//JQuery Validators
$.validator.addMethod("defaultInvalid", function(value, element){
	result = true;
	if(element.id == "industry" && element.value == "Industry"){
		result = false;
	}else if(element.id == "category" && element.value == "Job Category"){
		result = false;
	}else if(element.id == "rate" && element.value == "$0.00"){
		result = false;
	}else if(element.id == "title" && element.value == ""){
		result = false;
	}else if(element.id == "name" && element.value == "Name"){
		result = false;
	}
    return this.optional(element) || result;
});

$.validator.addMethod("validateURL", function(value, element){
	result = true;
	pattern_to_match = /(((ht|f)tp(s?):\/\/)|(www\.[^ \[\]\(\)\n\r\t]+)|(([012]?[0-9]{1,2}\.){3}[012]?[0-9]{1,2})\/)([^ \[\]\(\),;&quot;'&lt;&gt;\n\r\t]+)([^\. \[\]\(\),;&quot;'&lt;&gt;\n\r\t])|(([012]?[0-9]{1,2}\.){3}[012]?[0-9]{1,2})/
	if(value.search(pattern_to_match) == -1){
		result = false;
	}else{
		result = true;
	}
    return this.optional(element) || result;
});		

$.validator.addMethod("money", 
	function(value, element) {
		return this.optional(element) || /^\$?(\d{1,3}(\,\d{3})*|(\d+))(\.\d{2})?$/.test(value);
	}, "(Sample format: $99.99 or $99 or $1,200 or $1200)"
);

$.validator.addMethod("validTalentName", 
	function(value, element) {
		return this.optional(element) || /^[a-zA-Z0-9\s\(\)]+$/.test(value);	
	}
);		

//end JQuery Validators

function show_file_text(input, text_holder){
	fullpath = document.getElementById(input).value;
	document.getElementById(text_holder).innerHTML = fullpath.replace("C:\\fakepath\\", "");
}

function add_url(){
	url = $("#add_website_form #website").val();
	action = "add_url";
	skill_id = $("#edit_talent_form #id").val();

	if(url != ""){
		post_data = "action=" + action + "&url=" + url + "&skill_id=" + skill_id;
		$.post(skills_controller,post_data,
			function(data){
				if(data.status == "success"){
					url_id = data.message;
					$("#add_website_form #website").val("");
					if($("#website_expand").is(':hidden')){
						$('#website_expand').slideToggle();								
					}

					url_scheme_check = /^(http|https):///
					if(url.search(url_scheme_check) == -1){
						url = "http://" + url;
					}					
					//append new site to list
					html = '<li id="id_'+ url_id +'"><div class="title"><a href="'+ url +'" target="_blank">' + url + '</a></div>';
	                html = html + '<div class="links"><a href="###" id="' + url_id + '" class="delete">Delete</a></div><div class="list_hr"></div></li>';
	                $(".list_box_left_5 ul").append(html);
	                $(".list_box_left_5 ul li.none").fadeOut();
	                open_media_div('websites');
					recalc('websites');
				}else{
					$('label[for="website"]').html("<br>"+data.message);
					$('label[for="website"]').show();
				}
			},"json"
		);
	}else{
		$('label[for="website"]').show();
	}				
}

function get_websites(list_type){
	if(list_type != undefined){
		list_type = "Archive"
	}else{
		list_type = "Active";
	}
	skill_id = $("#edit_talent_form #id").val();
	$.post(skills_controller,{"action":"get_websites","skill_id":skill_id,"status":list_type},
		function(data){
			if(data.status == "success"){
				if(list_type == "Active"){
					$("#website_expand .list_box_left_5 ul").html(data.content);				
				}else{
					$("#website_expand .list_box_right_5 ul").html(data.content);
				}
					create_media_sortable('websites');
					open_media_div('websites');
					recalc('websites');
					close_media_div('websites');
			}
		},"json"
	);
}

//Handles retrieval of Videos/Animations, Audio Clips, and Documents only
function get_media(media_type,list_type){
	if(media_type == undefined){
		media_type = "videos";
	}

	if(list_type == undefined){
		list_type = "Active";
	}

	if(media_type == "audio_clips"){
		media_type = "allaudio";
	}
	
	if(media_type == "videos"){
		media_type = "allvideos";
	}

	skill_id = $("#edit_talent_form #id").val();
	$.post(media_controller,{"action":"get_skill_media","skill_id":skill_id,"status":list_type,"media_type":media_type},
		function(data){
			if(data.status == "success"){
				if(list_type == "Active"){
					num_active = data.num;
					switch(media_type){
						case "videos":
							$("#video_expand .list_box_left_0 ul").html(data.content);
							break;
						case "allvideos":
							$("#video_expand .list_box_left_0 ul").html(data.content);
							break;
						case "audio":
							$("#audio_expand .list_box_left_2 ul").html(data.content);
							break;
						case "allaudio":
							$("#audio_expand .list_box_left_2 ul").html(data.content);
							break;
						case "documents":
							$("#document_expand .list_box_left_3 ul").html(data.content);
							break;
					}
				}else{
					num_archive = data.num;
					switch(media_type){
						case "videos":
							$("#video_expand .list_box_right_0 ul").html(data.content);
							break;
						case "allvideos":
							$("#video_expand .list_box_right_0 ul").html(data.content);
							break;
						case "audio":
							$("#audio_expand .list_box_right_2 ul").html(data.content);
							break;
						case "allaudio":
							$("#audio_expand .list_box_right_2 ul").html(data.content);
							break;
						case "documents":
							$("#document_expand .list_box_right_3 ul").html(data.content);
							break;							
					}
				}

				switch(media_type){
					case "videos":
						create_media_sortable('videos');
						break;
					case "allvideos":
						create_media_sortable('videos');
						break;
					case "audio":
						create_media_sortable('audio_clips');
						break;
					case "allaudio":
						create_media_sortable('audio_clips');
						break;
					case "documents":
						create_media_sortable('documents');
						break;
				}
					open_media_div(media_type);
					recalc(media_type);
					close_media_div(media_type);
			}
		},"json"
	);
}

//Used by Upload Modal upon a successful upload
function update_media_list(media_type){
	get_media(media_type);
	switch(media_type){
		case "videos":
			if($("#video_expand").css("display") == "none"){
				setTimeout(function(){
					$("#video_expand").slideToggle();
					$(".expand_button a[href='video_expand']").html("Collapse"); //change the inner html
				},500);
			}
			break;
		case "audio_clips":
			if($("#audio_expand").css("display") == "none"){
				setTimeout(function(){
					$("#audio_expand").slideToggle();
					$(".expand_button a[href='audio_expand']").html("Collapse"); //change the inner html
				},500);
			}
			break;
		case "documents":
			if($("#document_expand").css("display") == "none"){
				setTimeout(function(){
					$("#document_expand").slideToggle();
					$(".expand_button a[href='document_expand']").html("Collapse"); //change the inner html 
				},500);
			}
			break;
	}
}

//Primarily used by upload modal
function update_images_list(list_type){
	if(list_type != undefined){
		list_type = "Archive"
	}else{
		list_type = "Active";
	}

	$.post(media_controller,{"action":"get_skill_media_v3","skill_id":skill_id,"status":list_type,"media_type":media_type},
		function(data){
			if(data.status == "success"){
				if(list_type == "Active"){
					$("#image_expand .list_box_left_1").html(data.content);					
				}else{
					$("#image_expand .list_box_right_1 ul").html(data.content);
				}
				create_image_sortable();
				open_media_div('images');
				recalc('images');
				
					post_data = "action=get_skills_media_categories&skill_id=" + skill_id;
					$.post(media_controller,post_data,
						function(data){
							if(data.num <= 0){
								$(".list_box_left_1 ul").sortable("disable");
							}
						},"json"	
					);				
			}
		},"json"
	);	
}

function get_images(list_type){
	media_type = "images";
	if(list_type === undefined){
		list_type = "Active";
	}	

	skill_id = $("#edit_talent_form #id").val(); //Deprecated skill_id is now set at the top of doc ready
	$.post(media_controller,{"action":"get_skill_media_v3","skill_id":skill_id,"status":list_type,"media_type":media_type},
		function(data){
			if(data.status == "success"){
				if(list_type == "Active"){
					$("#image_expand .list_box_left_1").html(data.content);				
				}else{
					$("#image_expand .list_box_right_1 ul").html(data.content)
				}
					create_image_sortable(); //create sortables
					open_media_div('images');
					recalc('images'); //Takes care of adjusting the heights for the Actives and Archives list
					close_media_div('images');

					post_data = "action=get_skills_media_categories&skill_id=" + skill_id;
					$.post(media_controller,post_data,
						function(data){
							if(data.num <= 0){
								$(".list_box_left_1 ul").sortable("disable");
							}
						},"json"	
					);
			}
		},"json"
	);
}

function update_dashboard(){
	account_id = $('#logged_in_userid').val();
	$.post(media_controller,{"action":"get_disk_usage","account_id":account_id},
		function(data){
			if(data.status == "success"){
				$('#used_space').html(data.formated_content);
			}
		}, "json"
	);
}

//Used to assist in formatting Rate value
Number.prototype.formatMoney = function(c, d, t){
var n = this, c = isNaN(c = Math.abs(c)) ? 2 : c, d = d == undefined ? "," : d, t = t == undefined ? "." : t, s = n < 0 ? "-" : "", i = parseInt(n = Math.abs(+n || 0).toFixed(c)) + "", j = (j = i.length) > 3 ? j % 3 : 0;
   return s + (j ? i.substr(0, j) + t : "") + i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + t) + (c ? d + Math.abs(n - i).toFixed(c).slice(2) : "");
 };






