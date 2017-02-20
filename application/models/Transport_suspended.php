<?php
class Transport_suspended extends CI_Model
{
	public $dbprefix='transport';//_suspended
	public $partner='stock_location';
	public $inventory_ref_type=3;

	public function get_all()
	{
		$this->db->from($this->dbprefix.'s');
		$this->db->order_by($this->dbprefix.'_id');

		return $this->db->get();
	}
	
	public function get_info($transport_id)
	{
		$this->db->from($this->dbprefix.'s');
		$this->db->where($this->dbprefix.'_id', $transport_id);
		$this->db->join('people', 'people.person_id = '.$this->dbprefix.'s.'.$this->partner.'_id', 'LEFT');

		return $this->db->get();
	}

	/*
	Gets total of invocie rows
	*/
	public function get_invoice_count()
	{
		$this->db->from($this->dbprefix.'s');
		$this->db->where('invoice_number IS NOT NULL');

		return $this->db->count_all_results();
	}
	
	public function get_transaction_by_invoice_number($invoice_number)
	{
		$this->db->from($this->dbprefix.'s');
		$this->db->where('invoice_number', $invoice_number);

		return $this->db->get();
	}

	public function exists($transport_id)
	{
		$this->db->from($this->dbprefix.'s');
		$this->db->where($this->dbprefix.'_id', $transport_id);

		return ($this->db->get()->num_rows() == 1);
	}
	
	public function update($transport_data, $transport_id)
	{
		$this->db->where($this->dbprefix.'_id', $transport_id);

		return $this->db->update($this->dbprefix.'s', $transport_data);
	}
	
	public function save($items, $stock_location_id, $employee_id, $comment, 
		$invoice_number, $payments, $infos,$transport_id = FALSE)
	{
		if(count($items) == 0)
		{
			return -1;
		}

		$date_now=date('Y-m-d H:i:s');
		$transports_data = array(
			'last_edit_time' => $date_now,
			$this->partner.'_id'    => $this->Stock_location->exists($stock_location_id) ? $stock_location_id : null,
			'employee_id'    => $employee_id,
			'comment'        => $comment,
			'invoice_number' => $invoice_number,
			'mail_state'	 => $infos['mail_state'],
			'transport_items_location'=>$infos['transport_items_location']
		);

		$this->delete_together($transport_id);

		//Run these queries as a transaction, we want to make sure we do all or nothing
		$this->db->trans_start();

		$last_suspended_sale_info = null;

		if(empty($transport_id)||FALSE==$transport_id){
			$transports_data[$this->dbprefix.'_time']=$date_now;

			$this->db->insert($this->dbprefix.'s', $transports_data);
			$transport_id = $this->db->insert_id();
		}else{
			$last_suspended_sale_info = $this->get_info($transport_id)->row();//warning
			$this->update($transports_data,$transport_id);
		}

		foreach($payments as $payment_id=>$payment)
		{
			$transports_payments_data = array(
				$this->dbprefix.'_id'        => $transport_id,
				'payment_type'   => $payment['payment_type'],
				'payment_amount' => $payment['payment_amount']
			);

			$this->db->insert($this->dbprefix.'s'.'_payments', $transports_payments_data);
		}

		foreach($items as $line=>$item)
		{
			$is_item_kit=(1==empty($item['is_item_kit'])?0:$item['is_item_kit']);
			$cur_item_info = $is_item_kit?$this->Item_kit->get_info_like_item($item['item_id'])
									:$this->Item->get_info($item['item_id']);
			//$cur_item_info = $this->Item->get_info($item['item_id']);

			$transports_items_data = array(
				$this->dbprefix.'_id'            => $transport_id,
				'item_id'            => $item['item_id'],
				'line'               => $item['line'],
				'description'        => character_limiter($item['description'], 30),
				'serialnumber'       => character_limiter($item['serialnumber'], 30),
				'quantity_purchased' => $item['quantity'],
				'discount_percent'   => 0,//$item['discount'],
				'item_cost_price'    => $cur_item_info->cost_price,
				'item_unit_price'    => $item['price'],
				'item_wage_price'    => $cur_item_info->wage_price,'is_item_kit'=>$is_item_kit?1:0,
				'item_location'      => $item['item_location']
			);

			$this->db->insert($this->dbprefix.'s'.'_items', $transports_items_data);

		}

		if(array_key_exists('cart_diff', $infos)){
			foreach ($infos['cart_diff'] as $item_id => $locations) {
				foreach ($locations as $item_loc => $quantities) {
					$quantity_old=$quantities['old'];$quantity_new=$quantities['new'];

					
                    for($factor=-1;$factor<=1;$factor+=2){
                    if($factor>0){
                        if($last_suspended_sale_info->mail_state!=3){$quantity_old=0;}
                        if($infos['mail_state']!=3){$quantity_new=0;}
                        $item_loc=$stock_location_id;
                    }
        

					if($quantity_old==$quantity_new){continue;}

					$diff=$quantity_new-$quantity_old;
					$item_quantity = $this->Item_quantity->get_item_quantity($item_id,$item_loc);
					$this->Item_quantity->save(array('quantity'		=> $item_quantity->quantity + $factor*$diff,
                                              'item_id'		=> $item_id,
                                              'location_id'	=> $item_loc), $item_id, $item_loc);

					if($quantity_old!=0){
						$this->db->delete('inventory',array('ref_type'=>$this->inventory_ref_type,'ref_id'=>$transport_id,
							'trans_items'=>$item_id,'trans_location'=>$item_loc));
					}

					if($quantity_new!=0){//echo $item_id." ".$quantity_old." ".$quantity_new." _";
						$transport_remarks = $this->dbprefix.'(序号'.$transport_id.')';
						$inv_data = array(
							'trans_date'		=> date('Y-m-d H:i:s'),
							'trans_items'		=> $item_id,
							'trans_user'		=> $employee_id,
							'trans_location'	=> $item_loc,
							'trans_comment'		=> $transport_remarks,
							'trans_inventory'	=> $factor*$quantity_new,
							'ref_type'=>$this->inventory_ref_type,'ref_id'=>$transport_id
						);
						$this->Inventory->insert($inv_data);
					}
					
					}
				}
			}
		}

		$this->db->trans_complete();
		
		if($this->db->trans_status() === FALSE)
		{
			return -1;
		}
		
		return $transport_id;
	}
	
	public function delete($transport_id)
	{
		//Run these queries as a transaction, we want to make sure we do all or nothing
		$this->db->trans_start();
		
		$this->db->delete($this->dbprefix.'s'.'_payments', array($this->dbprefix.'_id' => $transport_id)); 
		$this->db->delete($this->dbprefix.'s'.'_items_taxes', array($this->dbprefix.'_id' => $transport_id)); 
		$this->db->delete($this->dbprefix.'s'.'_items', array($this->dbprefix.'_id' => $transport_id)); 
		$this->db->delete($this->dbprefix.'s', array($this->dbprefix.'_id' => $transport_id)); 
		
		$this->db->trans_complete();
				
		return $this->db->trans_status();
	}
	public function delete_together($transport_id)
	{
		if(empty($transport_id)||FALSE==$transport_id){return FALSE;}

		//Run these queries as a transaction, we want to make sure we do all or nothing
		$this->db->trans_start();
		
		$this->db->delete($this->dbprefix.'s'.'_payments', array($this->dbprefix.'_id' => $transport_id)); 
		$this->db->delete($this->dbprefix.'s'.'_items_taxes', array($this->dbprefix.'_id' => $transport_id)); 
		$this->db->delete($this->dbprefix.'s'.'_items', array($this->dbprefix.'_id' => $transport_id)); 
		//$this->db->delete($this->dbprefix.'s', array($this->dbprefix.'_id' => $transport_id)); 
		
		$this->db->trans_complete();
				
		return $this->db->trans_status();
	}

	public function get_transaction_items($transport_id)
	{
		$this->db->from($this->dbprefix.'s'.'_items');
		$this->db->where($this->dbprefix.'_id', $transport_id);

		return $this->db->get();
	}

	public function get_transaction_payments($transport_id)
	{
		$this->db->from($this->dbprefix.'s'.'_payments');
		$this->db->where($this->dbprefix.'_id', $transport_id);

		return $this->db->get();
	}

	public function get_comment($transport_id)
	{
		$this->db->from($this->dbprefix.'s');
		$this->db->where($this->dbprefix.'_id', $transport_id);

		return $this->db->get()->row()->comment;
	}
}
?>
