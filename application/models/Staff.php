<?php
class Staff extends Person
{
	/*
	Determines if a given person_id is an employee
	*/
	public function exists($person_id)
	{
		$this->db->from('staffs');	
		$this->db->join('people', 'people.person_id = staffs.person_id');
		$this->db->where('staffs.person_id', $person_id);

		return ($this->db->get()->num_rows() == 1);
	}	

	/*
	Gets total of rows
	*/
	public function get_total_rows()
	{
		$this->db->from('staffs');
		$this->db->where('deleted', 0);

		return $this->db->count_all_results();
	}

	/*
	Returns all the staff
	*/
	public function get_all($limit = 10000, $offset = 0)
	{
		$this->db->from('staffs');
		$this->db->where('deleted', 0);		
		$this->db->join('people', 'staffs.person_id = people.person_id');			
		$this->db->order_by('last_name', 'asc');
		$this->db->limit($limit);
		$this->db->offset($offset);

		return $this->db->get();		
	}
	
	/*
	Gets information about a particular employee
	*/
	public function get_info($staff_id)
	{
		$this->db->from('staffs');	
		$this->db->join('people', 'people.person_id = staffs.person_id');
		$this->db->where('staffs.person_id', $staff_id);
		$query = $this->db->get();

		if($query->num_rows() == 1)
		{
			return $query->row();
		}
		else
		{
			//Get empty base parent object, as $staff_id is NOT an employee
			$person_obj = parent::get_info(-1);

			//Get all the fields from employee table
			//append those fields to base parent object, we we have a complete empty object
			foreach($this->db->list_fields('staffs') as $field)
			{
				$person_obj->$field = '';
			}

			return $person_obj;
		}
	}

	/*
	Gets information about multiple staff
	*/
	public function get_multiple_info($staff_ids)
	{
		$this->db->from('staffs');
		$this->db->join('people', 'people.person_id = staffs.person_id');		
		$this->db->where_in('staffs.person_id', $staff_ids);
		$this->db->order_by('last_name', 'asc');

		return $this->db->get();		
	}

	/*
	Inserts or updates an employee
	*/
	public function save_staff(&$person_data, &$staff_data, $staff_id = FALSE)
	{
		$success = FALSE;

		$this->db->trans_start();

		if(parent::save($person_data, $staff_id))
		{
			if(!$staff_id || !$this->exists($staff_id))
			{
				$staff_data['person_id'] = $staff_id = $person_data['person_id'];
				$success = $this->db->insert('staffs', $staff_data);
			}
			else
			{
				$this->db->where('person_id', $staff_id);
				$success = $this->db->update('staffs', $staff_data);
			}
		}
		
		$this->db->trans_complete();
		
		$success &= $this->db->trans_status();

		return $success;
	}

	/*
	Deletes one employee
	*/
	public function delete($staff_id)
	{
		//Run these queries as a transaction, we want to make sure we do all or nothing
		$this->db->trans_start();

		$this->db->where('person_id', $staff_id);
		$success = $this->db->update('staffs', array('deleted' => 1));

		$this->db->trans_complete();

		return $success;
	}

	/*
	Deletes a list of staff
	*/
	public function delete_list($staff_ids)
	{
		//Run these queries as a transaction, we want to make sure we do all or nothing
		$this->db->trans_start();

		$this->db->where_in('person_id', $staff_ids);
		$success = $this->db->update('staffs', array('deleted' => 1));

		$this->db->trans_complete();

		return $success;
 	}

	/*
	Get search suggestions to find staff
	*/
	/*public function get_search_suggestions($search, $limit = 5)
	{
		$suggestions = array();

		$this->db->from('staffs');
		$this->db->join('people', 'staffs.person_id = people.person_id');
		$this->db->group_start();
			$this->db->like('first_name', $search);
			$this->db->or_like('last_name', $search); 
			$this->db->or_like('CONCAT(first_name, " ", last_name)', $search);
		$this->db->group_end();
		$this->db->where('deleted', 0);
		$this->db->order_by('last_name', 'asc');
		foreach($this->db->get()->result() as $row)
		{
			$suggestions[] = array('value' => $row->person_id, 'label' => $row->first_name.' '.$row->last_name);
		}

		$this->db->from('staffs');
		$this->db->join('people', 'staffs.person_id = people.person_id');
		$this->db->where('deleted', 0);
		$this->db->like('email', $search);
		$this->db->order_by('email', 'asc');
		foreach($this->db->get()->result() as $row)
		{
			$suggestions[] = array('value' => $row->person_id, 'label' => $row->email);
		}

		$this->db->from('staffs');
		$this->db->join('people', 'staffs.person_id = people.person_id');
		$this->db->where('deleted', 0);
		$this->db->like('phone_number', $search);
		$this->db->order_by('phone_number', 'asc');
		foreach($this->db->get()->result() as $row)
		{
			$suggestions[] = array('value' => $row->person_id, 'label' => $row->phone_number);
		}

		//only return $limit suggestions
		if(count($suggestions > $limit))
		{
			$suggestions = array_slice($suggestions, 0, $limit);
		}

		return $suggestions;
	}*/

	public function get_search_suggestions($search, $unique = FALSE, $limit = 25)
	{
		$suggestions = array();

		$this->db->from('staffs');
		$this->db->join('people', 'staffs.person_id = people.person_id');
		$this->db->where('deleted', 0);
		$this->db->like('company_name', $search);
		$this->db->order_by('company_name', 'asc');
		foreach($this->db->get()->result() as $row)
		{
			$suggestions[] = array('value' => $row->person_id, 'label' => $row->company_name);
		}

		/*$this->db->from('staffs');
		$this->db->join('people', 'staffs.person_id = people.person_id');
		$this->db->where('deleted', 0);
		$this->db->distinct();
		$this->db->like('agency_name', $search);
		$this->db->where('agency_name IS NOT NULL');
		$this->db->order_by('agency_name', 'asc');
		foreach($this->db->get()->result() as $row)
		{
			$suggestions[] = array('value' => $row->person_id, 'label' => $row->agency_name);
		}*/

		$this->db->from('staffs');
		$this->db->join('people', 'staffs.person_id = people.person_id');
		$this->db->group_start();
			$this->db->like('first_name', $search);
			$this->db->or_like('last_name', $search); 
			$this->db->or_like('CONCAT(first_name, " ", last_name)', $search);
		$this->db->group_end();
		$this->db->where('deleted', 0);
		$this->db->order_by('last_name', 'asc');
		foreach($this->db->get()->result() as $row)
		{
			$suggestions[] = array('value' => $row->person_id, 'label' => $row->first_name . ' ' . $row->last_name);
		}

		if(!$unique)
		{
			/*$this->db->from('staffs');
			$this->db->join('people', 'staffs.person_id = people.person_id');
			$this->db->where('deleted', 0);
			$this->db->like('email', $search);
			$this->db->order_by('email', 'asc');
			foreach($this->db->get()->result() as $row)
			{
				$suggestions[] = array('value' => $row->person_id, 'label' => $row->email);
			}

			$this->db->from('staffs');
			$this->db->join('people', 'staffs.person_id = people.person_id');
			$this->db->where('deleted', 0);
			$this->db->like('phone_number', $search);
			$this->db->order_by('phone_number', 'asc');
			foreach($this->db->get()->result() as $row)
			{
				$suggestions[] = array('value' => $row->person_id, 'label' => $row->phone_number);
			}

			$this->db->from('staffs');
			$this->db->join('people', 'staffs.person_id = people.person_id');
			$this->db->where('deleted', 0);
			$this->db->like('account_number', $search);
			$this->db->order_by('account_number', 'asc');
			foreach($this->db->get()->result() as $row)
			{
				$suggestions[] = array('value' => $row->person_id, 'label' => $row->account_number);
			}*/
		}

		//only return $limit suggestions
		if(count($suggestions > $limit))
		{
			$suggestions = array_slice($suggestions, 0, $limit);
		}

		return $suggestions;
	}

 	/*
	Gets rows
	*/
	public function get_found_rows($search)
	{
		$this->db->from('staffs');
		$this->db->join('people', 'staffs.person_id = people.person_id');
		$this->db->group_start();
			$this->db->like('first_name', $search);
			$this->db->or_like('last_name', $search);
			$this->db->or_like('email', $search);
			$this->db->or_like('phone_number', $search);
			$this->db->or_like('CONCAT(first_name, " ", last_name)', $search);
		$this->db->group_end();
		$this->db->where('deleted', 0);

		return $this->db->get()->num_rows();
	}

	/*
	Performs a search on staff
	*/
	public function search($search, $rows = 0, $limit_from = 0, $sort = 'last_name', $order = 'asc')
	{
		$this->db->from('staffs');
		$this->db->join('people', 'staffs.person_id = people.person_id');
		$this->db->group_start();
			$this->db->like('first_name', $search);
			$this->db->or_like('last_name', $search);
			$this->db->or_like('email', $search);
			$this->db->or_like('phone_number', $search);
			$this->db->or_like('CONCAT(first_name, " ", last_name)', $search);
		$this->db->group_end();
		$this->db->where('deleted', 0);
		$this->db->order_by($sort, $order);

		if($rows > 0)
		{
			$this->db->limit($rows, $limit_from);
		}

		return $this->db->get();	
	}
}
?>
