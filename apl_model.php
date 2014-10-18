<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');

class Request extends MY_Model
{
	
		
	private static $report_columns = array (
											'id_str' 		=> array('title'=>'Request #', 'group' => 0),
											'initiator_name'=> array('title'=>'Initiator Name','group' => 0),
											'ar_behalf_name'=> array('title'=>'AR Name', 'group' => 0),
											'status' 		=> array('title'=>'Status', 'group' => 0),
											'business_justification'=>array('title'=>'Business Justification', 'group' => 0),
											'ticket_id'		=> array('title'=>'Espresso Id', 'group' => 0),
											'action'		=> array('title'=>'Action', 'group' => 1),
											'approval'		=> array('title'=>'Approval', 'group' => 1),
											'division_str'	=> array('title'=>'Division', 'group' => 1),
											'department_str'=> array('title'=>'Department', 'group' => 1),
											'sent_date_str' => array('title'=>'Date Submitted', 'group' => 0),
											'approval_date_str' => array('title'=>'Date Approved', 'group' => 0),
									
	);



	private $initiator;
	private $_route;
	private $history;
	private $attachments;
	private $actions;
	private $ar_behalf_user;
	private $reminderTimeIntervals = null;
	private $timeoutTimeIntervals = null;
	public $ar_behalf_id;	
	// had to make public for DB_Result::custom_result_object() (invoked from util.php), but I'm not stoked about it.
	public $status_id;			// had to make public for DB_Result::custom_result_object() (invoked from util.php), but I'm not stoked about it.
	
	public function __construct($args=null) {
		parent::__construct($args);
		$this->load->model('route_node');
		$this->init(!is_null($args));

	}
	
	protected function instance_created() {
		if (count($this->history()) < 1) {
			$args = array('request_id'=>$this->id, 'actor_id'=>$this->initiator_id);
			
			// primary owner id is only 0 for imports.
			if ($args['actor_id'] > 0) {
				$args['actor_name'] = $this->user->infoForId($args['actor_id'])->name;
				$args['action'] = 'First created';
			}
			else {
				$args['actor_name'] = 'System';
				$args['action'] = 'Imported';
			}
			$this->history_event->create($args);
		}
	}
	
	
	// create another request on behalf of NULL_USER

	public function clone_instance() {
		$initiator = $this->user->currentUserInfo();
		$data = array(
			'initiator_id'=>$initiator->id,
			'initiator_name'=>$initiator->name,
			'ar_behalf_id'=>MY_Model::MAX_MYSQL_INT,
			'ar_behalf_name'=>'',
			'status_id'=>self::REQUEST_STATUS_ID_DRAFT,
			'business_justification'=>$this->business_justification,
		);
		
		$this->db->insert($this->_table, $data);
		$new_id = $this->db->insert_id();
		
		$this->load->model('assignment');
		foreach($this->actions() as $action) {
			if (!$null_assignment = array_pop($this->assignment->find(array('authorized_requester_id'=>MY_Model::MAX_MYSQL_INT, 'cost_center_id'=>$action->assignment->cost_center_id))))
				$null_assignment = $this->assignment->create(array('authorized_requester_id'=>MY_Model::MAX_MYSQL_INT, 'cost_center_id'=>$action->assignment->cost_center_id, 'status'=>'inactive'));
			$this->action->create(array('action'=>$action->action, 'assignment_id'=>$null_assignment->id, 'request_id'=>$new_id));
		}

		$this->load->model('history_event');
		$event = $this->history_event->create(array(
							'request_id'=>$new_id, 
							'actor_id'=>$this->user->currentUserId(), 
							'actor_name' => $this->user->currentUserInfo()->name,
							'action'=>'Cloned from AR-'.$this->id)
						);

		return $new_id;
	}
	
	// a specialized case where we create a new request to revoke the approved grant actions of the original
	public function cloneRevokeApproved() {
		$initiator = $this->user->currentUserInfo();
		$data = array(
			'initiator_id'=>$initiator->id,
			'initiator_name'=>$initiator->name,
			'ar_behalf_id'=>$this->ar_behalf_id(),
			'ar_behalf_name'=>$this->ar_behalf_name,
			'status_id'=>self::REQUEST_STATUS_ID_DRAFT,
			'business_justification'=>'Previous request was rejected by the SAP team after being implemented in eApproval. This request is to have access removed from eApproval for consistency.',
		);
		
		$this->db->insert($this->_table, $data);
		$new_id = $this->db->insert_id();
		
		foreach($this->actions() as $action) {
			if ($action->dc_approval == 'approve' && $action->action = 'grant')
				$this->action->create(array('action'=>'revoke', 'assignment_id'=>$action->assignment_id, 'request_id'=>$new_id));
		}
	
		$this->load->model('history_event');
		$event = $this->history_event->create(array(
							'request_id'=>$new_id, 
							'actor_id'=>$this->user->currentUserId(), 
							'actor_name' => $this->user->currentUserInfo()->name,
							'action'=>'Cloned from AR-'.$this->id)
						);

		return $new_id;
	}
	
	public function save() {
		$this->logger->write("ar_behalf_id = {$this->ar_behalf_id}", '&&&&&&&&');
		if (isset($this->ar_behalf_id)) {
			if ($this->ar_behalf_id == MY_Model::MAX_MYSQL_INT)
				$this->ar_behalf_name = '';
			else
				$this->ar_behalf_name = $this->user->infoForId($this->ar_behalf_id)->name;
		}
                $this->logger->write("ar behalf name = {$this->ar_behalf_name}", '&&&&&&&&');
		parent::save();
	}
	
	/****************  query methods on singleton $CI->request **********************/

	public function count_matching_of_type($args, $type = null) {
		if (!is_null($type))
			$args['request_type'] = $type;
		
		$this->db->select('COUNT(DISTINCT `id`) AS count');
		$this->prepare_find_query($args);
		$this->db->from('dashboard_view');
		$query = $this->db->get();
		
	
	if (ENVIRONMENT == ENV_DEV && !is_object($query)) {
			echo $this->db->last_query();
			debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		}
		if ($query->num_rows() > 0)
			return $query->row()->count;
		else
			return 0;
	}
	

	public function count_active_of_type($type = null) {
		$groups = $this->appledirectory_group->groupIdsForCurrentUser();
		$groups[] = $this->user->currentUserId();

		$this->db->select('COUNT(`id`) AS `count`', false);
		if (!is_null($type))
			$this->db->where('request_type', $type);
		
		if ($this->user->currentUserIsAdmin()) {
		
	$this->db->where('(`status_id` = '.Request::REQUEST_STATUS_ID_ERROR.' OR `current_actor_id` IN ('.implode(',', $groups).') )', null, false);
		}
		else {
			$this->db->where_in('current_actor_id', $groups);
		}
		
		$query = $this->db->get('dashboard_view');
		if (ENVIRONMENT==ENV_DEV && !is_object($query)) {
			print $this->db->last_query();
			debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
			die;
		}
		if ($query->num_rows() == 0)
			return 0;
		else
			return $query->row()->count;
	}
	
	public function list_active() {
		return $this->list_active_of_type();
	}
	
	public function list_active_of_type($type = null) {
		$groups = $this->appledirectory_group->groupIdsForCurrentUser();
		$groups[] = $this->user->currentUserId();

		$requests = array();
		$this->db->select('`dashboard_view`.*', false);
		if (!is_null($type))
		
	$this->db->where('request_type', $type);

		if ($this->user->currentUserIsAdmin()) {
			$this->db->where('(`status_id` = '.Request::REQUEST_STATUS_ID_ERROR.' OR `current_actor_id` IN ('.implode(',', $groups).') )', null, false);
		}
		else {
			$this->db->where_in('current_actor_id', $groups);
		}
		
		$query = $this->db->get('dashboard_view');
		if (ENVIRONMENT == ENV_DEV && !is_object($query)) {
			echo $this->db->last_query();
			debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		}
		return $query->result();
	}

	public function list_matching_of_type($criteria, $type = null) {
		foreach($criteria as $criterion => $value) {
			if (is_array($value))
				$this->db->where_in($criterion, $value);
			else
				$this->db->where($criterion, $value);
		}
		if ($type !== false){
			$this->db->where('request_type', $type);
                }
                
		$query = $this->db->get('dashboard_view');
		
		if (ENVIRONMENT == ENV_DEV && !is_object($query)) {
			echo $this->db->last_query();
			debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		}
		return $query->result();
	}
	
	
	// This checks the history and if a group this person is a member of has a history entry for this request, will select it
	// Then it weeds out requests that will be in the inbox.
	public function countTouchedByCurrentUser($type, $request_statuses = array()) {
		$this->load->model('route_node');
		$groups = $this->appledirectory_group->groupIdsForCurrentUser();
		$statuses = '('.implode(',', $request_statuses).')';
		
		$sql = "SELECT COUNT(id) AS count FROM `dashboard_view` WHERE `id` IN (SELECT DISTINCT(`request_id`) from `history_events` WHERE (`history_events`.`action` IN ".Route_Node::TOUCHED_ACTIONS." AND `history_events`.`actor_id` = ".$this->user->currentUserId().")";
		if (count($groups) > 0)
			$sql .= "OR `group_id` in (".implode(',', $groups)."))";
		else
			$sql .= ')';
			
		if (count($request_statuses) > 0)
			$sql .= " AND `dashboard_view`.`status_id` IN ('".implode("','", $request_statuses)."')";
			
		$sql .= ' AND `request_type` = "'.$type.'"';
			
		$groups[] = $this->user->currentUserId();
		$sql .= " AND id NOT IN (SELECT routes.request_id AS req_id FROM `routes` JOIN `route_nodes` ON `route_nodes`.`id`=`routes`.`active_node_id` WHERE `route_nodes`.`actor_id` IN (".implode(',', $groups)."))";
		
		$query = $this->db->query($sql);
		//$this->logger->write($this->db->last_query());
		if ($query->num_rows() > 0)
			return $query->row()->count;
		else
			return 0;
	}
	
	public function listTouchedByCurrentUser($type = null, $request_statuses = array()) {
		$groups = $this->appledirectory_group->groupIdsForCurrentUser();
		$sql = "
			SELECT * FROM `dashboard_view` WHERE `id` IN (SELECT DISTINCT(`request_id`) from `history_events`
			WHERE (`history_events`.`action` IN ".Route_Node::TOUCHED_ACTIONS." AND `history_events`.`actor_id` = ".$this->user->currentUserId().")";
		if (count($groups) > 0)
			$sql .= "OR `group_id` in (".implode(',', $groups)."))";
		else
			$sql .= ')';

		if (count($request_statuses) > 0)
			$sql .= " AND `dashboard_view`.`status_id` IN ('".implode("','", $request_statuses)."')";
		
		if (is_string($type))
			$sql .= ' AND `request_type` = "'.$type.'"';

		$groups[] = $this->user->currentUserId();
		$sql .= "
			AND `id` NOT IN (
			SELECT `requests`.`id` AS req_id
			FROM `requests`
			JOIN `routes` ON `routes`.`request_id` = `requests`.`id`
		
	JOIN `route_nodes` ON `route_nodes`.`id`=`routes`.`active_node_id`
			WHERE `route_nodes`.`actor_id` IN (".implode(',', $groups)."))";
		
		$query = $this->db->query($sql);
		return $query->result();
	}

	public function report_columns() {
		return self::$report_columns;
	}
		
	public function dashboard_columns() {
		return self::$dashboard_columns;
	}
		
	/************************* find and count overrides to perform joins *************************/

	public function count($args) {
		if (count(array_diff(array_keys($args), $this->column_names())) > 0)
			return $this->fullObjectCount($args);
	
	else
			return $this->count_by($args);
	}
	
	public function find($args) {
		if (!is_array($args))
			return $this->find_by(array('id'=>$args));
		else
			return $this->find_by($args);
	}
	
	public function requests_matching($match_text, $candidates) { 
		$matches = array();
		$candidate_ids = array();
		foreach($candidates as $candidate) {
			$candidate_ids[] = $candidate->id;
		}
		
		$this->db->from($this->_table);
		$this->db->where_in('id', $candidate_ids);
	
	$where_clause = '(0';
		foreach(self::$search_fields as $column_name) {
			if (in_array($column_name, $this->column_names())) {
				$this->db->select("`$column_name`", false);
                                if($column_name == "type"){
                                    $this->db->select("`$column_name` AS `request_type`", false);
                                }
				$where_clause .= " OR `$column_name` LIKE '%".$this->db->escape_like_str($match_text)."%'";
			}
		}
		$where_clause .= ')';
		$this->db->where($where_clause, null, false);
		$query = $this->db->get();
		log_message("INFO","Query inside Request::requests_matching " . $this->db->last_query());
		$matches = $query->result('Request');
			
		return $matches;
	}
	
	private function build_view_select_with_columns($columns) {
		$col_names = array_keys(self::$report_columns);

		$group_index = 0;
		foreach(array_intersect($columns, $col_names) as $column) {
			$this->db->select("`$column`", false);
			$group_index = max($group_index, self::$report_columns[$column]['group']);
		}
		$this->db->select("`request_id`", false);
		$this->db->group_by(self::$group_by_options[$group_index]);
	}

	public function find_with_filters($filters, $columns=NULL, $order=NULL) {
		$merged_view = 'ar_view';
		$this->db->from($merged_view);
		if (!is_null($order) && strlen($order) > 0)
		
	$this->db->order_by(str_replace('-', ' ', $order));
		
		if (is_array($columns)) {
			$this->build_view_select_with_columns($columns);
		}
		
		$this->logger->write($_POST);
		$this->logger->write($filters);
		
		foreach($filters as $filter) {
		
	$column_name = $filter['column_name'];
			switch ($filter['predicate']) {
				case 'is':
					$this->db->where('`'.$column_name.'`', $this->db->escape($filter['modifier']), false);
					break;
				case 'is not':
					$this->db->where('`'.$column_name.'`!=', $this->db->escape($filter['modifier']), false);
					break;
				case 'is one of':
					if (is_array($filter['modifier']))
						$list = array_keys($filter['modifier']);
					else
						$list = explode(',', $filter['modifier']);
					$this->db->where_in($column_name, $list);
					break;
				case 'is not one of':
					if (is_array($filter['modifier']))
						$list = array_keys($filter['modifier']);
					else
						$list = explode(',', $filter['modifier']);
					$this->db->where_not_in($column_name, $list);
					break;
				case 'yes':
					$this->db->where('`'.$column_name.'`', '"yes"', false);
					break;
				case 'no':
					$this->db->where('`'.$column_name.'`', '"no"', false);
					break;
				case 'is before':
					$this->db->where('`'.$column_name.'`<', $this->db->escape($filter['modifier']), false);
					break;
				case 'is after':
					$this->db->where('`'.$column_name.'`>', $this->db->escape($filter['modifier']), false);
					break;
				case 'is less than':
					$this->db->where('`'.$column_name.'`<', intval($filter['modifier']), false);
					break;
				case 'is greater than':
					$this->db->where('`'.$column_name.'`>', intval($filter['modifier']), false);
					break;
				case 'to':
					$this->db->where('`'.$column_name.'`<=', $this->db->escape($filter['modifier']), false);
					break;
				case 'from':
				
	$this->db->where('`'.$column_name.'`>=', $this->db->escape($filter['modifier']), false);
					break;
				case 'includes':
					$this->db->where('(FIND_IN_SET('.$this->db->escape($filter['modifier']).', `'.$column_name.'`) > 0)');
					break;
				case 'does not include':
					$this->db->where('(FIND_IN_SET('.$this->db->escape($filter['modifier']).', `'.$column_name.'`) = 0)');
					break;
				case 'starts with':
					$this->db->where('`'.$column_name.'` LIKE ', $this->db->escape($filter['modifier'].'%'), false);
					break;
				case 'contains':
					$this->db->where('`'.$column_name.'` LIKE ', $this->db->escape('%'.$filter['modifier'].'%'), false);
					break;
			}
		}
		
		$query = $this->db->get();
		if (ENVIRONMENT == ENV_DEV && !is_object($query)) {
			echo $this->db->last_query();
			debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
			die;
		}
		return $this->process_query_results($query->result());
	}

	
	protected function process_query_results($results) {
		$this->load->model('history_event');
		foreach($results as $row) {
			if (isset($row->sla) && $row->sla > 0) {
				$row->sla = History_Event::formatSLATime($row->sla);
			}
		}
		return ($results);
	}
	

	/****************  methods for single instances  **********************/
	
	
	public function isVisibleToUser($user) {
		if ($this->initiator_id == $this->user->currentUserId() || $user->isAdmin() || $user->isExec() )
			return true;
			
		$groups = $this->appledirectory_group->groupIdsForCurrentUser();
		$groups[] = $this->user->currentUserId();
		
		$this->db->select('COUNT(`route_nodes`.`id`) AS count', false);
		$this->db->from('route_nodes');
		$this->db->join('routes', 'routes.id = route_nodes.route_id AND routes.request_id = '.$this->id);
		$this->db->where_in('actor_id', $groups);
		$query = $this->db->get();
		return $query->row()->count > 0;
	}
	
	// controls whether user can delete a request - only initiator can delete and cancel right now
	public function isChangeableByUser($user) {
		return $this->initiator_id == $user->id;
	}
	
	public function route() {
		if (!isset($this->_route)) {
			$this->load->model('route');
			if(empty($this->_route)) {
				$route = $this->route->find(array('request_id'=>$this->id));
				if(!empty($route)) {
					$this->_route = array_pop($route);
				}
				else {
					$route_args = array('request_id'=>$this->id);
					$this->_route = $this->route->create($route_args);
				}
			}			
			$this->_route->setRequest($this);
		}
		return $this->_route;
	}
	
	public function actions() {
		if (!isset($this->actions)) {
			$this->actions = $this->fetch_many_for_one('action');
		}
		return $this->actions;
	}
	public function actionForAssignment($assignment) {
		$action = null;
		foreach($this->actions() as $a) {
			if ($a->assignment_id == $assignment->id)
				$action = $a;
		}
		if (is_null($action)) {
			print('no action found!');
			print_r($assignment);
			print($this->id);
		}
			
		return $action;
	}
	
	private function ar_behalf_user() {
		if (!isset($this->ar_behalf_user)) {
			$this->ar_behalf_user = $this->user->infoForId($this->ar_behalf_id());
		}
		return $this->ar_behalf_user;
	}
	
	public function manager_id() {
		$id = $this->ar_behalf_user()->manager_id;
		// fix for bogus ldap return value for manager
		if (ENVIRONMENT == ENV_TEST && $id == 55795)
			$id = 6938;
		return $id;
	}
	
	public function ar_behalf_name() {
		return $this->ar_behalf_user()->name;
	}
	
	public function ar_behalf_id() { return $this->ar_behalf_id; }
	public function set_ar_behalf_id($val) {
		$this->logger->write("new id is $val - old id was {$this->ar_behalf_id}");
		$transfer_failures = array();
		if ($val != $this->ar_behalf_id) {
			$this->logger->write('changing behalf id of request', '********');
			$this->ar_behalf_id = intval($val);
			$this->save();
			// create new assignments with the new user. If that assignment exists and is not inactive, throw it out and return the conflicting id
			$this->load->model('assignment');
			foreach($this->actions() as $action) {
				if (!$new_assignment = array_pop($this->assignment->find(array('authorized_requester_id'=>$this->ar_behalf_id, 'cost_center_id'=>$action->assignment->cost_center_id))))
					$new_assignment = $this->assignment->create(array('authorized_requester_id'=>$this->ar_behalf_id, 'cost_center_id'=>$action->assignment->cost_center_id, 'status'=>'inactive'));

				if ( ($action->action == 'grant' && $new_assignment->status != 'inactive') || ($action->action == 'revoke' && $new_assignment->status != 'active') ) {
				
	$transfer_failures[] = $new_assignment->cost_center;
					$this->logger->write('cannot transfer action!', '********');
					$this->logger->write($new_assignment);
				}
				else {
					$new_action = $this->action->create(array('action'=>$action->action, 'assignment_id'=>$new_assignment->id, 'request_id'=>$this->id));
					//$this->logger->write($new_action);
				}
				
				$action->delete();
			}
			unset($this->actions);
		}
		
		$num_failures = count($transfer_failures);
		if ($num_failures> 0) {
			$alert_text = 'The following cost center'.($num_failures > 1 ? 's were' : ' was')." not copied for the new authorized requester:\n";
			foreach ($transfer_failures as $failure) {
				$alert_text .= $failure->fullIdString().' - '.$failure->description."\n";
			}
			return array('alert'=>$alert_text);
		}
		else {
			return;
		}
		
	}
	

	public function status_id() { return $this->status_id; }
	public function setStatusId($stat_id) {
		$this->status_id = $stat_id;
		
		$this->logger->write("setting status to $stat_id");
		// This is where we have to update the status of all assignments
		foreach($this->actions() as $action) {
			$this->logger->write("updating action {$action->id}");
			$assignment = $action->assignment;
			$this->logger->write("updating assignment {$assignment->id}");
			switch($stat_id) {
				case self::REQUEST_STATUS_ID_PENDING:
					if ($action->action == 'grant')
						$assignment->status = 'inactive_transition';	// currently inactive but transitioning to active
					else
						$assignment->status = 'active_transition';		// currently active but in transition
					$assignment->save();
					break;
				case self::REQUEST_STATUS_ID_COMPLETE:
					if ($action->action == 'grant' && $action->dc_approval == 'approve')
						$assignment->status = 'active';
					else if ($action->action == 'revoke' && $action->dc_approval == 'approve')
						$assignment->status = 'inactive';
					else if ($action->dc_approval == 'reject') // status goes back to what it was transitioning from
						$assignment->status = str_replace('_transition', '', $assignment->status);
					$assignment->save();
					break;
				case self::REQUEST_STATUS_ID_REJECTED:
				case self::REQUEST_STATUS_ID_CANCELED:
					$assignment->status = str_replace('_transition', '', $assignment->status);
				
	$assignment->save();
					break;
			}
			$this->logger->write("status is now {$assignment->status}");
			
		}
		
		$this->save();
	}

	public function history() {
		if (!isset($this->history)) {
			$this->load->model('history_event');
			$this->db->order_by('time');
			$this->history = $this->fetch_many_for_one('History_Event');
		}
		return $this->history;
	}
	
	public function get_value($field_name) {
		//log_message('debug', '********		getting value for = '.print_r($field_name, true));
		if ($this->_reflector->hasMethod($field_name))
			return $this->$field_name();
		else if (isset($this->$field_name))
			return $this->$field_name;
	}
	
	public function set_value($field_name, $value) {
		if ($this->_reflector->hasMethod('set_'.$field_name)) {
			return $this->{'set_'.$field_name}($value);
		}
		else/* if (isset($this->$field_name))*/ {
			$this->$field_name = $value;
			return NULL;
		}
	}
	public function numNonRejectedActions() {
		// there may be a crap-ton of actions, so we'll write a lean and mean query
		$this->db->select('COUNT(`id`) AS count', false);
		$this->db->from('actions');
		$this->db->where('`request_id`', $this->id, false);
		$this->db->where('(`dc_approval` IS NULL OR `dc_approval` = "approve")', null, false);
		$query = $this->db->get();
		return $query->row()->count;
	}
	public function dcNodeComplete() {
		//double-check to make sure if this is a dc node that all actions have been deicded upon.
		$node = $this->route()->activeNode();
		if ($node->subtype == Route_Node::NODE_SUBTYPE_DIVISION_CONTROLLER) {
			foreach($this->actions() as $action) {
				if (is_null($action->approver_id)) {
					if ($action->assignment->cost_center->controller_id == $node->actor_id()) {
						$this->logger->write($action);
						return false;
					}
				}
			}
		}
		
		return true;
	}
	public function is_editable() {
		//log_message('DEBUG', __CLASS__."::".__FUNCTION__ . "() - " . $this->status );
		$status_id = $this->status_id();
		$editable_state = $status_id == self::REQUEST_STATUS_ID_DRAFT || $status_id == self::REQUEST_STATUS_ID_RETURNED;
		if ($editable_state)
			return $this->initiator_id == $this->user->currentUserId() || $this->user->currentUserIsAdmin();
		else
			return false;
	}
	
	public function id_str() {
		return 'AR-'.$this->id;
	}

	
	public function initiator() {
		if (!isset($this->initiator))  {
			if ($this->initiator_id == 0)
				$this->initiator = new UserData((object)array('name'=>'Import', 'id'=>0, 'mail'=>''));
			else
				$this->initiator = $this->user->infoForId($this->initiator_id);
		}
		
		return $this->initiator;
	}
	public function initiator_name() {
		return $this->initiator()->name;
	}
	public function combined_description() {
		$description  = 'Request: '.$this->id_str()."\n\n";
		$description .= 'Initiator: '.$this->initiator_name()."\n";
		$description .= 'Initiator email: '.$this->initiator()->email."\n\n";
		
		$behalf_user = $this->user->infoForId($this->ar_behalf_id);
		
		$description .= 'AR name: '.$behalf_user->name."\n";
		$description .= 'AR DSID: '.$this->ar_behalf_id."\n";
		
		$description .= "\n";
		$description .= "Actions:\n";
		$accepted_grants = '';
		$accepted_revokes = '';
		$denied_grants = '';
		$denied_revokes = '';
		
		foreach ($this->actions() as $action) {
			if ($action->dc_approval == 'approve') {
				if ($action->action == 'grant')
					$this->addActionRow($accepted_grants, $action);
				else
					$this->addActionRow($accepted_revokes, $action);
		
	}
			else {
				if ($action->action == 'grant')
					$this->addActionRow($denied_grants, $action);
				else
					$this->addActionRow($denied_revokes, $action);
			}
		}
		
		if (strlen($accepted_grants) > 0)
			$description .= "\n GRANT ACCESS: $accepted_grants";
		if (strlen($accepted_revokes) > 0)
			$description .= "\n REVOKE ACCESS: $accepted_revokes";
			
		if (strlen($denied_grants) > 0 || strlen($denied_revokes) > 0) {
			$description .= "\n\n\n Denied actions:";
			$description .= "\n NOTE: DO NOT EXECUTE THE FOLLOWING ACTIONS. The following actions were rejected by the Division Controller, and are included here for documentary purposes only.";
			$description .= $denied_grants.$denied_revokes;
		}
		
                $description .= "\n\n See attached Excel file for details.";
                
                return $description;
	}
        
        
    public function generate_excel_data($request_id) {
 
        $request = array_pop($this->request->find($request_id)); // Get request data associated with given id
        $data = array();
     
		$data['description']['request_display_id']  = $this->id_str();
		$data['description']['initiator'] = $this->initiator_name();
		$data['description']['initiator_email']  = $this->initiator()->email;
		
		$behalf_user = $this->user->infoForId($this->ar_behalf_id);

		$data['description']['ar_name'] = trim($behalf_user->name);
		$data['description']['ar_id']  = trim($this->ar_behalf_id);
                
                $description = "";
		$accepted_grants = '';
		$accepted_revokes = '';
		$denied_grants = '';
		$denied_revokes = '';
		
               $actions_list = NULL;
               
		foreach ($request->actions() as $action) {
                    $assignment = $action->assignment; // Assignment information
                    
			if ($action->dc_approval == 'approve') { 
				if ($action->action == 'grant')
					$this->addActionRow($accepted_grants, $action);
				else
					$this->addActionRow($accepted_revokes, $action);
                                
                                $cost_center_string = explode("/",$assignment->cost_center->fullIdString());
                                $division_id = (string) $cost_center_string[0];
                                $dept_id = $cost_center_string[1];
                                $actions_list[] = array("action"=>$action->action,"division_id"=>$division_id,"dept_id"=>$dept_id,"status"=>$action->dc_approval,"dc"=>$this->user->infoForId($action->approver_id)->name,"modified_datetime"=>date('m/d/Y H:i:s A', $action->approval_timestamp));
			}
			else {
				if ($action->action == 'grant')
                                    $this->addActionRow($denied_grants, $action);
                                        
				else
					$this->addActionRow($denied_revokes, $action);
                                
                                $cost_center_string = explode("/",$assignment->cost_center->fullIdString());
                                $division_id = $cost_center_string[0];
                                $dept_id = $cost_center_string[1];
                                $actions_list[] = array("action"=>$action->action,"division_id"=>$division_id,"dept_id"=>$dept_id,"status"=>$action->dc_approval,"dc"=>$this->user->infoForId($action->approver_id)->name,"modified_datetime"=>date('m/d/Y H:i:s A', $action->approval_timestamp));
			}
                        
		}
                
                $data['actions_list'] = $actions_list;
                return $data;

	}
        
        /*
         * Get associated counts per status for request
         * @return array $data
         */
        public function get_action_counts($request_id){
            $request = array_pop($this->request->find($request_id)); // Get request data associated with given id
            
            $data = array();
            $dcs_involved = array();
            $accepted_grants = 0;
            $accepted_revokes = 0;
            $denied_grants = 0;
            $denied_revokes = 0;
            
            foreach ($request->actions() as $action) {
                $assignment = $action->assignment; // Assignment information

                    if ($action->dc_approval == 'approve') { 
                            if ($action->action == 'grant')
                                $accepted_grants++;
                            else
                                $accepted_revokes++;
                            
                            $dcs_involved[] = $this->user->infoForId($action->approver_id)->name;
                    }else {
                            if ($action->action == 'grant')
                                $denied_grants++;
                            else
                               $denied_revokes++;
                            
                            $dcs_involved[] = $this->user->infoForId($action->approver_id)->name;
                    }
           } 
           $dcs_involved_clean = array_unique($dcs_involved);
           
           $data['accepted_grants'] = $accepted_grants;
           $data['accepted_revokes'] = $accepted_revokes;
           $data['denied_grants'] = $denied_grants;
           $data['denied_revokes'] = $denied_revokes;
           $data['dcs_involved'] = $dcs_involved_clean;
           return $data;
            
        }
               
	private function addActionRow(&$str, $action) {
		$assignment = $action->assignment;
		$str .= "\n	".$action->action.' access to cost center: '.$assignment->cost_center->fullIdString();
		$str .= ' - '.($action->dc_approval == 'approve' ? 'approved' : 'rejected').' by '.$this->user->infoForId($action->approver_id)->name.' on '.date('Y-m-d H:i:s', $action->approval_timestamp);
	}
	
	public function reminderTimeIntervals($node_type) {
		if (is_null($this->reminderTimeIntervals)) {
			$config_name = 'ar_settings';
			$this->config->load($config_name, TRUE);
			$this->reminderTimeIntervals = $this->config->item('reminderTimeIntervals', $config_name);
		}
		
		return $this->reminderTimeIntervals[$node_type];
	}	

	public function timeoutTimeInterval($node_type) {
		if (is_null($this->timeoutTimeIntervals)) {
			$config_name = 'ar_settings';
			$this->config->load($config_name, TRUE);
			$this->timeoutTimeIntervals = $this->config->item('timeoutTimeIntervals', $config_name);
		}
		
		return $this->timeoutTimeIntervals[$node_type];
	}
	

	public function headerTextForRouteNode($node) {
		return $this->route()->headerTextForRouteNode($node);
	}
	
		
	public function replacePlaceholders($text) {
		$num_matches = preg_match_all('|{%%([\w]*)%%}|', $text, $matches, PREG_OFFSET_CAPTURE);
		//$this->logger->write($matches);
	
	
		for ($i = count($matches[0])-1; $i >= 0; $i--) {
			$new_beginning = substr($text, 0, $matches[0][$i][1]);
			//$this->logger->write($i);
			//$this->logger->write($this->get_value($matches[1][$i][0]));
			$new_middle = $this->get_value($matches[1][$i][0]);
			$new_end = substr($text, $matches[0][$i][1] + strlen($matches[0][$i][0]));
			$text = $new_beginning.$new_middle.$new_end;
		}
		return $text;
	}	

	
	public function determineType() {
            
            if (!isset($this->type) || is_null($this->type)) {
                
                // Gets the number assignments associated with given authorized requester id
                // but not for the current request id
                $sql =  "SELECT assignments.id from assignments WHERE assignments.id NOT IN 
                (SELECT assignment_id from `actions` WHERE `request_id` = '".$this->id."') AND `authorized_requester_id` = '".$this->ar_behalf_id."'";

                $query = $this->db->query($sql);
                $num_assignments = $query->num_rows();
                 
                if($query->num_rows() == 0){
                     $this->type = "New";
                 }else{
                     
                // Get the number of completed revoke requests
                $sql_num_completed_revokes = "SELECT COUNT(DISTINCT requests.id) FROM `requests`
                   LEFT JOIN `actions` AS `other_revokes` ON (`other_revokes`.`request_id` <> `requests`.`id`) and (`other_revokes`.`action` = 'revoke' and `requests`.`status_id` >= 4)
                   LEFT JOIN `assignments` AS `completed_revoke_assignments` ON (`completed_revoke_assignments`.`id` = `other_revokes`.`assignment_id` and `requests`.`ar_behalf_id` = `completed_revoke_assignments`.`authorized_requester_id`)
                   WHERE requests.id = '".$this->id."'";

                $completed_revokes_query = $this->db->query($sql_num_completed_revokes);
                $num_completed_revokes = $completed_revokes_query->num_rows();
                
                // Get number of requests pertaining to grants
                $sql_num_grants = "SELECT COUNT(DISTINCT id) FROM `actions` WHERE `action` = 'grant' and request_id NOT IN ('".$this->id."')
                   AND assignment_id IN (SELECT id FROM assignments where authorized_requester_id = '".$this->ar_behalf_id."')";
                $grants_query = $this->db->query($sql_num_grants);
                $num_grants = $grants_query->num_rows();
                
                     // Get number of requsts pertaining to revokes 
                     $sql_num_revokes = "SELECT COUNT(DISTINCT id) FROM `actions` WHERE `action` = 'revoke' and request_id NOT IN ('".$this->id."')
                        AND assignment_id IN (SELECT id FROM assignments where authorized_requester_id = '".$this->ar_behalf_id."')";
                     //log_message("DEBUG","Num revokes SQL: ".$sql_num_revokes);
                     $revokes_query = $this->db->query($sql_num_revokes);
                     $num_revokes = $revokes_query->num_rows();
                    
                     // Get number of grants in current request
                     $sql_query = "SELECT `action` FROM `actions` WHERE action = 'grant' AND request_id = '".$this->id."'";
                     $query_result = $this->db->query($sql_query);
                     $num_grants_in_current_request = $query_result->num_rows();
                     
                     // Get the number of pending grants
                     $sql_num_pending_grants = "SELECT * from assignments
                        WHERE id in (SELECT assignment_id from actions WHERE action = 'grant' AND request_id NOT IN ('".$this->id."') and `dc_approval` IS NULL)
                        AND authorized_requester_id = '".$this->ar_behalf_id."'";
                     
                     $pending_grants_query = $this->db->query($sql_num_pending_grants);
                     $num_pending_grants = $pending_grants_query->num_rows();
                 
                     if($num_grants_in_current_request == 0 && $num_pending_grants == 0){
                     //if((($num_assignments - $num_completed_revokes) - $num_revokes) == 0){
                         $this->type = "Revoke";
                     }else{
                         $this->type = "Change";
                     }
                     
                }
            }   
               
	}
        
        public function get_counts(){

            $sql =  "SELECT assignments.id from assignments WHERE assignments.id NOT IN 
                (SELECT assignment_id from `actions` WHERE `request_id` = '".$this->id."')";  
            $query = $this->db->query($sql);
            if($query->row()->count == 0){
                 $this->type = "New";
             }else{
                 $this->type = "Change";
            }            
      
        }
        
        /*
         * Get account manager id for given Request ID
         */
        public function get_acct_mgr_id($request_id){
            $this->db->select('acct_mgr_req_id');
            $this->db->from('requests');
            $this->db->where('id',$request_id);
            $query = $this->db->get();
            if($query->num_rows == 1){
                $result = $query->row();
                return $result->acct_mgr_req_id;
            }
        }
        
        /*
         * Set the account manager request id for given Request ID
         */
        public function set_acct_mgr_id($data,$request_id){
            $this->db->where('id', $request_id);
            $this->db->update('requests', $data);
            
            if($this->db->affected_rows() == 1){
                return true;
            }else{
                return false;
            }
        }
        
        /**
         * search_filter
         * Allows searching through requests that currently logged in user is allowed access to
         * @author Milder Lisondra
         * @param array $args
         */
        public function search_filter($args){
            extract($args);
            
            $status_array = NULL;
            $admin = false; // Default admin to false to avoid accidental exposure of requests

            if($group_id == ARACCESS_GROUP_ADMIN){
                $admin = true;
            }
            if($admin === false){
                switch($boxName){
                    case "Sent":
                            $sql = "SELECT * FROM `dashboard_view` WHERE `id` IN (SELECT DISTINCT(`request_id`) from `history_events`
                                        WHERE (`history_events`.`action` IN ('Sent','Approved','Returned','Rejected','Reviewed') AND `history_events`.`actor_id` = ".$user_id.") OR `group_id` in (".$group_id.")) 
                                            AND `dashboard_view`.`status_id` IN ('2','4')
                                        AND `id` NOT IN (
                                        SELECT `requests`.`id` AS req_id
                                        FROM `requests`
                                        JOIN `routes` ON `routes`.`request_id` = `requests`.`id`
                                        JOIN `route_nodes` ON `route_nodes`.`id`=`routes`.`active_node_id`
                                        WHERE `route_nodes`.`actor_id` IN (".$user_id.",".$group_id."))";                            
                    $sql .= " AND (`initiator_name` LIKE '%".$search_term. "%' OR `ar_behalf_name` LIKE '%".$search_term. "%' OR `business_justification` LIKE '%".$search_term. "%' OR `id` LIKE '%".$search_term. "%')";
                            $query = $this->db->query($sql);
                            return $query->result();
                        break;
                    case "Complete":
                           $sql = "SELECT * FROM `dashboard_view` WHERE `id` IN (SELECT DISTINCT(`request_id`) from `history_events`
                                        WHERE (`history_events`.`action` IN ('Sent','Approved','Returned','Rejected','Reviewed') AND `history_events`.`actor_id` = ".$user_id.") OR `group_id` in (".$group_id.")) 
                                            AND `dashboard_view`.`status_id` IN ('5','6','7')
                                        AND `id` NOT IN (
                                        SELECT `requests`.`id` AS req_id
                                        FROM `requests`
                                        JOIN `routes` ON `routes`.`request_id` = `requests`.`id`
                                        JOIN `route_nodes` ON `route_nodes`.`id`=`routes`.`active_node_id`
                                        WHERE `route_nodes`.`actor_id` IN (".$user_id.",".$group_id."))";                            
                           $sql .= " AND (`initiator_name` LIKE '%".$search_term. "%' OR `ar_behalf_name` LIKE '%".$search_term. "%' OR `business_justification` LIKE '%".$search_term. "%' OR `id` LIKE '%".$search_term. "%')";

                             $query = $this->db->query($sql);
                            return $query->result();                        
                        break;
                }
            }else{
                
                $this->db->select('id,initiator_id,initiator_name,ar_behalf_id,ar_behalf_name,business_justification,status,ticket_id,request_type');
                $this->db->from('dashboard_view');   
                
                switch($boxName){
                    case "Inbox":
                        $this->db->where('status_id',8);
                        $user_ids = array($user_id,$group_id);
                        $this->db->or_where_in('current_actor_id',$user_ids);
                        break;
                    case "Drafts":
                        $this->db->where('initiator_id',$user_id);
                        $this->db->where('status_id',1);
                        $this->db->where('request_type IS NULL',null,false);
                        break;
                    case "Sent":
                        if($admin){ 
                            $status_array = array("2","4");
                            $this->db->where_in('status_id',$status_array);
                        }
                        break;
                    case "Complete":
                        if($admin){
                            $status_array = array("5","6","7");
                            $this->db->where_in('status_id',$status_array);
                        }
                        
                        break;

                }
                $search_term = trim(addslashes($search_term)); // clean up the search term to avoid injections
                $temp = "(`initiator_name` LIKE '%".$search_term. "%' OR `ar_behalf_name` LIKE '%".$search_term. "%' OR `business_justification` LIKE '%".$search_term. "%' OR `id` LIKE '%".$search_term. "%')";
                $this->db->where($temp);

                $query = $this->db->get();
                 
                if($query){
                    if($query->num_rows() > 0){
                        return $query->result();
                        $query = NULL;
                    }
                }else{
                    return false;
                }
            }
        }
        
        /*
         * Update the route information for given request id
         */
        public function updateRequestRoute($args){

            extract($args);
            
            $data = array("active_node_id"=>$next_node_id);
            $this->db->where('request_id', $request_id);
            $this->db->update('routes', $data);
            
            log_message("INFO",$this->db->last_query());
            
            $data = array("state"=>"complete");
            $this->db->where('route_id', $route_id);
            $this->db->where('type', $type);
            $this->db->where('subtype', $subtype);
            $this->db->update('route_nodes', $data);
            
        }
        
}