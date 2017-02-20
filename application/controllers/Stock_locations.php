<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once("Secure_Controller.php");

class Stock_locations extends Secure_Controller
{
	public function __construct($module_id = NULL)
	{
		parent::__construct($module_id);		
	}
	
	/*
	 Gives search suggestions based on what is being searched for
	*/
	public function suggest()
	{
		$locations=$this->Stock_location->get_location_name_session();
		$results=array();$default_id=$this->Stock_location->get_default_location_id();
		foreach ($locations as $loc_id => $loc_name) {
			if($default_id==$loc_id){continue;}
			$results[]=array('value'=>$loc_id,"label"=>$loc_name);
		}

		$suggestions = $this->xss_clean($results,TRUE);
		echo json_encode($suggestions);
	}
		
	public function get_row($row_id)
	{
		
	}
}
?>