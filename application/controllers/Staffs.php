<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once("Persons.php");

class Staffs extends Persons
{
	public function __construct()
	{
		parent::__construct('staffs');
	}
	
	public function index()
	{
		$data['table_headers'] = $this->xss_clean(get_people_manage_table_headers());

		$this->load->view('people/manage', $data);
	}
	
	/*
	Returns Staff table data rows. This will be called with AJAX.
	*/
	public function search()
	{
		$search = $this->input->get('search');
		$limit  = $this->input->get('limit');
		$offset = $this->input->get('offset');
		$sort   = $this->input->get('sort');
		$order  = $this->input->get('order');

		$staff = $this->Staff->search($search, $limit, $offset, $sort, $order);
		$total_rows = $this->Staff->get_found_rows($search);
		
		$data_rows = array();
		foreach($staff->result() as $person)
		{
			$data_rows[] = get_person_data_row($person, $this);
		}

		$data_rows = $this->xss_clean($data_rows);

		echo json_encode(array('total' => $total_rows, 'rows' => $data_rows));
	}
	
	/*
	Gives search suggestions based on what is being searched for
	*/
	public function suggest()
	{
		$suggestions = $this->xss_clean($this->Staff->get_search_suggestions($this->input->post('term')),TRUE);

		echo json_encode($suggestions);
	}
	
	/*
	Loads the Staff edit form
	*/
	public function view($Staff_id = -1)
	{
		$person_info = $this->Staff->get_info($Staff_id);
		foreach(get_object_vars($person_info) as $property => $value)
		{
			$person_info->$property = $this->xss_clean($value);
		}
		$data['person_info'] = $person_info;

		$this->load->view("staffs/form", $data);
	}
	
	/*
	Inserts/updates an Staff
	*/
	public function save($Staff_id = -1)
	{
		$person_data = array(
			'first_name' => $this->input->post('first_name'),
			'last_name' => $this->input->post('last_name'),
			'gender' => $this->input->post('gender'),
			'email' => $this->input->post('email'),
			'phone_number' => $this->input->post('phone_number'),
			'address_1' => $this->input->post('address_1'),
			'address_2' => $this->input->post('address_2'),
			'city' => $this->input->post('city'),
			'state' => $this->input->post('state'),
			'zip' => $this->input->post('zip'),
			'country' => $this->input->post('country'),
			'comments' => $this->input->post('comments')
		);
		
		$Staff_data=array(
			'account_number' => $this->input->post('account_number') == '' ? NULL : $this->input->post('account_number')
		);

		if($this->Staff->save_staff($person_data, $Staff_data, $Staff_id))
		{
			$person_data = $this->xss_clean($person_data);
			$Staff_data = $this->xss_clean($Staff_data);

			//New Staff
			if($Staff_id == -1)
			{
				echo json_encode(array('success' => TRUE, 'message' => $this->lang->line('staffs_successful_adding').' '.
								$person_data['first_name'].' '.$person_data['last_name'], 'id' => $Staff_data['person_id']));
			}
			else //Existing Staff
			{
				echo json_encode(array('success' => TRUE, 'message' => $this->lang->line('staffs_successful_updating').' '.
								$person_data['first_name'].' '.$person_data['last_name'], 'id' => $Staff_id));
			}
		}
		else//failure
		{
			$person_data = $this->xss_clean($person_data);

			echo json_encode(array('success' => FALSE, 'message' => $this->lang->line('staffs_error_adding_updating').' '.
							$person_data['first_name'].' '.$person_data['last_name'], 'id' => -1));
		}
	}
	
	/*
	This deletes staff from the staff table
	*/
	public function delete()
	{
		$staff_to_delete = $this->xss_clean($this->input->post('ids'));

		if($this->Staff->delete_list($staff_to_delete))
		{
			echo json_encode(array('success' => TRUE,'message' => $this->lang->line('staff_successful_deleted').' '.
							count($staff_to_delete).' '.$this->lang->line('staff_one_or_multiple')));
		}
		else
		{
			echo json_encode(array('success' => FALSE,'message' => $this->lang->line('staff_cannot_be_deleted')));
		}
	}
}
?>