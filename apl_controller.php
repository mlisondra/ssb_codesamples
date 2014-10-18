<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once(APPPATH.'controllers/request_controller.php');

class Ar extends Request_Controller {

	public function __construct() {
		parent::__construct();
		$this->load->model('request');
	}
	
	public function index() {
		$this->id();
	}
	
	public function id() {
		if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
			$id = $this->uri->rsegment(3);
			if ($id) {
				$this->data['request'] = $request = array_pop($this->request->find($id)); // Get request data associated with given id
			}
			else {
				$this->load->model('authorized_requester'); 
				if (count($this->authorized_requester->find($this->data['user']->id)) == 0) { // Checks to see if logged in user has the permissions
					$this->authorized_requester->create(array('id'=>$this->data['user']->id, 'name'=>$this->data['user']->name, 'training_time'=>0));
				}
				$this->data['request'] = $request = $this->request->create(array(
																			'initiator_id'=>$this->data['user']->id,
																			'ar_behalf_id'=>$this->data['user']->id,
																			'initiator_name'=>$this->data['user']->name,
																			'ar_behalf_name'=>$this->data['user']->name,
																			'status_id'=>Request::REQUEST_STATUS_ID_DRAFT));
				$request->save();
				$this->data['new_id'] = $request->id;
		
	}
			
			if (!($request->isVisibleToUser($this->data['user'])) ) {
				$this->show_error_message("Request AR-$id was not found.", '404 Not Found');
			}
			else {
				$this->load->model('form');
				$this->data['base_form'] = $this->form->find_by_name('edit_ar_request');
				$base_fields =& $this->data['base_form']->fields();
				if ($request->ar_behalf_id() == $this->data['user']->id) {
					$base_fields['request_for']->value = 0;
					$base_fields['ar_behalf_id']->search_init = '';
					$base_fields['ar_behalf_id']->value = MY_Model::MAX_MYSQL_INT;
				}
				else if ($request->ar_behalf_id() == 0) {
					$base_fields['request_for']->value = 1;
					$base_fields['ar_behalf_id']->search_init = '';
				}
				else {
					$base_fields['request_for']->value = 1;
					$base_fields['ar_behalf_id']->search_init = $request->ar_behalf_name; // probably should store name in database as well
				}
	
				$route = $request->route();
				
				// can edit if there is no route (it's a draft) or if at first route node and this user is the actor in that node (request was returned)
				$can_edit = false;
                               
				if ( $route->nodeCount() == 0) {
					$can_edit = true;
				}
				else if ($route->activeNode() && $route->activeNode()->id == $route->firstNode()->id) {
					$active_node = $route->activeNode();
                                        if ($active_node->actor_id() == $this->user->currentUserId())
						$can_edit = true;
				}

				if ($can_edit){
                                   $this->edit(); 
                                }else{
                                    $this->show($request);
                                }
			}
		}
		else {
			$this->load_page_frame(array());
		}
	}
	
	private function edit() {
		$this->data['pageTitle'] = 'Edit AR Request';
		$button_block = $this->buttonsForRequest($this->data['request'], false);
		if (is_object($button_block)) {
			$button_block->appendInput(array('type'=>'button', 'value'=>'Send', 'id'=>'submit-button'));
			$button_block->setAttribute('id', 'button-block');
			$this->data['button_block'] = $button_block;
		}
		
		$this->data['cc_search_form'] = $this->form->find_by_name('cost_center_search');
                $this->data['status_fragment'] = $this->load->view('status-fragment', $this->data);
				
		$this->load->view('ar/edit-view', $this->data);
		$this->add_tracker();
		$return_html = $this->doc->saveHTML($this->data['content_area']['page_swapper']);
		
		$out_data = array('pageTitle'=>$this->data['pageTitle'], 'html'=>$return_html);
		if (isset($this->data['new_id']))
			$out_data['url_addon'] = $this->data['new_id'];

		$this->output->set_output( json_encode($out_data) );
	}
	
        /**
         * show
         * @param object $request
         * Creates page to allow for editing of a Requeset (if logged in user has the permission)
         * Views: 
         * status-fragment
         * requested-actions-fragment
         * ar/existing-access-fragment
         * ar/show-view (this view actually contans the previously listed views)
         * 
         * Addition info: the piwik tracker is added at this point
         * 
         */
	private function show($request) { 

		$groups = $_SESSION['session_groups'];
		$groups[] = $this->user->currentUserId();
                
		$this->data['node'] = $node = $this->data['request']->route()->activeNode();

		if (!empty($node)){
			$this->data['can_edit'] = ($this->user->currentUserIsAdmin() || in_array($node->actor_id(), $groups));
                        $approval_granularity = $node->approvalGranularity();
		} else { // Edit is not allowed
			$this->data['can_edit'] = false;
			$approval_granularity = Route_Node::NODE_APPROVAL_GRANULARITY_NONE;
		}

                // Check to see that user currently viewing is allowed to make edits to the Request
                // and that the current status of the Request is not 8 (Error)
		if ($this->data['can_edit'] && $this->data['request']->status_id() != Request::REQUEST_STATUS_ID_ERROR) {
			if ($approval_granularity == Route_Node::NODE_APPROVAL_GRANULARITY_ALL){ // Allows only managers to approve/reject
                            if($node->subtype == Route_Node::NODE_SUBTYPE_MANAGER){
                                if($this->user->currentUserIsAdmin() === false){
                                    $this->data['approve_reject_form'] = $this->form->find_by_name('approve_reject');
                                }                                
                            }else{
                                $this->data['approve_reject_form'] = $this->form->find_by_name('approve_reject');                          
                            }

                        }else if ($approval_granularity == Route_Node::NODE_APPROVAL_GRANULARITY_LINE){
				$this->data['approve_by_line'] = true;
                        }else if ($node->subtype == Route_Node::NODE_SUBTYPE_GFSC){
				$this->data['approve_reject_form'] = $this->form->find_by_name('mark_complete');
                        }else if ($node->subtype == Route_Node::NODE_SUBTYPE_SAP){
				$this->data['approve_reject_form'] = $this->form->find_by_name('mark_complete_or_reject');
                        }
		}
		// tweak the base value form so the names show correctly
		$base_fields = $this->data['base_form']->fields();
		$base_fields['ar_behalf_id']->search_init = $request->ar_behalf_name . " " . $this->user->infoForId($request->ar_behalf_id())->email;
		$base_fields['ar_behalf_id']->label = 'Request is for';
                
		unset($base_fields['request_for']);
	
	$this->data['base_form']->setFields($base_fields);

		$this->data['show_approvals'] = (empty($node) ||
                    $node->type == Route_Node::NODE_TYPE_TASK || 
                    ($node->subtype == Route_Node::NODE_SUBTYPE_DIVISION_CONTROLLER && !$this->data['can_edit']));
                
		$status_pane = $this->load->view('status-fragment', $this->data);
		$this->data['status_fragment'] = $status_pane->ownerDocument->getElementById('info-table');
		$this->data['requested_action_fragment'] = $this->load->view('ar/requested-actions-fragment', $this->data);
		
		$this->load->model('cost_center');
		$this->data['existing'] = $this->cost_center->costCentersForARId($this->data['request']->ar_behalf_id());
		$this->data['existing_access_fragment'] =  $this->load->view('ar/existing-access-fragment', $this->data);

		$button_block = $this->buttonsForRequest($this->data['request'], false);
	
	$button_block->setAttribute('id', 'button-block');
		if (is_object($button_block))
			$this->data['button_block'] = $button_block;
		if ($this->data['request']->status_id() == Request::REQUEST_STATUS_ID_CANCELED)
			$this->data['status_stamp'] = 'Canceled';
		
		$this->data['pageTitle'] = 'View AR Access Request'; // Browser tab title
		$this->load->view('ar/show-view', $this->data);
		$this->add_tracker(); // load piwik js tracker
		$return_html = $this->doc->saveHTML($this->data['content_area']['page_swapper']);
		
		$this->output->set_output( json_encode(array('pageTitle'=>$this->data['pageTitle'], 'html'=>$return_html)) );
	}
	
	public function fetchHomeScreenDetails() {
		if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
			$this->load->model('form');			
			$request_id = intval($this->input->post('request_id'));
			$this->data['request'] = $request = array_pop($this->request->find($request_id));
			
			if (!($request->isVisibleToUser($this->data['user'])) ) {
				$this->show_error_message("Request AR-$id was not found.", '404 Not Found');
			}

			$this->load->model('front_page_output');
			$this->data['output_data'] = array_pop($this->front_page_output->find($request->id));

			$this->data['output_data']->dc_names = array();
			if (!empty($this->data['output_data']->controller_ids)) {
				foreach (explode(',', $this->data['output_data']->controller_ids) as $cid) {
					if (is_object($this->user->infoForId($cid)))
						$this->data['output_data']->dc_names[] = $this->user->infoForId($cid)->name;
					else
						$this->data['output_data']->dc_names[] = $cid;
				}
			}

			$button_block = $this->buttonsForRequest($request, true);
			if (is_object($button_block))
				$this->data['button_block'] = $button_block;
			
			$fragment = $this->doc->documentElement->appendChild($this->load->view('ar/home-detail-panel', $this->data));
			$this->output->set_output(json_encode(array('html' => $this->doc->saveHTML($fragment))));
		}
	}
	
	
	
	public function record_action_approval() {
		if ($this->is_valid_ajax_request()) {
			$request = array_pop($this->request->find(intval($this->input->post('request_id'))));
			if ($request->route()->activeNodeId() == intval($this->input->post('node_id'))) {
				$this->load->model('action');
				$this->load->model('micro_history_event');
				$action_ids = json_decode($this->input->post('action_ids'));
				//$actions = $this->action->actionsWithIds($action_ids); // performance boost but need to make sure array values are safe
				foreach($action_ids as $action_id) {
					if (intval($action_id) > 0) {
						$action = array_pop($this->action->find($action_id));
						if ($action->assignment->cost_center->controller_id == $this->data['user']->id) {
							$action->dc_approval = $this->input->post('action_value');
							$action->approver_id = $this->data['user']->id;
							$action->approval_timestamp = time();
							$action->save();
						
	$this->micro_history_event->create(array(
												'request_id'	=>	$action->request_id,
												'actor_id'		=>	$action->approver_id,
												'actor_name'	=>	$this->data['user']->name,
												'event_type'	=>	$action->dc_approval,
												'identifier'	=>	'action_id',
												'value'		=>	$action->id
                                                                                                ));                                                   
						}
						else {
							$this->unauthorized();
						}
					}
				}
			}
			else {
				$this->unauthorized();
			}
		}
	}
	
	/**
         * send
         * Method manages the request to create a New Request
         * Request id is taken from the POST array
         * If the request contains Cost Centers that are binded to the The Behalf (person the request is for)
         * they are deleted from the system
         */
	public function send() {
		$request = array_pop($this->request->find(intval($this->input->post('request_id'))));
                
		if ($request->is_editable()) {
                    
                    $this->load->model('route_node');
                    $request->first_submission_time = time();
                    $request->route()->build_route();
                    $request->route()->step_route();
                    $request->setStatusId(Request::REQUEST_STATUS_ID_PENDING);
                    $request->determineType();
                    $request->save();
                    
			// remove all actions that are superceded by this request (actions for the same user/cost center that are still in draft)
			$this->load->model('action');
                        $before_loop = microtime(true);
			foreach ($request->actions() as $action) {
                            
                            $begin_time = microtime(true);
                            $this->action->deleteDraftActionsMatching($action);
                            $end_time = microtime(true);
                            $duration_time = $end_time - $begin_time;
			}
                        
			$this->load->library('email');
			$this->email->scheduleEmails($request);
			$this->output->set_output(true);
		} else {
			$this->output->set_output(false);
		}
	}
	
	
	public function fetch_existing() {
		if ($this->is_valid_ajax_request()) {
			if ($request_id = intval($this->input->post('request_id', TRUE))) {
				$request = array_pop($this->request->find($request_id));
				if (!($request->isVisibleToUser($this->data['user'])) ) {
					$this->show_error_message("Request AR-$id was not found.", '404 Not Found');
				}
				$this->load->model('assignment');
				$this->load->model('cost_center');
				$existing = $this->cost_center->costCentersForARId($request->ar_behalf_id());
				//$this->logger->write($this->db->last_query());
				//log_message('debug', '^^^^^^^^		'.print_r($existing, true));
				foreach($existing as $row) {
					$user_info = $this->user->infoForId($row->controller_id);
					if (is_object($user_info))
						$row->controller = $user_info->name;
				
	else
						$row->controller = "lookup failed";
				}
				echo json_encode((object)array('rows'=>$existing));
			}
		}
	}
        
       public function fetch_actions() {
            if ($this->is_valid_ajax_request()) {
                    $this->load->model('assignment');
                    $this->load->model('cost_center');
                    $this->load->model('action');
                    $this->load->library('ldap'); // Added 07-08-2013 Milder Lisondra

                    $request_id = intval($this->input->post('request_id', true));
                    $request = array_pop($this->request->find($request_id));
                    if (!($request->isVisibleToUser($this->data['user'])) ) {
                            $this->show_error_message("Request AR-$id was not found.", '404 Not Found');
                    }

                    //$results = $this->assignment->pendingActionsForRequest($request);
                    $results = $this->get_pending_actions($request);
                    foreach($results as $item){
                        $controller_info = $this->ldap->searchByDsid($item->controller_id);
                        $item->controller_name = $controller_info[0]->name;
                    }
                    
                    //echo json_encode((object)array('rows'=>$this->assignment->pendingActionsForRequest($request))); //Original code
                    echo json_encode((object)array('rows'=>$results,"total_pending_actions"=>count($results)));
            }
	}
	
	public function clear_actions() {
		if ($this->is_valid_ajax_request()) {
			$request = array_pop($this->request->find(intval($this->input->post('request_id', true))));
			if ($request->is_editable()) {
				$this->load->model('action');
				$this->load->model('assignment');
				$this->load->model('cost_center');
				$actions = $this->assignment->pendingActionsForRequest($request);
				foreach($actions as $action) {
					$assignment = array_pop($this->assignment->find($action->assignment_id));
					echo json_encode($assignment);
					if ($action->action === 'grant') {
						// delete no-longer-pending assignment
						$assignment->delete();
					}
					$action->delete();
				}
			}
		}
	}

	
        /*
         * @return json
         */
	public function modify_request_action() {
            
            $total_pending_actions = 0;
            
		if ($this->is_valid_ajax_request()) {
			$this->load->model('micro_history_event');
			$request = array_pop($this->request->find(intval($this->input->post('request_id', true))));
			if ($request->is_editable()) {
                            
				$this->load->model('assignment');
				$this->load->model('action');
                                
				$operation = $this->input->post('operation');
				$action = $this->input->post('action');
				if ($operation === 'create') {
					if ($action === 'revoke') {
                                                // Applies to multiple assignments to be removed
                                                // class method takes care of response to controller
						if (isset($_POST['assignment_ids'])) {
							$this->revoke_multiple($request);
							return;
						}
							
						$assignment = array_pop($this->assignment->find(intval($this->input->post('assignment_id'))));
					}
					else {
						// create assignment and mark to be granted
						$ar_id = intval($this->input->post('ar_behalf_id'));
						$cc_id = intval($this->input->post('cost_center_id'));
						$this->load->model('authorized_requester');
                                                // Check to see if Behalf is in system. If not, create new record
						if (count($this->authorized_requester->find($ar_id)) == 0) {
							$this->authorized_requester->create(array('id'=>$ar_id, 'name'=>$this->user->infoForId($ar_id)->name, 'training_time'=>0));
						}
						
						// assignment may already exist as part of canceled or draft request
						if (!$assignment = array_pop($this->assignment->find(array('authorized_requester_id'=>$ar_id, 'cost_center_id'=>$cc_id))))
							$assignment = $this->assignment->create(array('authorized_requester_id'=>$ar_id, 'cost_center_id'=>$cc_id, 'status'=>'inactive'));
					}
					$act = $this->action->create(array('action'=>$action, 'request_id'=>$request->id, 'assignment_id'=>$assignment->id));
					$this->load->model('cost_center');
					
					$return_data = new stdClass();
					$return_data->operation = $operation;
					$return_data->action_info = array_values($this->cost_center->find($assignment->cost_center_id));
                                        
                                        $user_info = $this->user->infoForId($return_data->action_info[0]->controller_id); // Look up Division Controller person info
                                        $return_data->action_info[0]->controller_name = $user_info->name; // Add Division Controller name to response
                                        
					$info = $return_data->action_info[0];
					$info->action = $action;
					$info->assignment_id = $assignment->id;
					$info->request_id = $request->id;
					$info->action_id = $act->id;
					$info->cost_center_id = $info->id;
					$info->ar_behalf_id = $request->ar_behalf_id();
					$this->micro_history_event->create(array(
										'request_id'	=>	$request->id, 
										'actor_id'		=>	$this->data['user']->id, 
										'actor_name'	=>	$this->data['user']->name, 
										'event_type'	=>	$operation, 
										'identifier'	=>	'action', 
										'value'			=>	$act->id, 
										'value_text'	=>	$act->toString()));
					
                                        $return_data->total_pending_actions = $this->get_pending_actions($request, true);
                                        echo json_encode($return_data);
                                        
				}else {
					$assignment = array_pop($this->assignment->find(intval($this->input->post('assignment_id'))));
					$action = $request->actionForAssignment($assignment);
					$this->micro_history_event->create(array(
										'request_id'	=>	$request->id, 
										'actor_id'		=>	$this->data['user']->id, 
										'actor_name'	=>	$this->data['user']->name, 
										'event_type'	=>	$operation, 
										'identifier'	=>	'action', 
										'value'			=>	$action->id, 
										'value_text'	=>	$action->toString()));
					
                                        if ($action->action === 'grant') {
                                            // Logic below is to avoid removing assignments from previous requests
                                            // which the current request has been cloned from
                                            // applies only when the Behalf is the same
                                            // This is a very unlikely scenario but must be addressed
						$prev_request_assignment = $this->action->getByAssignmentRequest($assignment->id,$request->id,$action->action);
                                                if(is_object($prev_request_assignment)){
                                                    $previous_request_id = $prev_request_assignment->request_id;
                                                    $previous_request_object = array_pop($this->request->find(array("id"=>$previous_request_id)));
                                                    $previous_request_behalf_id = $previous_request_object->ar_behalf_id;
                                                    if($previous_request_behalf_id != $request->ar_behalf_id){
                                                        $assignment->delete(); // delete no-longer-pending assignment
                                                    }
                                                }
					}
					$action->delete();
                                        
                                        $assignment->total_pending_actions = $this->get_pending_actions($request, true);
                                        $assignment->operation = $operation;
					echo json_encode($assignment);
				}
			}
		}
	}

        /**
         * 
         * @param object $request
         * @return JSON
         */
	private function revoke_multiple($request) {
            $total_pending_actions = 0;
            
		$this->load->model('cost_center');
                $this->load->model('user');
		$current_ccs = $this->cost_center->costCentersForARId($request->ar_behalf_id());
		$revoke_action_ids = array_map('intval', $this->input->post('assignment_ids'));
		$ar_id = intval($this->input->post('ar_behalf_id'));
		$cc_id = intval($this->input->post('cost_center_id'));
		$return_data = new stdClass();
		$return_data->operation = 'create';

		foreach($current_ccs as $current_cc) {

                    if (in_array($current_cc->assignment_id, $revoke_action_ids)) {
                            // do not revoke assignments that already have a pending action, unless the pending action is in a different request that is still draft
                            if (strpos($current_cc->assignment_status, 'transition') === false) {

                                    // it's still possible to have a duplicate assignment (costCentersForARId doesn't handle to-many assignments to actions, so check
                                    if (count($this->action->find(array('request_id'=>$request->id, 'assignment_id'=>$current_cc->assignment_id))) == 0) {
                                            $act = $this->action->create(array('action'=>'revoke', 'request_id'=>$request->id, 'assignment_id'=>$current_cc->assignment_id));
                                            $this->micro_history_event->create(array(
                                                                            'request_id'	=>	$request->id, 
                                                                            'actor_id'		=>	$this->data['user']->id, 
                                                                            'actor_name'	=>	$this->data['user']->name, 
                                                                            'event_type'	=>	'create', 
                                                                            'identifier'	=>	'action', 
                                                                            'value'			=>	$act->id, 
                                                                            'value_text'	=>	$act->toString()));
                                            $current_cc->action_id = $act->id;
                                            $current_cc->action = 'revoke';
                                            $current_cc->request_id = $request->id;
                                            $current_cc->ar_behalf_id = $ar_id; // Added 6/11/2013 Milder Lisondra
                                            $user_info = $this->user->infoForId($current_cc->controller_id); // Added 6/25/2013 Milder Lisondra
                                            $current_cc->controller_name = $user_info->name; //Added 6/25/2013 Milder Lisondra
                                            $return_data->action_info[] = $current_cc;
                                    }
                            }
                    }
		}
		
                $return_data->total_pending_actions = $this->get_pending_actions($request, true);
		echo json_encode($return_data);
	}
	
	public function search_cost_centers() { 
		if ($this->is_valid_ajax_request()) {
			$division = strval($this->input->post('division'));
			$department = strval($this->input->post('department'));
			$search_params = array('active'=>1);
			if (strlen($division) > 0)
				$search_params['division'] = $division;
			if (strlen($department) > 0)
				$search_params['department'] = $department;
				
		
	$this->load->model('cost_center');
			$request = array_pop($this->request->find(intval($this->input->post('request_id'))));
			if (is_object($request))
				$matches = $this->cost_center->findExcludingAssignedToUser($search_params, $request->ar_behalf_id());
			else
				$matches = array();
			echo json_encode($matches);
		}
	}
	
	/**
	* Retrieve Existing Authorized Requestor(s) for given Cost Center ID(s)
	* 
	* @access public
	* @param array Post data from POST
	* @return JSON
	*/
	public function ars_for_cost_center() {
		if(is_array($_POST['cost_center_ids'])){
			$cc_ids = $_POST['cost_center_ids']; 
			$this->load->model('cost_center');
			$names = $this->cost_center->existingARs_group($cc_ids);
		}

		echo json_encode($names);	
	
	}

	private function unauthorized() {
		header('HTTP/1.1 403 Error');
		print json_encode(array('error_code'=>403, 'reason'=>'unauthorized attempt to modify resource'));
		die;
	}
        
        /*
         * Get pending actions for given Request object
         * @param object $request
         * @return integer $count_only
         */
        public function get_pending_actions($request,$count_only = false){
            
            $results = $this->assignment->pendingActionsForRequest($request);
            if($count_only){
                return count($results);
            }else{
                return $results;
            }
        }
        
        /**
         * remove_assignments
         * Removes pending assignments from given array of assignment ids
         * Input taken from $_POST
         * @return json - Contains number of pending actions for given request
         */
        public function remove_assignments(){
            
            $this->load->model('assignment');
            $this->load->model('action');
            $this->load->model('micro_history_event');
           
            $request = array_pop($this->request->find(intval($this->input->post('request_id', true))));
            $operation = "remove";
            
            $assignments = json_decode($this->input->post('assignments'));
            
            foreach($assignments as $assignment){
                
                $assignment_info = array_pop($this->assignment->find($assignment->assignment_id));
                $action = $request->actionForAssignment($assignment_info);
                $this->micro_history_event->create(array(
                                                        'request_id'	=> $request->id, 
                                                        'actor_id'	=> $this->data['user']->id, 
                                                        'actor_name'	=> $this->data['user']->name, 
                                                        'event_type'	=> $operation, 
                                                        'identifier'	=> 'action', 
                                                        'value'		=> $action->id, 
                                                        'value_text'	=> $action->toString()));

                if ($action->action === 'grant') {
                        // delete no-longer-pending assignment
                    $assignment_info->delete();
                }
                $action->delete();    
            }
            
            $total_pending_actions = $this->get_pending_actions($request, true);
            print json_encode(array("total_pending_actions"=>$total_pending_actions));
                   
        }
        
        public function get_account_manager_id(){
            if ($this->is_valid_ajax_request()) {
                
                $request_id = $this->input->post('request_id');
                $result['acct_mgr_req_id'] = $this->request->get_acct_mgr_id($request_id);
                print json_encode($result);
            }            
        }
        
        public function save_account_manager_id(){
            if ($this->is_valid_ajax_request()) {
                
                $request_id = $this->input->post('request_id');
                $acct_mgr_req_id = $this->input->post('acct_mgr_id');
                $data = array("acct_mgr_req_id"=>$acct_mgr_req_id);
                if($this->request->set_acct_mgr_id($data,$request_id)){
                    $result = array("status"=>"success");
                }else{
                    $result = array("status"=>"fail");
                }
                print json_encode($result);
            }
        }
        
        /**
         * record_submit_button_state
         * Records the state of the submit button within the Division Controller Approval node
         * Data is recorded within table "micro_history_events"
         * 
         * @param array $action_ids (taken from $_POST)
         * @return void
         */
        public function record_submit_button_state(){
            log_message("INFO","Recording submit button state");
            $this->load->model('action');
            $this->load->model('micro_history_event');
            $action_ids = json_decode($this->input->post('action_ids'));
            foreach($action_ids as $action_id) {
                    if (intval($action_id) > 0) {
                            $action = array_pop($this->action->find($action_id));
                            if ($action->assignment->cost_center->controller_id == $this->data['user']->id) {
                                    $action->dc_approval = $this->input->post('action_value');
                                    $action->approver_id = $this->data['user']->id;
                                    $action->approval_timestamp = time();
                                    $action->save();
                                    $this->micro_history_event->create(array(
                                            'request_id'	=>	$action->request_id,
                                            'actor_id'		=>	$action->approver_id,
                                            'actor_name'	=>	$this->data['user']->name,