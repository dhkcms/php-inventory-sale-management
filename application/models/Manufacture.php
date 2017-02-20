<?php
class Manufacture extends CI_Model
{
	public function get_info($manufacture_id)
	{
		// NOTE: temporary tables are created to speed up searches due to the fact that are ortogonal to the main query
		// create a temporary table to contain all the payments per manufacture item
		$this->db->query('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $this->db->dbprefix('manufactures_payments_temp') . 
			'(
				SELECT payments.manufacture_id AS manufacture_id, 
					IFNULL(SUM(payments.payment_amount), 0) AS manufacture_payment_amount,
					GROUP_CONCAT(CONCAT(payments.payment_type, " ", payments.payment_amount) SEPARATOR ", ") AS payment_type
				FROM ' . $this->db->dbprefix('manufactures_payments') . ' AS payments
				INNER JOIN ' . $this->db->dbprefix('manufactures') . ' AS manufactures
					ON manufactures.manufacture_id = payments.manufacture_id
				WHERE manufactures.manufacture_id = ' . $this->db->escape($manufacture_id) . '
				GROUP BY manufacture_id
			)'
		);

		// create a temporary table to contain all the sum of taxes per manufacture item
		$this->db->query('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $this->db->dbprefix('manufactures_items_taxes_temp') . 
			'(
				SELECT manufactures_items_taxes.manufacture_id AS manufacture_id,
					manufactures_items_taxes.item_id AS item_id,
					SUM(manufactures_items_taxes.percent) AS percent
				FROM ' . $this->db->dbprefix('manufactures_items_taxes') . ' AS manufactures_items_taxes
				INNER JOIN ' . $this->db->dbprefix('manufactures') . ' AS manufactures
					ON manufactures.manufacture_id = manufactures_items_taxes.manufacture_id
				INNER JOIN ' . $this->db->dbprefix('manufactures_items') . ' AS manufactures_items
					ON manufactures_items.manufacture_id = manufactures_items_taxes.manufacture_id AND manufactures_items.line = manufactures_items_taxes.line
				WHERE manufactures.manufacture_id = ' . $this->db->escape($manufacture_id) . '
				GROUP BY manufactures_items_taxes.manufacture_id, manufactures_items_taxes.item_id
			)'
		);

		if($this->config->item('tax_included'))
		{
			$manufacture_total = 'SUM(manufactures_items.item_unit_price * manufactures_items.quantity_purchased * (1 - manufactures_items.discount_percent / 100))';
			$manufacture_subtotal = 'SUM(manufactures_items.item_unit_price * manufactures_items.quantity_purchased * (1 - manufactures_items.discount_percent / 100) * (100 / (100 + manufactures_items_taxes.percent)))';
			$manufacture_tax = 'SUM(manufactures_items.item_unit_price * manufactures_items.quantity_purchased * (1 - manufactures_items.discount_percent / 100) * (1 - 100 / (100 + manufactures_items_taxes.percent)))';
		}
		else
		{
			$manufacture_total = 'SUM(manufactures_items.item_unit_price * manufactures_items.quantity_purchased * (1 - manufactures_items.discount_percent / 100) * (1 + (manufactures_items_taxes.percent / 100)))';
			$manufacture_subtotal = 'SUM(manufactures_items.item_unit_price * manufactures_items.quantity_purchased * (1 - manufactures_items.discount_percent / 100))';
			$manufacture_tax = 'SUM(manufactures_items.item_unit_price * manufactures_items.quantity_purchased * (1 - manufactures_items.discount_percent / 100) * (manufactures_items_taxes.percent / 100))';
		}

		$decimals = totals_decimals();

		$this->db->select('
				manufactures.manufacture_id AS manufacture_id,
				DATE(manufactures.manufacture_time) AS manufacture_date,
				manufactures.manufacture_time AS manufacture_time,
				manufactures.last_edit_time AS last_edit_time,
				manufactures.comment AS comment,
				manufactures.invoice_number AS invoice_number,
				manufactures.employee_id AS employee_id,
				manufactures.staff_id AS staff_id,
				staff_p.first_name AS staff_name,
				staff_p.first_name AS first_name,
				staff_p.last_name AS last_name,
				staff_p.email AS email,
				staff_p.comments AS comments,
				' . "
				IFNULL(ROUND($manufacture_total, $decimals), ROUND($manufacture_subtotal, $decimals)) AS amount_due,
				payments.manufacture_payment_amount AS amount_tendered,
				(payments.manufacture_payment_amount - IFNULL(ROUND($manufacture_total, $decimals), ROUND($manufacture_subtotal, $decimals))) AS change_due,
				" . '
				payments.payment_type AS payment_type
		');

		$this->db->from('manufactures_items AS manufactures_items');
		$this->db->join('manufactures AS manufactures', 'manufactures_items.manufacture_id = manufactures.manufacture_id', 'inner');
		$this->db->join('people AS staff_p', 'manufactures.staff_id = staff_p.person_id', 'left');
		$this->db->join('staffs AS staff', 'manufactures.staff_id = staff.person_id', 'left');
		$this->db->join('manufactures_payments_temp AS payments', 'manufactures.manufacture_id = payments.manufacture_id', 'left outer');
		$this->db->join('manufactures_items_taxes_temp AS manufactures_items_taxes', 'manufactures_items.manufacture_id = manufactures_items_taxes.manufacture_id AND manufactures_items.item_id = manufactures_items_taxes.item_id', 'left outer');

		$this->db->where('manufactures.manufacture_id', $manufacture_id);

		$this->db->group_by('manufactures.manufacture_id');
		$this->db->order_by('manufactures.manufacture_time', 'asc');

		return $this->db->get();
	}

	/*
	 Get number of rows for the takings (manufactures/manage) view
	*/
	public function get_found_rows($search, $filters)
	{
		return $this->search($search, $filters)->num_rows();
	}

	/*
	 Get the manufactures data for the takings (manufactures/manage) view
	*/
	public function search($search, $filters, $rows = 0, $limit_from = 0, $sort = 'manufacture_date', $order = 'desc')
	{
		// NOTE: temporary tables are created to speed up searches due to the fact that are ortogonal to the main query
		// create a temporary table to contain all the payments per manufacture item
		$this->db->query('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $this->db->dbprefix('manufactures_payments_temp') . 
			' (PRIMARY KEY(manufacture_id), INDEX(manufacture_id))
			(
				SELECT payments.manufacture_id AS manufacture_id, 
					IFNULL(SUM(payments.payment_amount), 0) AS manufacture_payment_amount,
					GROUP_CONCAT(CONCAT(payments.payment_type, " ", payments.payment_amount) SEPARATOR ", ") AS payment_type
				FROM ' . $this->db->dbprefix('manufactures_payments') . ' AS payments
				INNER JOIN ' . $this->db->dbprefix('manufactures') . ' AS manufactures
					ON manufactures.manufacture_id = payments.manufacture_id
				WHERE DATE(manufactures.manufacture_time) BETWEEN ' . $this->db->escape($filters['start_date']) . ' AND ' . $this->db->escape($filters['end_date']) . '
				GROUP BY manufacture_id
			)'
		);

		// create a temporary table to contain all the sum of taxes per manufacture item
		$this->db->query('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $this->db->dbprefix('manufactures_items_taxes_temp') . 
			' (INDEX(manufacture_id), INDEX(item_id))
			(
				SELECT manufactures_items_taxes.manufacture_id AS manufacture_id,
					manufactures_items_taxes.item_id AS item_id,
					SUM(manufactures_items_taxes.percent) AS percent
				FROM ' . $this->db->dbprefix('manufactures_items_taxes') . ' AS manufactures_items_taxes
				INNER JOIN ' . $this->db->dbprefix('manufactures') . ' AS manufactures
					ON manufactures.manufacture_id = manufactures_items_taxes.manufacture_id
				INNER JOIN ' . $this->db->dbprefix('manufactures_items') . ' AS manufactures_items
					ON manufactures_items.manufacture_id = manufactures_items_taxes.manufacture_id AND manufactures_items.line = manufactures_items_taxes.line
				WHERE DATE(manufactures.manufacture_time) BETWEEN ' . $this->db->escape($filters['start_date']) . ' AND ' . $this->db->escape($filters['end_date']) . '
				GROUP BY manufactures_items_taxes.manufacture_id, manufactures_items_taxes.item_id
			)'
		);

		if($this->config->item('tax_included'))
		{
			$manufacture_total = 'SUM(manufactures_items.item_unit_price * manufactures_items.quantity_purchased * (1 - manufactures_items.discount_percent / 100))';
			$manufacture_subtotal = 'SUM(manufactures_items.item_unit_price * manufactures_items.quantity_purchased * (1 - manufactures_items.discount_percent / 100) * (100 / (100 + manufactures_items_taxes.percent)))';
			$manufacture_tax = 'SUM(manufactures_items.item_unit_price * manufactures_items.quantity_purchased * (1 - manufactures_items.discount_percent / 100) * (1 - 100 / (100 + manufactures_items_taxes.percent)))';
		}
		else
		{
			$manufacture_total = 'SUM(manufactures_items.item_unit_price * manufactures_items.quantity_purchased * (1 - manufactures_items.discount_percent / 100) * (1 + (manufactures_items_taxes.percent / 100)))';
			$manufacture_subtotal = 'SUM(manufactures_items.item_unit_price * manufactures_items.quantity_purchased * (1 - manufactures_items.discount_percent / 100))';
			$manufacture_tax = 'SUM(manufactures_items.item_unit_price * manufactures_items.quantity_purchased * (1 - manufactures_items.discount_percent / 100) * (manufactures_items_taxes.percent / 100))';
		}

		$manufacture_cost = 'SUM(manufactures_items.item_cost_price * manufactures_items.quantity_purchased)';

		$decimals = totals_decimals();

		$this->db->select('
				manufactures.manufacture_id AS manufacture_id,
				DATE(manufactures.manufacture_time) AS manufacture_date,
				manufactures.manufacture_time AS manufacture_time,
				manufactures.invoice_number AS invoice_number,
				SUM(manufactures_items.quantity_purchased) AS items_purchased,
				staff_p.first_name AS staff_name,
				staff.company_name AS company_name,
				' . "
				ROUND($manufacture_subtotal, $decimals) AS subtotal,
				IFNULL(ROUND($manufacture_tax, $decimals), 0) AS tax,
				IFNULL(ROUND($manufacture_total, $decimals), ROUND($manufacture_subtotal, $decimals)) AS total,
				ROUND($manufacture_cost, $decimals) AS cost,
				ROUND($manufacture_total - IFNULL($manufacture_tax, 0) - $manufacture_cost, $decimals) AS profit,
				IFNULL(ROUND($manufacture_total, $decimals), ROUND($manufacture_subtotal, $decimals)) AS amount_due,
				payments.manufacture_payment_amount AS amount_tendered,
				(payments.manufacture_payment_amount - IFNULL(ROUND($manufacture_total, $decimals), ROUND($manufacture_subtotal, $decimals))) AS change_due,
				" . '
				payments.payment_type AS payment_type
		');

		$this->db->from('manufactures_items AS manufactures_items');
		$this->db->join('manufactures AS manufactures', 'manufactures_items.manufacture_id = manufactures.manufacture_id', 'inner');
		$this->db->join('people AS staff_p', 'manufactures.staff_id = staff_p.person_id', 'left');
		$this->db->join('staffs AS staff', 'manufactures.staff_id = staff.person_id', 'left');
		$this->db->join('manufactures_payments_temp AS payments', 'manufactures.manufacture_id = payments.manufacture_id', 'left outer');
		$this->db->join('manufactures_items_taxes_temp AS manufactures_items_taxes', 'manufactures_items.manufacture_id = manufactures_items_taxes.manufacture_id AND manufactures_items.item_id = manufactures_items_taxes.item_id', 'left outer');

		$this->db->where('DATE(manufactures.manufacture_time) BETWEEN ' . $this->db->escape($filters['start_date']) . ' AND ' . $this->db->escape($filters['end_date']));

		if(!empty($search))
		{
			if($filters['is_valid_receipt'] != FALSE)
			{
				$pieces = explode(' ', $search);
				$this->db->where('manufactures.manufacture_id', $pieces[1]);
			}
			else
			{			
				$this->db->group_start();
					// staff last name
					$this->db->like('staff_p.last_name', $search);
					// staff first name
					$this->db->or_like('staff_p.first_name', $search);
					// staff first and last name
					$this->db->or_like('CONCAT(staff_p.first_name, " ", staff_p.last_name)', $search);
					// staff company name
					$this->db->or_like('staff.company_name', $search);
				$this->db->group_end();
			}
		}

		if($filters['location_id'] != 'all')
		{
			$this->db->where('manufactures_items.item_location', $filters['location_id']);
		}

		if($filters['manufacture_type'] == 'manufactures')
        {
            $this->db->where('manufactures_items.quantity_purchased > 0');
        }
        elseif($filters['manufacture_type'] == 'returns')
        {
            $this->db->where('manufactures_items.quantity_purchased < 0');
        }

		if($filters['only_invoices'] != FALSE)
		{
			$this->db->where('manufactures.invoice_number IS NOT NULL');
		}

		if($filters['only_cash'] != FALSE)
		{
			$this->db->group_start();
				$this->db->like('payments.payment_type', $this->lang->line('manufactures_cash'), 'after');
				$this->db->or_where('payments.payment_type IS NULL');
			$this->db->group_end();
		}

		$this->db->group_by('manufactures.manufacture_id');
		$this->db->order_by($sort, $order);

		if($rows > 0)
		{
			$this->db->limit($rows, $limit_from);
		}

		return $this->db->get();
	}

	/*
	 Get the payment summary for the takings (manufactures/manage) view
	*/
	public function get_payments_summary($search, $filters)
	{
		// get payment summary
		$this->db->select('payment_type, count(*) AS count, SUM(payment_amount) AS payment_amount');
		$this->db->from('manufactures');
		$this->db->join('manufactures_payments', 'manufactures_payments.manufacture_id = manufactures.manufacture_id');
		$this->db->join('people AS staff_p', 'manufactures.staff_id = staff_p.person_id', 'left');
		$this->db->join('staffs AS staff', 'manufactures.staff_id = staff.person_id', 'left');

		$this->db->where('DATE(manufacture_time) BETWEEN ' . $this->db->escape($filters['start_date']) . ' AND ' . $this->db->escape($filters['end_date']));

		if(!empty($search))
		{
			if($filters['is_valid_receipt'] != FALSE)
			{
				$pieces = explode(' ',$search);
				$this->db->where('manufactures.manufacture_id', $pieces[1]);
			}
			else
			{
				$this->db->group_start();
					// staff last name
					$this->db->like('staff_p.last_name', $search);
					// staff first name
					$this->db->or_like('staff_p.first_name', $search);
					// staff first and last name
					$this->db->or_like('CONCAT(staff_p.first_name, " ", staff_p.last_name)', $search);
					// staff company name
					$this->db->or_like('staff.company_name', $search);
				$this->db->group_end();
			}
		}

		if($filters['manufacture_type'] == 'manufactures')
		{
			$this->db->where('payment_amount > 0');
		}
		elseif($filters['manufacture_type'] == 'returns')
		{
			$this->db->where('payment_amount < 0');
		}

		if($filters['only_invoices'] != FALSE)
		{
			$this->db->where('invoice_number IS NOT NULL');
		}
		
		if($filters['only_cash'] != FALSE)
		{
			$this->db->like('payment_type', $this->lang->line('manufactures_cash'), 'after');
		}

		$this->db->group_by('payment_type');

		$payments = $this->db->get()->result_array();

		// consider Gift Card as only one type of payment and do not show "Gift Card: 1, Gift Card: 2, etc." in the total
		$gift_card_count = 0;
		$gift_card_amount = 0;
		foreach($payments as $key=>$payment)
		{
			if( strstr($payment['payment_type'], $this->lang->line('manufactures_giftcard')) != FALSE )
			{
				$gift_card_count  += $payment['count'];
				$gift_card_amount += $payment['payment_amount'];

				// remove the "Gift Card: 1", "Gift Card: 2", etc. payment string
				unset($payments[$key]);
			}
		}

		if($gift_card_count > 0)
		{
			$payments[] = array('payment_type' => $this->lang->line('manufactures_giftcard'), 'count' => $gift_card_count, 'payment_amount' => $gift_card_amount);
		}

		return $payments;
	}

	/*
	Gets total of rows
	*/
	public function get_total_rows()
	{
		$this->db->from('manufactures');

		return $this->db->count_all_results();
	}

	public function get_search_suggestions($search, $limit = 25)
	{
		$suggestions = array();

		if(!$this->is_valid_receipt($search))
		{
			$this->db->distinct();
			$this->db->select('first_name, last_name');
			$this->db->from('manufactures');
			$this->db->join('people', 'people.person_id = manufactures.staff_id');
			$this->db->like('last_name', $search);
			$this->db->or_like('first_name', $search);
			$this->db->or_like('CONCAT(first_name, " ", last_name)', $search);
			$this->db->or_like('company_name', $search);
			$this->db->order_by('last_name', 'asc');

			foreach($this->db->get()->result_array() as $result)
			{
				$suggestions[] = array('label' => $result['first_name'] . ' ' . $result['last_name']);
			}
		}
		else
		{
			$suggestions[] = array('label' => $search);
		}

		return $suggestions;
	}

	/*
	Gets total of invoice rows
	*/
	public function get_invoice_count()
	{
		$this->db->from('manufactures');
		$this->db->where('invoice_number IS NOT NULL');

		return $this->db->count_all_results();
	}

	public function get_manufacture_by_invoice_number($invoice_number)
	{
		$this->db->from('manufactures');
		$this->db->where('invoice_number', $invoice_number);

		return $this->db->get();
	}

	public function get_invoice_number_for_year($year = '', $start_from = 0) 
	{
		$year = $year == '' ? date('Y') : $year;
		$this->db->select('COUNT( 1 ) AS invoice_number_year');
		$this->db->from('manufactures');
		$this->db->where('DATE_FORMAT(manufacture_time, "%Y" ) = ', $year);
		$this->db->where('invoice_number IS NOT NULL');
		$result = $this->db->get()->row_array();

		return ($start_from + $result['invoice_number_year']);
	}
	
	public function is_valid_receipt(&$receipt_manufacture_id)
	{
		if(!empty($receipt_manufacture_id))
		{
			//POS #
			$pieces = explode(' ', $receipt_manufacture_id);

			if(count($pieces) == 2 && preg_match('/(POS)/', $pieces[0]))
			{
				return $this->exists($pieces[1]);
			}
			elseif($this->config->item('invoice_enable') == TRUE)
			{
				$manufacture_info = $this->get_manufacture_by_invoice_number($receipt_manufacture_id);
				if($manufacture_info->num_rows() > 0)
				{
					$receipt_manufacture_id = 'POS ' . $manufacture_info->row()->manufacture_id;

					return TRUE;
				}
			}
		}

		return FALSE;
	}

	public function exists($manufacture_id)
	{
		$this->db->from('manufactures');
		$this->db->where('manufacture_id', $manufacture_id);

		return ($this->db->get()->num_rows()==1);
	}

	public function update($manufacture_id, $manufacture_data, $payments)
	{
		$this->db->where('manufacture_id', $manufacture_id);
		$success = $this->db->update('manufactures', $manufacture_data);

		// touch payment only if update manufacture is successful and there is a payments object otherwise the result would be to delete all the payments associated to the manufacture
		if($success && !empty($payments))
		{
			//Run these queries as a transaction, we want to make sure we do all or nothing
			$this->db->trans_start();
			
			// first delete all payments
			$this->db->delete('manufactures_payments', array('manufacture_id' => $manufacture_id));

			// add new payments
			foreach($payments as $payment)
			{
				$manufactures_payments_data = array(
					'manufacture_id' => $manufacture_id,
					'payment_type' => $payment['payment_type'],
					'payment_amount' => $payment['payment_amount']
				);

				$success = $this->db->insert('manufactures_payments', $manufactures_payments_data);
			}
			
			$this->db->trans_complete();
			
			$success &= $this->db->trans_status();
		}
		
		return $success;
	}

	public function save($items, $staff_id, $employee_id, $comment, $invoice_number, $payments, $manufacture_id = FALSE)
	{
		if(count($items) == 0)
		{
			return -1;
		}

		$manufactures_data = array(
			'manufacture_time'		 => date('Y-m-d H:i:s'),
			'staff_id'	 => $this->Staff->exists($staff_id) ? $staff_id : null,
			'employee_id'	 => $employee_id,
			'comment'		 => $comment,
			'invoice_number' => $invoice_number
		);

		// Run these queries as a transaction, we want to make sure we do all or nothing
		$this->db->trans_start();

		$this->db->insert('manufactures', $manufactures_data);
		$manufacture_id = $this->db->insert_id();

		foreach($payments as $payment_id=>$payment)
		{
			if( substr( $payment['payment_type'], 0, strlen( $this->lang->line('manufactures_giftcard') ) ) == $this->lang->line('manufactures_giftcard') )
			{
				// We have a gift card and we have to deduct the used value from the total value of the card.
				$splitpayment = explode( ':', $payment['payment_type'] );
				$cur_giftcard_value = $this->Giftcard->get_giftcard_value( $splitpayment[1] );
				$this->Giftcard->update_giftcard_value( $splitpayment[1], $cur_giftcard_value - $payment['payment_amount'] );
			}

			$manufactures_payments_data = array(
				'manufacture_id'		 => $manufacture_id,
				'payment_type'	 => $payment['payment_type'],
				'payment_amount' => $payment['payment_amount']
			);
			$this->db->insert('manufactures_payments', $manufactures_payments_data);
		}

		foreach($items as $line=>$item)
		{
			$cur_item_info = $this->Item->get_info($item['item_id']);

			$manufactures_items_data = array(
				'manufacture_id'			=> $manufacture_id,
				'item_id'			=> $item['item_id'],
				'line'				=> $item['line'],
				'description'		=> character_limiter($item['description'], 30),
				'serialnumber'		=> character_limiter($item['serialnumber'], 30),
				'quantity_purchased'=> $item['quantity'],
				'discount_percent'	=> $item['discount'],
				'item_cost_price'	=> $cur_item_info->cost_price,
				'item_unit_price'	=> $item['price'],
				'item_location'		=> $item['item_location']
			);

			$this->db->insert('manufactures_items', $manufactures_items_data);

			// Update stock quantity
			$item_quantity = $this->Item_quantity->get_item_quantity($item['item_id'], $item['item_location']);
			$this->Item_quantity->save(array('quantity'		=> $item_quantity->quantity - $item['quantity'],
                                              'item_id'		=> $item['item_id'],
                                              'location_id'	=> $item['item_location']), $item['item_id'], $item['item_location']);

			// if an items was deleted but later returned it's restored with this rule
			if($item['quantity'] < 0)
			{
				$this->Item->undelete($item['item_id']);
			}
											  
			// Inventory Count Details
			$manufacture_remarks = 'POS '.$manufacture_id;
			$inv_data = array(
				'trans_date'		=> date('Y-m-d H:i:s'),
				'trans_items'		=> $item['item_id'],
				'trans_user'		=> $employee_id,
				'trans_location'	=> $item['item_location'],
				'trans_comment'		=> $manufacture_remarks,
				'trans_inventory'	=> -$item['quantity']
			);
			$this->Inventory->insert($inv_data);

			$staff = $this->Staff->get_info($staff_id);
 			if($staff_id == -1 || $staff->taxable)
 			{
				foreach($this->Item_taxes->get_info($item['item_id']) as $row)
				{
					$this->db->insert('manufactures_items_taxes', array(
						'manufacture_id' 	=> $manufacture_id,
						'item_id' 	=> $item['item_id'],
						'line'      => $item['line'],
						'name'		=> $row['name'],
						'percent' 	=> $row['percent']
					));
				}
			}
		}

		$this->db->trans_complete();
		
		if($this->db->trans_status() === FALSE)
		{
			return -1;
		}
		
		return $manufacture_id;
	}

	public function delete_list($manufacture_ids, $employee_id, $update_inventory = TRUE) 
	{
		$result = TRUE;

		foreach($manufacture_ids as $manufacture_id)
		{
			$result &= $this->delete($manufacture_id, $employee_id, $update_inventory);
		}

		return $result;
	}

	public function delete($manufacture_id, $employee_id, $update_inventory = TRUE) 
	{
		// start a transaction to assure data integrity
		$this->db->trans_start();

		// first delete all payments
		$this->db->delete('manufactures_payments', array('manufacture_id' => $manufacture_id));
		// then delete all taxes on items
		$this->db->delete('manufactures_items_taxes', array('manufacture_id' => $manufacture_id));

		if($update_inventory)
		{
			// defect, not all item deletions will be undone??
			// get array with all the items involved in the manufacture to update the inventory tracking
			$items = $this->get_manufacture_items($manufacture_id)->result_array();
			foreach($items as $item)
			{
				// create query to update inventory tracking
				$inv_data = array(
					'trans_date'      => date('Y-m-d H:i:s'),
					'trans_items'     => $item['item_id'],
					'trans_user'      => $employee_id,
					'trans_comment'   => 'Deleting manufacture ' . $manufacture_id,
					'trans_location'  => $item['item_location'],
					'trans_inventory' => $item['quantity_purchased']
				);
				// update inventory
				$this->Inventory->insert($inv_data);

				// update quantities
				$this->Item_quantity->change_quantity($item['item_id'], $item['item_location'], $item['quantity_purchased']);
			}
		}

		// delete all items
		$this->db->delete('manufactures_items', array('manufacture_id' => $manufacture_id));
		// delete manufacture itself
		$this->db->delete('manufactures', array('manufacture_id' => $manufacture_id));

		// execute transaction
		$this->db->trans_complete();
	
		return $this->db->trans_status();
	}

	public function get_manufacture_items($manufacture_id)
	{
		$this->db->from('manufactures_items');
		$this->db->where('manufacture_id', $manufacture_id);

		return $this->db->get();
	}

	public function get_manufacture_payments($manufacture_id)
	{
		$this->db->from('manufactures_payments');
		$this->db->where('manufacture_id', $manufacture_id);

		return $this->db->get();
	}

	public function get_payment_options($giftcard = TRUE)
	{
		$payments = array();

		$options=array('现金','微信','银行转账','支付宝','其他');
		foreach ($options as $value) {$payments[$value]=$value;}
		
		/*if($this->config->item('payment_options_order') == 'debitcreditcash')
		{
			$payments[$this->lang->line('manufactures_debit')] = $this->lang->line('manufactures_debit');
			$payments[$this->lang->line('manufactures_credit')] = $this->lang->line('manufactures_credit');
			$payments[$this->lang->line('manufactures_cash')] = $this->lang->line('manufactures_cash');
		}
		elseif($this->config->item('payment_options_order') == 'debitcashcredit')
		{
			$payments[$this->lang->line('manufactures_debit')] = $this->lang->line('manufactures_debit');
			$payments[$this->lang->line('manufactures_cash')] = $this->lang->line('manufactures_cash');
			$payments[$this->lang->line('manufactures_credit')] = $this->lang->line('manufactures_credit');
		}
		else // default: if($this->config->item('payment_options_order') == 'cashdebitcredit')
		{
			$payments[$this->lang->line('manufactures_cash')] = $this->lang->line('manufactures_cash');
			$payments[$this->lang->line('manufactures_debit')] = $this->lang->line('manufactures_debit');
			$payments[$this->lang->line('manufactures_credit')] = $this->lang->line('manufactures_credit');
		}

		$payments[$this->lang->line('manufactures_check')] = $this->lang->line('manufactures_check');

		if($giftcard)
		{
			$payments[$this->lang->line('manufactures_giftcard')] = $this->lang->line('manufactures_giftcard');
		}*/

		return $payments;
	}

	public function get_staff($manufacture_id)
	{
		$this->db->from('manufactures');
		$this->db->where('manufacture_id', $manufacture_id);

		return $this->Staff->get_info($this->db->get()->row()->staff_id);
	}

	public function get_employee($manufacture_id)
	{
		$this->db->from('manufactures');
		$this->db->where('manufacture_id', $manufacture_id);

		return $this->Employee->get_info($this->db->get()->row()->employee_id);
	}

	public function check_invoice_number_exists($invoice_number, $manufacture_id = '')
	{
		$this->db->from('manufactures');
		$this->db->where('invoice_number', $invoice_number);
		if(!empty($manufacture_id))
		{
			$this->db->where('manufacture_id !=', $manufacture_id);
		}
		
		return ($this->db->get()->num_rows() == 1);
	}

	public function get_giftcard_value($giftcardNumber)
	{
		if(!$this->Giftcard->exists($this->Giftcard->get_giftcard_id($giftcardNumber)))
		{
			return 0;
		}
		
		$this->db->from('giftcards');
		$this->db->where('giftcard_number', $giftcardNumber);

		return $this->db->get()->row()->value;
	}

	//We create a temp table that allows us to do easy report/manufactures queries
	public function create_temp_table(array $inputs)
	{
		if($this->config->item('tax_included'))
		{
			$manufacture_total = '(manufactures_items.item_unit_price * manufactures_items.quantity_purchased * (1 - manufactures_items.discount_percent / 100))';
			$manufacture_subtotal = '(manufactures_items.item_unit_price * manufactures_items.quantity_purchased * (1 - manufactures_items.discount_percent / 100) * (100 / (100 + SUM(manufactures_items_taxes.percent))))';
			$manufacture_tax = '(manufactures_items.item_unit_price * manufactures_items.quantity_purchased * (1 - manufactures_items.discount_percent / 100) * (1 - 100 / (100 + SUM(manufactures_items_taxes.percent))))';
		}
		else
		{
			$manufacture_total = '(manufactures_items.item_unit_price * manufactures_items.quantity_purchased * (1 - manufactures_items.discount_percent / 100) * (1 + (SUM(manufactures_items_taxes.percent) / 100)))';
			$manufacture_subtotal = '(manufactures_items.item_unit_price * manufactures_items.quantity_purchased * (1 - manufactures_items.discount_percent / 100))';
			$manufacture_tax = '(manufactures_items.item_unit_price * manufactures_items.quantity_purchased * (1 - manufactures_items.discount_percent / 100) * (SUM(manufactures_items_taxes.percent) / 100))';
		}

		$manufacture_cost  = '(manufactures_items.item_cost_price * manufactures_items.quantity_purchased)';

		$decimals = totals_decimals();

		if(empty($inputs['manufacture_id']))
		{
			$where = 'WHERE DATE(manufactures.manufacture_time) BETWEEN ' . $this->db->escape($inputs['start_date']) . ' AND ' . $this->db->escape($inputs['end_date']);
		}
		else
		{
			$where = 'WHERE manufactures.manufacture_id = ' . $this->db->escape($inputs['manufacture_id']);
		}

		// create a temporary table to contain all the payment types and amount
		$this->db->query('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $this->db->dbprefix('manufactures_payments_temp') . 
			' (PRIMARY KEY(manufacture_id), INDEX(manufacture_id))
			(
				SELECT payments.manufacture_id AS manufacture_id, 
					IFNULL(SUM(payments.payment_amount), 0) AS manufacture_payment_amount,
					GROUP_CONCAT(CONCAT(payments.payment_type, " ", payments.payment_amount) SEPARATOR ", ") AS payment_type
				FROM ' . $this->db->dbprefix('manufactures_payments') . ' AS payments
				INNER JOIN ' . $this->db->dbprefix('manufactures') . ' AS manufactures
					ON manufactures.manufacture_id = payments.manufacture_id
				' . "
				$where
				" . '
				GROUP BY payments.manufacture_id
			)'
		);

		$this->db->query('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $this->db->dbprefix('manufactures_items_temp') . 
			' (INDEX(manufacture_date), INDEX(manufacture_id))
			(
				SELECT
					DATE(manufactures.manufacture_time) AS manufacture_date,
					manufactures.manufacture_time,
					manufactures.manufacture_id,
					manufactures.comment,
					manufactures.invoice_number,
					manufactures.staff_id,
					staff_p.first_name AS staff_name,
					staff_p.first_name AS staff_first_name,
					staff_p.last_name AS staff_last_name,
					staff_p.email AS staff_email,
					staff_p.comments AS staff_comments, 
					staff.company_name AS staff_company_name,
					manufactures.employee_id,
					CONCAT(employee.first_name, " ", employee.last_name) AS employee_name,
					items.item_id,
					items.name,
					items.category,
					items.supplier_id,
					manufactures_items.quantity_purchased,
					manufactures_items.item_cost_price,
					manufactures_items.item_unit_price,
					manufactures_items.discount_percent,
					manufactures_items.line,
					manufactures_items.serialnumber,
					manufactures_items.item_location,
					manufactures_items.description,
					payments.payment_type,
					payments.manufacture_payment_amount,
					IFNULL(SUM(manufactures_items_taxes.percent), 0) AS item_tax_percent,
					' . "
					ROUND($manufacture_subtotal, $decimals) AS subtotal,
					IFNULL(ROUND($manufacture_tax, $decimals), 0) AS tax,
					IFNULL(ROUND($manufacture_total, $decimals), ROUND($manufacture_subtotal, $decimals)) AS total,
					ROUND($manufacture_cost, $decimals) AS cost,
					ROUND($manufacture_total - IFNULL($manufacture_tax, 0) - $manufacture_cost, $decimals) AS profit
					" . '
				FROM ' . $this->db->dbprefix('manufactures_items') . ' AS manufactures_items
				INNER JOIN ' . $this->db->dbprefix('manufactures') . ' AS manufactures
					ON manufactures_items.manufacture_id = manufactures.manufacture_id
				INNER JOIN ' . $this->db->dbprefix('items') . ' AS items
					ON manufactures_items.item_id = items.item_id
				LEFT OUTER JOIN ' . $this->db->dbprefix('manufactures_payments_temp') . ' AS payments
					ON manufactures_items.manufacture_id = payments.manufacture_id		
				LEFT OUTER JOIN ' . $this->db->dbprefix('suppliers') . ' AS supplier
					ON items.supplier_id = supplier.person_id
				LEFT OUTER JOIN ' . $this->db->dbprefix('people') . ' AS staff_p
					ON manufactures.staff_id = staff_p.person_id
				LEFT OUTER JOIN ' . $this->db->dbprefix('staffs') . ' AS staff
					ON manufactures.staff_id = staff.person_id
				LEFT OUTER JOIN ' . $this->db->dbprefix('people') . ' AS employee
					ON manufactures.employee_id = employee.person_id
				LEFT OUTER JOIN ' . $this->db->dbprefix('manufactures_items_taxes') . ' AS manufactures_items_taxes
					ON manufactures_items.manufacture_id = manufactures_items_taxes.manufacture_id AND manufactures_items.item_id = manufactures_items_taxes.item_id AND manufactures_items.line = manufactures_items_taxes.line
				' . "
				$where
				" . '
				GROUP BY manufactures.manufacture_id, items.item_id, manufactures_items.line
			)'
		);

		// drop the temporary table to contain memory consumption as it's no longer required
		$this->db->query('DROP TEMPORARY TABLE IF EXISTS ' . $this->db->dbprefix('manufactures_payments_temp'));
	}
}
?>
