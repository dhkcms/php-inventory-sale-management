<?php
class Stock_location extends CI_Model
{
    public function exists($location_name = '')
    {
        $this->db->from('stock_locations');  
        $this->db->where('location_name', $location_name);
        $this->db->or_where('location_id', $location_name);
        
        return ($this->db->get()->num_rows() >= 1);
    }
    
    public function get_all($limit = 10000, $offset = 0)
    {
        $this->db->from('stock_locations');
        $this->db->limit($limit);
        $this->db->offset($offset);
	
        return $this->db->get();
    }
    
    public function get_undeleted_all($module_id = 'items')
    {
        $this->db->from('stock_locations');
        /*$this->db->join('permissions', 'permissions.location_id = stock_locations.location_id');
		$this->db->join('grants', 'grants.permission_id = permissions.permission_id');
        $this->db->where('person_id', $this->session->userdata('person_id'));
        $this->db->like('permissions.permission_id', $module_id, 'after');*/
        $this->db->where('deleted', 0);

        return $this->db->get();
    }

	public function show_locations($module_id = 'items')
	{
		$stock_locations = $this->get_allowed_locations($module_id);

		return count($stock_locations) > 1;
	}

	public function multiple_locations()
	{
		return $this->get_all()->num_rows() > 1;
	}

    public function get_allowed_locations($module_id = 'items')
    {
    	$stock = $this->get_undeleted_all($module_id)->result_array();
    	$stock_locations = array();
    	foreach($stock as $location_data)
    	{
    		$stock_locations[$location_data['location_id']] = $location_data['location_name'];
    	}

    	return $stock_locations;
    }

	public function is_allowed_location($location_id, $module_id = 'items')
	{
		$this->db->from('stock_locations');
		/*$this->db->join('permissions', 'permissions.location_id = stock_locations.location_id');
		$this->db->join('grants', 'grants.permission_id = permissions.permission_id');
		$this->db->where('person_id', $this->session->userdata('person_id'));
		$this->db->like('permissions.permission_id', $module_id, 'after');*/
		$this->db->where('deleted', 0);
		$this->db->where('stock_locations.location_id', $location_id);

		return ($this->db->get()->num_rows() == 1);
	}
    
    public function get_default_location_id()
    {
        return $this->Employee->get_logged_in_employee_info()->location_id;

    	/*$this->db->from('stock_locations');
    	$this->db->join('permissions', 'permissions.location_id = stock_locations.location_id');
		$this->db->join('grants', 'grants.permission_id = permissions.permission_id');
    	$this->db->where('person_id', $this->session->userdata('person_id'));
    	$this->db->where('deleted', 0);
    	$this->db->limit(1);

    	return $this->db->get()->row()->location_id;*/
    }
    
    public function get_location_name($location_id) 
    {
    	$this->db->from('stock_locations');
    	$this->db->where('location_id', $location_id);

    	return $this->db->get()->row()->location_name;
    }
    
    public function save(&$location_data, $location_id) 
    {
		$location_name = $location_data['location_name'];

    	if(!$this->exists($location_name))
    	{
    		$this->db->trans_start();

    		$location_data = array('location_name'=>$location_name, 'deleted'=>0);
   			$this->db->insert('stock_locations', $location_data);
   			$location_id = $this->db->insert_id();
   			 
   			$this->_insert_new_permission('items', $location_id, $location_name);
   			$this->_insert_new_permission('sales', $location_id, $location_name);
   			$this->_insert_new_permission('receivings', $location_id, $location_name);
    		
   			// insert quantities for existing items
   			$items = $this->Item->get_all();
   			foreach($items->result_array() as $item)
   			{
   				$quantity_data = array('item_id' => $item['item_id'], 'location_id' => $location_id, 'quantity' => 0);
   				$this->db->insert('item_quantities', $quantity_data);
   			}

   			$this->db->trans_complete();
			
			return $this->db->trans_status();
   		}
    	else 
    	{
    		$this->db->where('location_id', $location_id);

    		return $this->db->update('stock_locations', $location_data);
    	}
    }
    	
    private function _insert_new_permission($module, $location_id, $location_name)
    {
    	// insert new permission for stock location
    	$permission_id = $module . '_' . $location_name;
    	$permission_data = array('permission_id' => $permission_id, 'module_id' => $module, 'location_id' => $location_id);
    	$this->db->insert('permissions', $permission_data);
    	
    	// insert grants for new permission
    	$employees = $this->Employee->get_all();
    	foreach($employees->result_array() as $employee)
    	{
    		$grants_data = array('permission_id' => $permission_id, 'person_id' => $employee['person_id']);
    		$this->db->insert('grants', $grants_data);
    	}
    }
    
    /*
     Deletes one item
    */
    public function delete($location_id)
    {
    	$this->db->trans_start();

    	$this->db->where('location_id', $location_id);
    	$this->db->update('stock_locations', array('deleted' => 1));
    	
    	$this->db->where('location_id', $location_id);
    	$this->db->delete('permissions');

    	$this->db->trans_complete();
		
		return $this->db->trans_status();
    }

    public function store_all_to_session($override=TRUE){
        if(!$override&&!empty($this->get_location_name_session())){
            return;
        }

        $locations=array();//echo "load db";

        foreach($this->get_all()->result() as $row){
            $locations[$row->location_id]=$row->location_name;
        }

        $this->session->set_userdata('stock_locations',$locations);
    }

    public function get_location_name_session($location_id=null){
        $locations=$this->session->userdata('stock_locations');

        if(empty($location_id)){return $locations;}
        else{return $locations[$location_id];}
    }

    public function get_info($location_id){
        $obj;$obj->first_name=$this->get_location_name_session($location_id);

        return $obj;
    }
}
?>