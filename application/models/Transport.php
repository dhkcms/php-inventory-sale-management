<?php
class Transport extends CI_Model
{
	public function get_info($transport_id)
	{
		// NOTE: temporary tables are created to speed up searches due to the fact that are ortogonal to the main query
		// create a temporary table to contain all the payments per transport item
		$this->db->query('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $this->db->dbprefix('transports_payments_temp') . 
			'(
				SELECT payments.transport_id AS transport_id, 
					IFNULL(SUM(payments.payment_amount), 0) AS transport_payment_amount,
					GROUP_CONCAT(CONCAT(payments.payment_type, " ", payments.payment_amount) SEPARATOR ", ") AS payment_type
				FROM ' . $this->db->dbprefix('transports_payments') . ' AS payments
				INNER JOIN ' . $this->db->dbprefix('transports') . ' AS transports
					ON transports.transport_id = payments.transport_id
				WHERE transports.transport_id = ' . $this->db->escape($transport_id) . '
				GROUP BY transport_id
			)'
		);

		// create a temporary table to contain all the sum of taxes per transport item
		$this->db->query('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $this->db->dbprefix('transports_items_taxes_temp') . 
			'(
				SELECT transports_items_taxes.transport_id AS transport_id,
					transports_items_taxes.item_id AS item_id,
					SUM(transports_items_taxes.percent) AS percent
				FROM ' . $this->db->dbprefix('transports_items_taxes') . ' AS transports_items_taxes
				INNER JOIN ' . $this->db->dbprefix('transports') . ' AS transports
					ON transports.transport_id = transports_items_taxes.transport_id
				INNER JOIN ' . $this->db->dbprefix('transports_items') . ' AS transports_items
					ON transports_items.transport_id = transports_items_taxes.transport_id AND transports_items.line = transports_items_taxes.line
				WHERE transports.transport_id = ' . $this->db->escape($transport_id) . '
				GROUP BY transports_items_taxes.transport_id, transports_items_taxes.item_id
			)'
		);

		if($this->config->item('tax_included'))
		{
			$transport_total = 'SUM(transports_items.item_unit_price * transports_items.quantity_purchased * (1 - transports_items.discount_percent / 100))';
			$transport_subtotal = 'SUM(transports_items.item_unit_price * transports_items.quantity_purchased * (1 - transports_items.discount_percent / 100) * (100 / (100 + transports_items_taxes.percent)))';
			$transport_tax = 'SUM(transports_items.item_unit_price * transports_items.quantity_purchased * (1 - transports_items.discount_percent / 100) * (1 - 100 / (100 + transports_items_taxes.percent)))';
		}
		else
		{
			$transport_total = 'SUM(transports_items.item_unit_price * transports_items.quantity_purchased * (1 - transports_items.discount_percent / 100) * (1 + (transports_items_taxes.percent / 100)))';
			$transport_subtotal = 'SUM(transports_items.item_unit_price * transports_items.quantity_purchased * (1 - transports_items.discount_percent / 100))';
			$transport_tax = 'SUM(transports_items.item_unit_price * transports_items.quantity_purchased * (1 - transports_items.discount_percent / 100) * (transports_items_taxes.percent / 100))';
		}

		$decimals = totals_decimals();

		$this->db->select('
				transports.transport_id AS transport_id,
				DATE(transports.transport_time) AS transport_date,
				transports.transport_time AS transport_time,
				transports.last_edit_time AS last_edit_time,
				transports.comment AS comment,
				transports.invoice_number AS invoice_number,
				transports.employee_id AS employee_id,
				transports.stock_location_id AS stock_location_id,
				stock_location.location_name AS stock_location_name,
				stock_location.location_name AS first_name,
				stock_location_p.last_name AS last_name,
				stock_location_p.email AS email,
				stock_location_p.comments AS comments,
				' . "
				IFNULL(ROUND($transport_total, $decimals), ROUND($transport_subtotal, $decimals)) AS amount_due,
				payments.transport_payment_amount AS amount_tendered,
				(payments.transport_payment_amount - IFNULL(ROUND($transport_total, $decimals), ROUND($transport_subtotal, $decimals))) AS change_due,
				" . '
				payments.payment_type AS payment_type
		');

		$this->db->from('transports_items AS transports_items');
		$this->db->join('transports AS transports', 'transports_items.transport_id = transports.transport_id', 'inner');
		// AS stock_location_p', 'transports.stock_location_id = stock_location_p.person_id', 'left');
		$this->db->join('stock_locations AS stock_location', 'transports.stock_location_id = stock_location.location_id', 'left');
		$this->db->join('transports_payments_temp AS payments', 'transports.transport_id = payments.transport_id', 'left outer');
		$this->db->join('transports_items_taxes_temp AS transports_items_taxes', 'transports_items.transport_id = transports_items_taxes.transport_id AND transports_items.item_id = transports_items_taxes.item_id', 'left outer');

		$this->db->where('transports.transport_id', $transport_id);

		$this->db->group_by('transports.transport_id');
		$this->db->order_by('transports.transport_time', 'asc');

		return $this->db->get();
	}

	/*
	 Get number of rows for the takings (transports/manage) view
	*/
	public function get_found_rows($search, $filters)
	{
		return $this->search($search, $filters)->num_rows();
	}

	/*
	 Get the transports data for the takings (transports/manage) view
	*/
	public function search($search, $filters, $rows = 0, $limit_from = 0, $sort = 'transport_date', $order = 'desc')
	{
		// NOTE: temporary tables are created to speed up searches due to the fact that are ortogonal to the main query
		// create a temporary table to contain all the payments per transport item
		$this->db->query('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $this->db->dbprefix('transports_payments_temp') . 
			' (PRIMARY KEY(transport_id), INDEX(transport_id))
			(
				SELECT payments.transport_id AS transport_id, 
					IFNULL(SUM(payments.payment_amount), 0) AS transport_payment_amount,
					GROUP_CONCAT(CONCAT(payments.payment_type, " ", payments.payment_amount) SEPARATOR ", ") AS payment_type
				FROM ' . $this->db->dbprefix('transports_payments') . ' AS payments
				INNER JOIN ' . $this->db->dbprefix('transports') . ' AS transports
					ON transports.transport_id = payments.transport_id
				WHERE DATE(transports.transport_time) BETWEEN ' . $this->db->escape($filters['start_date']) . ' AND ' . $this->db->escape($filters['end_date']) . '
				GROUP BY transport_id
			)'
		);

		// create a temporary table to contain all the sum of taxes per transport item
		$this->db->query('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $this->db->dbprefix('transports_items_taxes_temp') . 
			' (INDEX(transport_id), INDEX(item_id))
			(
				SELECT transports_items_taxes.transport_id AS transport_id,
					transports_items_taxes.item_id AS item_id,
					SUM(transports_items_taxes.percent) AS percent
				FROM ' . $this->db->dbprefix('transports_items_taxes') . ' AS transports_items_taxes
				INNER JOIN ' . $this->db->dbprefix('transports') . ' AS transports
					ON transports.transport_id = transports_items_taxes.transport_id
				INNER JOIN ' . $this->db->dbprefix('transports_items') . ' AS transports_items
					ON transports_items.transport_id = transports_items_taxes.transport_id AND transports_items.line = transports_items_taxes.line
				WHERE DATE(transports.transport_time) BETWEEN ' . $this->db->escape($filters['start_date']) . ' AND ' . $this->db->escape($filters['end_date']) . '
				GROUP BY transports_items_taxes.transport_id, transports_items_taxes.item_id
			)'
		);

		if($this->config->item('tax_included'))
		{
			$transport_total = 'SUM(transports_items.item_unit_price * transports_items.quantity_purchased * (1 - transports_items.discount_percent / 100))';
			$transport_subtotal = 'SUM(transports_items.item_unit_price * transports_items.quantity_purchased * (1 - transports_items.discount_percent / 100) * (100 / (100 + transports_items_taxes.percent)))';
			$transport_tax = 'SUM(transports_items.item_unit_price * transports_items.quantity_purchased * (1 - transports_items.discount_percent / 100) * (1 - 100 / (100 + transports_items_taxes.percent)))';
		}
		else
		{
			$transport_total = 'SUM(transports_items.item_unit_price * transports_items.quantity_purchased * (1 - transports_items.discount_percent / 100) * (1 + (transports_items_taxes.percent / 100)))';
			$transport_subtotal = 'SUM(transports_items.item_unit_price * transports_items.quantity_purchased * (1 - transports_items.discount_percent / 100))';
			$transport_tax = 'SUM(transports_items.item_unit_price * transports_items.quantity_purchased * (1 - transports_items.discount_percent / 100) * (transports_items_taxes.percent / 100))';
		}

		$transport_cost = 'SUM(transports_items.item_cost_price * transports_items.quantity_purchased)';

		$decimals = totals_decimals();

		$this->db->select('
				transports.transport_id AS transport_id,
				DATE(transports.transport_time) AS transport_date,
				transports.transport_time AS transport_time,
				transports.invoice_number AS invoice_number,
				SUM(transports_items.quantity_purchased) AS items_purchased,
				stock_location.location_name AS stock_location_name,
				stock_location.company_name AS company_name,
				' . "
				ROUND($transport_subtotal, $decimals) AS subtotal,
				IFNULL(ROUND($transport_tax, $decimals), 0) AS tax,
				IFNULL(ROUND($transport_total, $decimals), ROUND($transport_subtotal, $decimals)) AS total,
				ROUND($transport_cost, $decimals) AS cost,
				ROUND($transport_total - IFNULL($transport_tax, 0) - $transport_cost, $decimals) AS profit,
				IFNULL(ROUND($transport_total, $decimals), ROUND($transport_subtotal, $decimals)) AS amount_due,
				payments.transport_payment_amount AS amount_tendered,
				(payments.transport_payment_amount - IFNULL(ROUND($transport_total, $decimals), ROUND($transport_subtotal, $decimals))) AS change_due,
				" . '
				payments.payment_type AS payment_type
		');

		$this->db->from('transports_items AS transports_items');
		$this->db->join('transports AS transports', 'transports_items.transport_id = transports.transport_id', 'inner');
		// AS stock_location_p', 'transports.stock_location_id = stock_location_p.person_id', 'left');
		$this->db->join('stock_locations AS stock_location', 'transports.stock_location_id = stock_location.location_id', 'left');
		$this->db->join('transports_payments_temp AS payments', 'transports.transport_id = payments.transport_id', 'left outer');
		$this->db->join('transports_items_taxes_temp AS transports_items_taxes', 'transports_items.transport_id = transports_items_taxes.transport_id AND transports_items.item_id = transports_items_taxes.item_id', 'left outer');

		$this->db->where('DATE(transports.transport_time) BETWEEN ' . $this->db->escape($filters['start_date']) . ' AND ' . $this->db->escape($filters['end_date']));

		if(!empty($search))
		{
			if($filters['is_valid_receipt'] != FALSE)
			{
				$pieces = explode(' ', $search);
				$this->db->where('transports.transport_id', $pieces[1]);
			}
			else
			{			
				$this->db->group_start();
					$this->db->like('stock_location.location_name', $search);
					/*
					$this->db->like('stock_location_p.last_name', $search);
					// stock_location first name
					$this->db->or_like('stock_location.location_name', $search);
					// stock_location first and last name
					$this->db->or_like('CONCAT(stock_location.location_name, " ", stock_location_p.last_name)', $search);
					// stock_location company name
					$this->db->or_like('stock_location.*/
				$this->db->group_end();
			}
		}

		if($filters['location_id'] != 'all')
		{
			$this->db->where('transports_items.item_location', $filters['location_id']);
		}

		if($filters['transport_type'] == 'transports')
        {
            $this->db->where('transports_items.quantity_purchased > 0');
        }
        elseif($filters['transport_type'] == 'returns')
        {
            $this->db->where('transports_items.quantity_purchased < 0');
        }

		if($filters['only_invoices'] != FALSE)
		{
			$this->db->where('transports.invoice_number IS NOT NULL');
		}

		if($filters['only_cash'] != FALSE)
		{
			$this->db->group_start();
				$this->db->like('payments.payment_type', $this->lang->line('transports_cash'), 'after');
				$this->db->or_where('payments.payment_type IS NULL');
			$this->db->group_end();
		}

		$this->db->group_by('transports.transport_id');
		$this->db->order_by($sort, $order);

		if($rows > 0)
		{
			$this->db->limit($rows, $limit_from);
		}

		return $this->db->get();
	}

	/*
	 Get the payment summary for the takings (transports/manage) view
	*/
	public function get_payments_summary($search, $filters)
	{
		// get payment summary
		$this->db->select('payment_type, count(*) AS count, SUM(payment_amount) AS payment_amount');
		$this->db->from('transports');
		$this->db->join('transports_payments', 'transports_payments.transport_id = transports.transport_id');
		// AS stock_location_p', 'transports.stock_location_id = stock_location_p.person_id', 'left');
		$this->db->join('stock_locations AS stock_location', 'transports.stock_location_id = stock_location.location_id', 'left');

		$this->db->where('DATE(transport_time) BETWEEN ' . $this->db->escape($filters['start_date']) . ' AND ' . $this->db->escape($filters['end_date']));

		if(!empty($search))
		{
			if($filters['is_valid_receipt'] != FALSE)
			{
				$pieces = explode(' ',$search);
				$this->db->where('transports.transport_id', $pieces[1]);
			}
			else
			{
				$this->db->group_start();
					$this->db->like('stock_location.location_name', $search);
					/*
					$this->db->like('stock_location_p.last_name', $search);
					// stock_location first name
					$this->db->or_like('stock_location.location_name', $search);
					// stock_location first and last name
					$this->db->or_like('CONCAT(stock_location.location_name, " ", stock_location_p.last_name)', $search);
					// stock_location company name
					$this->db->or_like('stock_location.*/
				$this->db->group_end();
			}
		}

		if($filters['transport_type'] == 'transports')
		{
			$this->db->where('payment_amount > 0');
		}
		elseif($filters['transport_type'] == 'returns')
		{
			$this->db->where('payment_amount < 0');
		}

		if($filters['only_invoices'] != FALSE)
		{
			$this->db->where('invoice_number IS NOT NULL');
		}
		
		if($filters['only_cash'] != FALSE)
		{
			$this->db->like('payment_type', $this->lang->line('transports_cash'), 'after');
		}

		$this->db->group_by('payment_type');

		$payments = $this->db->get()->result_array();

		// consider Gift Card as only one type of payment and do not show "Gift Card: 1, Gift Card: 2, etc." in the total
		$gift_card_count = 0;
		$gift_card_amount = 0;
		foreach($payments as $key=>$payment)
		{
			if( strstr($payment['payment_type'], $this->lang->line('transports_giftcard')) != FALSE )
			{
				$gift_card_count  += $payment['count'];
				$gift_card_amount += $payment['payment_amount'];

				// remove the "Gift Card: 1", "Gift Card: 2", etc. payment string
				unset($payments[$key]);
			}
		}

		if($gift_card_count > 0)
		{
			$payments[] = array('payment_type' => $this->lang->line('transports_giftcard'), 'count' => $gift_card_count, 'payment_amount' => $gift_card_amount);
		}

		return $payments;
	}

	/*
	Gets total of rows
	*/
	public function get_total_rows()
	{
		$this->db->from('transports');

		return $this->db->count_all_results();
	}

	public function get_search_suggestions($search, $limit = 25)
	{
		$suggestions = array();

		if(!$this->is_valid_receipt($search))
		{
			$this->db->distinct();
			$this->db->select('first_name, last_name');
			$this->db->from('transports');
			//', 'people.person_id = transports.stock_location_id');
			/*
			$this->db->or_like('first_name', $search);
			$this->db->or_like('CONCAT(first_name, " ", last_name)', $search);
			$this->db->or_like('*/
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
		$this->db->from('transports');
		$this->db->where('invoice_number IS NOT NULL');

		return $this->db->count_all_results();
	}

	public function get_transport_by_invoice_number($invoice_number)
	{
		$this->db->from('transports');
		$this->db->where('invoice_number', $invoice_number);

		return $this->db->get();
	}

	public function get_invoice_number_for_year($year = '', $start_from = 0) 
	{
		$year = $year == '' ? date('Y') : $year;
		$this->db->select('COUNT( 1 ) AS invoice_number_year');
		$this->db->from('transports');
		$this->db->where('DATE_FORMAT(transport_time, "%Y" ) = ', $year);
		$this->db->where('invoice_number IS NOT NULL');
		$result = $this->db->get()->row_array();

		return ($start_from + $result['invoice_number_year']);
	}
	
	public function is_valid_receipt(&$receipt_transport_id)
	{
		if(!empty($receipt_transport_id))
		{
			//POS #
			$pieces = explode(' ', $receipt_transport_id);

			if(count($pieces) == 2 && preg_match('/(POS)/', $pieces[0]))
			{
				return $this->exists($pieces[1]);
			}
			elseif($this->config->item('invoice_enable') == TRUE)
			{
				$transport_info = $this->get_transport_by_invoice_number($receipt_transport_id);
				if($transport_info->num_rows() > 0)
				{
					$receipt_transport_id = 'POS ' . $transport_info->row()->transport_id;

					return TRUE;
				}
			}
		}

		return FALSE;
	}

	public function exists($transport_id)
	{
		$this->db->from('transports');
		$this->db->where('transport_id', $transport_id);

		return ($this->db->get()->num_rows()==1);
	}

	public function update($transport_id, $transport_data, $payments)
	{
		$this->db->where('transport_id', $transport_id);
		$success = $this->db->update('transports', $transport_data);

		// touch payment only if update transport is successful and there is a payments object otherwise the result would be to delete all the payments associated to the transport
		if($success && !empty($payments))
		{
			//Run these queries as a transaction, we want to make sure we do all or nothing
			$this->db->trans_start();
			
			// first delete all payments
			$this->db->delete('transports_payments', array('transport_id' => $transport_id));

			// add new payments
			foreach($payments as $payment)
			{
				$transports_payments_data = array(
					'transport_id' => $transport_id,
					'payment_type' => $payment['payment_type'],
					'payment_amount' => $payment['payment_amount']
				);

				$success = $this->db->insert('transports_payments', $transports_payments_data);
			}
			
			$this->db->trans_complete();
			
			$success &= $this->db->trans_status();
		}
		
		return $success;
	}

	public function save($items, $stock_location_id, $employee_id, $comment, $invoice_number, $payments, $transport_id = FALSE)
	{
		if(count($items) == 0)
		{
			return -1;
		}

		$transports_data = array(
			'transport_time'		 => date('Y-m-d H:i:s'),
			'stock_location_id'	 => $this->Stock_location->exists($stock_location_id) ? $stock_location_id : null,
			'employee_id'	 => $employee_id,
			'comment'		 => $comment,
			'invoice_number' => $invoice_number
		);

		// Run these queries as a transaction, we want to make sure we do all or nothing
		$this->db->trans_start();

		$this->db->insert('transports', $transports_data);
		$transport_id = $this->db->insert_id();

		foreach($payments as $payment_id=>$payment)
		{
			if( substr( $payment['payment_type'], 0, strlen( $this->lang->line('transports_giftcard') ) ) == $this->lang->line('transports_giftcard') )
			{
				// We have a gift card and we have to deduct the used value from the total value of the card.
				$splitpayment = explode( ':', $payment['payment_type'] );
				$cur_giftcard_value = $this->Giftcard->get_giftcard_value( $splitpayment[1] );
				$this->Giftcard->update_giftcard_value( $splitpayment[1], $cur_giftcard_value - $payment['payment_amount'] );
			}

			$transports_payments_data = array(
				'transport_id'		 => $transport_id,
				'payment_type'	 => $payment['payment_type'],
				'payment_amount' => $payment['payment_amount']
			);
			$this->db->insert('transports_payments', $transports_payments_data);
		}

		foreach($items as $line=>$item)
		{
			$cur_item_info = $this->Item->get_info($item['item_id']);

			$transports_items_data = array(
				'transport_id'			=> $transport_id,
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

			$this->db->insert('transports_items', $transports_items_data);

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
			$transport_remarks = 'POS '.$transport_id;
			$inv_data = array(
				'trans_date'		=> date('Y-m-d H:i:s'),
				'trans_items'		=> $item['item_id'],
				'trans_user'		=> $employee_id,
				'trans_location'	=> $item['item_location'],
				'trans_comment'		=> $transport_remarks,
				'trans_inventory'	=> -$item['quantity']
			);
			$this->Inventory->insert($inv_data);

			$stock_location = $this->Stock_location->get_info($stock_location_id);
 			if($stock_location_id == -1 || $stock_location->taxable)
 			{
				foreach($this->Item_taxes->get_info($item['item_id']) as $row)
				{
					$this->db->insert('transports_items_taxes', array(
						'transport_id' 	=> $transport_id,
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
		
		return $transport_id;
	}

	public function delete_list($transport_ids, $employee_id, $update_inventory = TRUE) 
	{
		$result = TRUE;

		foreach($transport_ids as $transport_id)
		{
			$result &= $this->delete($transport_id, $employee_id, $update_inventory);
		}

		return $result;
	}

	public function delete($transport_id, $employee_id, $update_inventory = TRUE) 
	{
		// start a transaction to assure data integrity
		$this->db->trans_start();

		// first delete all payments
		$this->db->delete('transports_payments', array('transport_id' => $transport_id));
		// then delete all taxes on items
		$this->db->delete('transports_items_taxes', array('transport_id' => $transport_id));

		if($update_inventory)
		{
			// defect, not all item deletions will be undone??
			// get array with all the items involved in the transport to update the inventory tracking
			$items = $this->get_transport_items($transport_id)->result_array();
			foreach($items as $item)
			{
				// create query to update inventory tracking
				$inv_data = array(
					'trans_date'      => date('Y-m-d H:i:s'),
					'trans_items'     => $item['item_id'],
					'trans_user'      => $employee_id,
					'trans_comment'   => 'Deleting transport ' . $transport_id,
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
		$this->db->delete('transports_items', array('transport_id' => $transport_id));
		// delete transport itself
		$this->db->delete('transports', array('transport_id' => $transport_id));

		// execute transaction
		$this->db->trans_complete();
	
		return $this->db->trans_status();
	}

	public function get_transport_items($transport_id)
	{
		$this->db->from('transports_items');
		$this->db->where('transport_id', $transport_id);

		return $this->db->get();
	}

	public function get_transport_payments($transport_id)
	{
		$this->db->from('transports_payments');
		$this->db->where('transport_id', $transport_id);

		return $this->db->get();
	}

	public function get_payment_options($giftcard = TRUE)
	{
		$payments = array();

		$options=array('现金','微信','银行转账','支付宝','其他');
		foreach ($options as $value) {$payments[$value]=$value;}
		
		/*if($this->config->item('payment_options_order') == 'debitcreditcash')
		{
			$payments[$this->lang->line('transports_debit')] = $this->lang->line('transports_debit');
			$payments[$this->lang->line('transports_credit')] = $this->lang->line('transports_credit');
			$payments[$this->lang->line('transports_cash')] = $this->lang->line('transports_cash');
		}
		elseif($this->config->item('payment_options_order') == 'debitcashcredit')
		{
			$payments[$this->lang->line('transports_debit')] = $this->lang->line('transports_debit');
			$payments[$this->lang->line('transports_cash')] = $this->lang->line('transports_cash');
			$payments[$this->lang->line('transports_credit')] = $this->lang->line('transports_credit');
		}
		else // default: if($this->config->item('payment_options_order') == 'cashdebitcredit')
		{
			$payments[$this->lang->line('transports_cash')] = $this->lang->line('transports_cash');
			$payments[$this->lang->line('transports_debit')] = $this->lang->line('transports_debit');
			$payments[$this->lang->line('transports_credit')] = $this->lang->line('transports_credit');
		}

		$payments[$this->lang->line('transports_check')] = $this->lang->line('transports_check');

		if($giftcard)
		{
			$payments[$this->lang->line('transports_giftcard')] = $this->lang->line('transports_giftcard');
		}*/

		return $payments;
	}

	public function get_stock_location($transport_id)
	{
		$this->db->from('transports');
		$this->db->where('transport_id', $transport_id);

		return $this->Stock_location->get_info($this->db->get()->row()->stock_location_id);
	}

	public function get_employee($transport_id)
	{
		$this->db->from('transports');
		$this->db->where('transport_id', $transport_id);

		return $this->Employee->get_info($this->db->get()->row()->employee_id);
	}

	public function check_invoice_number_exists($invoice_number, $transport_id = '')
	{
		$this->db->from('transports');
		$this->db->where('invoice_number', $invoice_number);
		if(!empty($transport_id))
		{
			$this->db->where('transport_id !=', $transport_id);
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

	//We create a temp table that allows us to do easy report/transports queries
	public function create_temp_table(array $inputs)
	{
		if($this->config->item('tax_included'))
		{
			$transport_total = '(transports_items.item_unit_price * transports_items.quantity_purchased * (1 - transports_items.discount_percent / 100))';
			$transport_subtotal = '(transports_items.item_unit_price * transports_items.quantity_purchased * (1 - transports_items.discount_percent / 100) * (100 / (100 + SUM(transports_items_taxes.percent))))';
			$transport_tax = '(transports_items.item_unit_price * transports_items.quantity_purchased * (1 - transports_items.discount_percent / 100) * (1 - 100 / (100 + SUM(transports_items_taxes.percent))))';
		}
		else
		{
			$transport_total = '(transports_items.item_unit_price * transports_items.quantity_purchased * (1 - transports_items.discount_percent / 100) * (1 + (SUM(transports_items_taxes.percent) / 100)))';
			$transport_subtotal = '(transports_items.item_unit_price * transports_items.quantity_purchased * (1 - transports_items.discount_percent / 100))';
			$transport_tax = '(transports_items.item_unit_price * transports_items.quantity_purchased * (1 - transports_items.discount_percent / 100) * (SUM(transports_items_taxes.percent) / 100))';
		}

		$transport_cost  = '(transports_items.item_cost_price * transports_items.quantity_purchased)';

		$decimals = totals_decimals();

		if(empty($inputs['transport_id']))
		{
			$where = 'WHERE DATE(transports.transport_time) BETWEEN ' . $this->db->escape($inputs['start_date']) . ' AND ' . $this->db->escape($inputs['end_date']);
		}
		else
		{
			$where = 'WHERE transports.transport_id = ' . $this->db->escape($inputs['transport_id']);
		}

		// create a temporary table to contain all the payment types and amount
		$this->db->query('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $this->db->dbprefix('transports_payments_temp') . 
			' (PRIMARY KEY(transport_id), INDEX(transport_id))
			(
				SELECT payments.transport_id AS transport_id, 
					IFNULL(SUM(payments.payment_amount), 0) AS transport_payment_amount,
					GROUP_CONCAT(CONCAT(payments.payment_type, " ", payments.payment_amount) SEPARATOR ", ") AS payment_type
				FROM ' . $this->db->dbprefix('transports_payments') . ' AS payments
				INNER JOIN ' . $this->db->dbprefix('transports') . ' AS transports
					ON transports.transport_id = payments.transport_id
				' . "
				$where
				" . '
				GROUP BY payments.transport_id
			)'
		);

		$this->db->query('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $this->db->dbprefix('transports_items_temp') . 
			' (INDEX(transport_date), INDEX(transport_id))
			(
				SELECT
					DATE(transports.transport_time) AS transport_date,
					transports.transport_time,
					transports.transport_id,
					transports.comment,
					transports.invoice_number,
					transports.stock_location_id,
					stock_location.location_name AS stock_location_name,
					stock_location.location_name AS stock_location_first_name,
					stock_location_p.last_name AS stock_location_last_name,
					stock_location_p.email AS stock_location_email,
					stock_location_p.comments AS stock_location_comments, 
					stock_location.company_name AS stock_location_company_name,
					transports.employee_id,
					CONCAT(employee.first_name, " ", employee.last_name) AS employee_name,
					items.item_id,
					items.name,
					items.category,
					items.supplier_id,
					transports_items.quantity_purchased,
					transports_items.item_cost_price,
					transports_items.item_unit_price,
					transports_items.discount_percent,
					transports_items.line,
					transports_items.serialnumber,
					transports_items.item_location,
					transports_items.description,
					payments.payment_type,
					payments.transport_payment_amount,
					IFNULL(SUM(transports_items_taxes.percent), 0) AS item_tax_percent,
					' . "
					ROUND($transport_subtotal, $decimals) AS subtotal,
					IFNULL(ROUND($transport_tax, $decimals), 0) AS tax,
					IFNULL(ROUND($transport_total, $decimals), ROUND($transport_subtotal, $decimals)) AS total,
					ROUND($transport_cost, $decimals) AS cost,
					ROUND($transport_total - IFNULL($transport_tax, 0) - $transport_cost, $decimals) AS profit
					" . '
				FROM ' . $this->db->dbprefix('transports_items') . ' AS transports_items
				INNER JOIN ' . $this->db->dbprefix('transports') . ' AS transports
					ON transports_items.transport_id = transports.transport_id
				INNER JOIN ' . $this->db->dbprefix('items') . ' AS items
					ON transports_items.item_id = items.item_id
				LEFT OUTER JOIN ' . $this->db->dbprefix('transports_payments_temp') . ' AS payments
					ON transports_items.transport_id = payments.transport_id		
				LEFT OUTER JOIN ' . $this->db->dbprefix('suppliers') . ' AS supplier
					ON items.supplier_id = supplier.person_id
				LEFT OUTER JOIN ' . $this->db->dbprefix('people') . ' AS stock_location_p
					ON transports.stock_location_id = stock_location_p.person_id
				LEFT OUTER JOIN ' . $this->db->dbprefix('stock_locations') . ' AS stock_location
					ON transports.stock_location_id = stock_location.person_id
				LEFT OUTER JOIN ' . $this->db->dbprefix('people') . ' AS employee
					ON transports.employee_id = employee.person_id
				LEFT OUTER JOIN ' . $this->db->dbprefix('transports_items_taxes') . ' AS transports_items_taxes
					ON transports_items.transport_id = transports_items_taxes.transport_id AND transports_items.item_id = transports_items_taxes.item_id AND transports_items.line = transports_items_taxes.line
				' . "
				$where
				" . '
				GROUP BY transports.transport_id, items.item_id, transports_items.line
			)'
		);

		// drop the temporary table to contain memory consumption as it's no longer required
		$this->db->query('DROP TEMPORARY TABLE IF EXISTS ' . $this->db->dbprefix('transports_payments_temp'));
	}
}
?>
