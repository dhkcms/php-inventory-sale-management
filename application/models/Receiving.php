<?php
class Receiving extends CI_Model
{
	public function get_info($receiving_id)
	{
		// NOTE: temporary tables are created to speed up searches due to the fact that are ortogonal to the main query
		// create a temporary table to contain all the payments per receiving item
		$this->db->query('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $this->db->dbprefix('receivings_payments_temp') . 
			'(
				SELECT payments.receiving_id AS receiving_id, 
					IFNULL(SUM(payments.payment_amount), 0) AS receiving_payment_amount,
					GROUP_CONCAT(CONCAT(payments.payment_type, " ", payments.payment_amount) SEPARATOR ", ") AS payment_type
				FROM ' . $this->db->dbprefix('receivings_payments') . ' AS payments
				INNER JOIN ' . $this->db->dbprefix('receivings') . ' AS receivings
					ON receivings.receiving_id = payments.receiving_id
				WHERE receivings.receiving_id = ' . $this->db->escape($receiving_id) . '
				GROUP BY receiving_id
			)'
		);

		// create a temporary table to contain all the sum of taxes per receiving item
		$this->db->query('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $this->db->dbprefix('receivings_items_taxes_temp') . 
			'(
				SELECT receivings_items_taxes.receiving_id AS receiving_id,
					receivings_items_taxes.item_id AS item_id,
					SUM(receivings_items_taxes.percent) AS percent
				FROM ' . $this->db->dbprefix('receivings_items_taxes') . ' AS receivings_items_taxes
				INNER JOIN ' . $this->db->dbprefix('receivings') . ' AS receivings
					ON receivings.receiving_id = receivings_items_taxes.receiving_id
				INNER JOIN ' . $this->db->dbprefix('receivings_items') . ' AS receivings_items
					ON receivings_items.receiving_id = receivings_items_taxes.receiving_id AND receivings_items.line = receivings_items_taxes.line
				WHERE receivings.receiving_id = ' . $this->db->escape($receiving_id) . '
				GROUP BY receivings_items_taxes.receiving_id, receivings_items_taxes.item_id
			)'
		);

		if($this->config->item('tax_included'))
		{
			$receiving_total = 'SUM(receivings_items.item_unit_price * receivings_items.quantity_purchased * (1 - receivings_items.discount_percent / 100))';
			$receiving_subtotal = 'SUM(receivings_items.item_unit_price * receivings_items.quantity_purchased * (1 - receivings_items.discount_percent / 100) * (100 / (100 + receivings_items_taxes.percent)))';
			$receiving_tax = 'SUM(receivings_items.item_unit_price * receivings_items.quantity_purchased * (1 - receivings_items.discount_percent / 100) * (1 - 100 / (100 + receivings_items_taxes.percent)))';
		}
		else
		{
			$receiving_total = 'SUM(receivings_items.item_unit_price * receivings_items.quantity_purchased * (1 - receivings_items.discount_percent / 100) * (1 + (receivings_items_taxes.percent / 100)))';
			$receiving_subtotal = 'SUM(receivings_items.item_unit_price * receivings_items.quantity_purchased * (1 - receivings_items.discount_percent / 100))';
			$receiving_tax = 'SUM(receivings_items.item_unit_price * receivings_items.quantity_purchased * (1 - receivings_items.discount_percent / 100) * (receivings_items_taxes.percent / 100))';
		}

		$decimals = totals_decimals();

		$this->db->select('
				receivings.receiving_id AS receiving_id,
				DATE(receivings.receiving_time) AS receiving_date,
				receivings.receiving_time AS receiving_time,
				receivings.last_edit_time AS last_edit_time,
				receivings.comment AS comment,
				receivings.invoice_number AS invoice_number,
				receivings.employee_id AS employee_id,
				receivings.supplier_id AS supplier_id,
				supplier_p.first_name AS supplier_name,
				supplier_p.first_name AS first_name,
				supplier_p.last_name AS last_name,
				supplier_p.email AS email,
				supplier_p.comments AS comments,
				' . "
				IFNULL(ROUND($receiving_total, $decimals), ROUND($receiving_subtotal, $decimals)) AS amount_due,
				payments.receiving_payment_amount AS amount_tendered,
				(payments.receiving_payment_amount - IFNULL(ROUND($receiving_total, $decimals), ROUND($receiving_subtotal, $decimals))) AS change_due,
				" . '
				payments.payment_type AS payment_type
		');

		$this->db->from('receivings_items AS receivings_items');
		$this->db->join('receivings AS receivings', 'receivings_items.receiving_id = receivings.receiving_id', 'inner');
		$this->db->join('people AS supplier_p', 'receivings.supplier_id = supplier_p.person_id', 'left');
		$this->db->join('suppliers AS supplier', 'receivings.supplier_id = supplier.person_id', 'left');
		$this->db->join('receivings_payments_temp AS payments', 'receivings.receiving_id = payments.receiving_id', 'left outer');
		$this->db->join('receivings_items_taxes_temp AS receivings_items_taxes', 'receivings_items.receiving_id = receivings_items_taxes.receiving_id AND receivings_items.item_id = receivings_items_taxes.item_id', 'left outer');

		$this->db->where('receivings.receiving_id', $receiving_id);

		$this->db->group_by('receivings.receiving_id');
		$this->db->order_by('receivings.receiving_time', 'asc');

		return $this->db->get();
	}

	/*
	 Get number of rows for the takings (receivings/manage) view
	*/
	public function get_found_rows($search, $filters)
	{
		return $this->search($search, $filters)->num_rows();
	}

	/*
	 Get the receivings data for the takings (receivings/manage) view
	*/
	public function search($search, $filters, $rows = 0, $limit_from = 0, $sort = 'receiving_date', $order = 'desc')
	{
		// NOTE: temporary tables are created to speed up searches due to the fact that are ortogonal to the main query
		// create a temporary table to contain all the payments per receiving item
		$this->db->query('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $this->db->dbprefix('receivings_payments_temp') . 
			' (PRIMARY KEY(receiving_id), INDEX(receiving_id))
			(
				SELECT payments.receiving_id AS receiving_id, 
					IFNULL(SUM(payments.payment_amount), 0) AS receiving_payment_amount,
					GROUP_CONCAT(CONCAT(payments.payment_type, " ", payments.payment_amount) SEPARATOR ", ") AS payment_type
				FROM ' . $this->db->dbprefix('receivings_payments') . ' AS payments
				INNER JOIN ' . $this->db->dbprefix('receivings') . ' AS receivings
					ON receivings.receiving_id = payments.receiving_id
				WHERE DATE(receivings.receiving_time) BETWEEN ' . $this->db->escape($filters['start_date']) . ' AND ' . $this->db->escape($filters['end_date']) . '
				GROUP BY receiving_id
			)'
		);

		// create a temporary table to contain all the sum of taxes per receiving item
		$this->db->query('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $this->db->dbprefix('receivings_items_taxes_temp') . 
			' (INDEX(receiving_id), INDEX(item_id))
			(
				SELECT receivings_items_taxes.receiving_id AS receiving_id,
					receivings_items_taxes.item_id AS item_id,
					SUM(receivings_items_taxes.percent) AS percent
				FROM ' . $this->db->dbprefix('receivings_items_taxes') . ' AS receivings_items_taxes
				INNER JOIN ' . $this->db->dbprefix('receivings') . ' AS receivings
					ON receivings.receiving_id = receivings_items_taxes.receiving_id
				INNER JOIN ' . $this->db->dbprefix('receivings_items') . ' AS receivings_items
					ON receivings_items.receiving_id = receivings_items_taxes.receiving_id AND receivings_items.line = receivings_items_taxes.line
				WHERE DATE(receivings.receiving_time) BETWEEN ' . $this->db->escape($filters['start_date']) . ' AND ' . $this->db->escape($filters['end_date']) . '
				GROUP BY receivings_items_taxes.receiving_id, receivings_items_taxes.item_id
			)'
		);

		if($this->config->item('tax_included'))
		{
			$receiving_total = 'SUM(receivings_items.item_unit_price * receivings_items.quantity_purchased * (1 - receivings_items.discount_percent / 100))';
			$receiving_subtotal = 'SUM(receivings_items.item_unit_price * receivings_items.quantity_purchased * (1 - receivings_items.discount_percent / 100) * (100 / (100 + receivings_items_taxes.percent)))';
			$receiving_tax = 'SUM(receivings_items.item_unit_price * receivings_items.quantity_purchased * (1 - receivings_items.discount_percent / 100) * (1 - 100 / (100 + receivings_items_taxes.percent)))';
		}
		else
		{
			$receiving_total = 'SUM(receivings_items.item_unit_price * receivings_items.quantity_purchased * (1 - receivings_items.discount_percent / 100) * (1 + (receivings_items_taxes.percent / 100)))';
			$receiving_subtotal = 'SUM(receivings_items.item_unit_price * receivings_items.quantity_purchased * (1 - receivings_items.discount_percent / 100))';
			$receiving_tax = 'SUM(receivings_items.item_unit_price * receivings_items.quantity_purchased * (1 - receivings_items.discount_percent / 100) * (receivings_items_taxes.percent / 100))';
		}

		$receiving_cost = 'SUM(receivings_items.item_cost_price * receivings_items.quantity_purchased)';

		$decimals = totals_decimals();

		$this->db->select('
				receivings.receiving_id AS receiving_id,
				DATE(receivings.receiving_time) AS receiving_date,
				receivings.receiving_time AS receiving_time,
				receivings.invoice_number AS invoice_number,
				SUM(receivings_items.quantity_purchased) AS items_purchased,
				supplier_p.first_name AS supplier_name,
				supplier.company_name AS company_name,
				' . "
				ROUND($receiving_subtotal, $decimals) AS subtotal,
				IFNULL(ROUND($receiving_tax, $decimals), 0) AS tax,
				IFNULL(ROUND($receiving_total, $decimals), ROUND($receiving_subtotal, $decimals)) AS total,
				ROUND($receiving_cost, $decimals) AS cost,
				ROUND($receiving_total - IFNULL($receiving_tax, 0) - $receiving_cost, $decimals) AS profit,
				IFNULL(ROUND($receiving_total, $decimals), ROUND($receiving_subtotal, $decimals)) AS amount_due,
				payments.receiving_payment_amount AS amount_tendered,
				(payments.receiving_payment_amount - IFNULL(ROUND($receiving_total, $decimals), ROUND($receiving_subtotal, $decimals))) AS change_due,
				" . '
				payments.payment_type AS payment_type
		');

		$this->db->from('receivings_items AS receivings_items');
		$this->db->join('receivings AS receivings', 'receivings_items.receiving_id = receivings.receiving_id', 'inner');
		$this->db->join('people AS supplier_p', 'receivings.supplier_id = supplier_p.person_id', 'left');
		$this->db->join('suppliers AS supplier', 'receivings.supplier_id = supplier.person_id', 'left');
		$this->db->join('receivings_payments_temp AS payments', 'receivings.receiving_id = payments.receiving_id', 'left outer');
		$this->db->join('receivings_items_taxes_temp AS receivings_items_taxes', 'receivings_items.receiving_id = receivings_items_taxes.receiving_id AND receivings_items.item_id = receivings_items_taxes.item_id', 'left outer');

		$this->db->where('DATE(receivings.receiving_time) BETWEEN ' . $this->db->escape($filters['start_date']) . ' AND ' . $this->db->escape($filters['end_date']));

		if(!empty($search))
		{
			if($filters['is_valid_receipt'] != FALSE)
			{
				$pieces = explode(' ', $search);
				$this->db->where('receivings.receiving_id', $pieces[1]);
			}
			else
			{			
				$this->db->group_start();
					// supplier last name
					$this->db->like('supplier_p.last_name', $search);
					// supplier first name
					$this->db->or_like('supplier_p.first_name', $search);
					// supplier first and last name
					$this->db->or_like('CONCAT(supplier_p.first_name, " ", supplier_p.last_name)', $search);
					// supplier company name
					$this->db->or_like('supplier.company_name', $search);
				$this->db->group_end();
			}
		}

		if($filters['location_id'] != 'all')
		{
			$this->db->where('receivings_items.item_location', $filters['location_id']);
		}

		if($filters['receiving_type'] == 'receivings')
        {
            $this->db->where('receivings_items.quantity_purchased > 0');
        }
        elseif($filters['receiving_type'] == 'returns')
        {
            $this->db->where('receivings_items.quantity_purchased < 0');
        }

		if($filters['only_invoices'] != FALSE)
		{
			$this->db->where('receivings.invoice_number IS NOT NULL');
		}

		if($filters['only_cash'] != FALSE)
		{
			$this->db->group_start();
				$this->db->like('payments.payment_type', $this->lang->line('receivings_cash'), 'after');
				$this->db->or_where('payments.payment_type IS NULL');
			$this->db->group_end();
		}

		$this->db->group_by('receivings.receiving_id');
		$this->db->order_by($sort, $order);

		if($rows > 0)
		{
			$this->db->limit($rows, $limit_from);
		}

		return $this->db->get();
	}

	/*
	 Get the payment summary for the takings (receivings/manage) view
	*/
	public function get_payments_summary($search, $filters)
	{
		// get payment summary
		$this->db->select('payment_type, count(*) AS count, SUM(payment_amount) AS payment_amount');
		$this->db->from('receivings');
		$this->db->join('receivings_payments', 'receivings_payments.receiving_id = receivings.receiving_id');
		$this->db->join('people AS supplier_p', 'receivings.supplier_id = supplier_p.person_id', 'left');
		$this->db->join('suppliers AS supplier', 'receivings.supplier_id = supplier.person_id', 'left');

		$this->db->where('DATE(receiving_time) BETWEEN ' . $this->db->escape($filters['start_date']) . ' AND ' . $this->db->escape($filters['end_date']));

		if(!empty($search))
		{
			if($filters['is_valid_receipt'] != FALSE)
			{
				$pieces = explode(' ',$search);
				$this->db->where('receivings.receiving_id', $pieces[1]);
			}
			else
			{
				$this->db->group_start();
					// supplier last name
					$this->db->like('supplier_p.last_name', $search);
					// supplier first name
					$this->db->or_like('supplier_p.first_name', $search);
					// supplier first and last name
					$this->db->or_like('CONCAT(supplier_p.first_name, " ", supplier_p.last_name)', $search);
					// supplier company name
					$this->db->or_like('supplier.company_name', $search);
				$this->db->group_end();
			}
		}

		if($filters['receiving_type'] == 'receivings')
		{
			$this->db->where('payment_amount > 0');
		}
		elseif($filters['receiving_type'] == 'returns')
		{
			$this->db->where('payment_amount < 0');
		}

		if($filters['only_invoices'] != FALSE)
		{
			$this->db->where('invoice_number IS NOT NULL');
		}
		
		if($filters['only_cash'] != FALSE)
		{
			$this->db->like('payment_type', $this->lang->line('receivings_cash'), 'after');
		}

		$this->db->group_by('payment_type');

		$payments = $this->db->get()->result_array();

		// consider Gift Card as only one type of payment and do not show "Gift Card: 1, Gift Card: 2, etc." in the total
		$gift_card_count = 0;
		$gift_card_amount = 0;
		foreach($payments as $key=>$payment)
		{
			if( strstr($payment['payment_type'], $this->lang->line('receivings_giftcard')) != FALSE )
			{
				$gift_card_count  += $payment['count'];
				$gift_card_amount += $payment['payment_amount'];

				// remove the "Gift Card: 1", "Gift Card: 2", etc. payment string
				unset($payments[$key]);
			}
		}

		if($gift_card_count > 0)
		{
			$payments[] = array('payment_type' => $this->lang->line('receivings_giftcard'), 'count' => $gift_card_count, 'payment_amount' => $gift_card_amount);
		}

		return $payments;
	}

	/*
	Gets total of rows
	*/
	public function get_total_rows()
	{
		$this->db->from('receivings');

		return $this->db->count_all_results();
	}

	public function get_search_suggestions($search, $limit = 25)
	{
		$suggestions = array();

		if(!$this->is_valid_receipt($search))
		{
			$this->db->distinct();
			$this->db->select('first_name, last_name');
			$this->db->from('receivings');
			$this->db->join('people', 'people.person_id = receivings.supplier_id');
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
		$this->db->from('receivings');
		$this->db->where('invoice_number IS NOT NULL');

		return $this->db->count_all_results();
	}

	public function get_receiving_by_invoice_number($invoice_number)
	{
		$this->db->from('receivings');
		$this->db->where('invoice_number', $invoice_number);

		return $this->db->get();
	}

	public function get_invoice_number_for_year($year = '', $start_from = 0) 
	{
		$year = $year == '' ? date('Y') : $year;
		$this->db->select('COUNT( 1 ) AS invoice_number_year');
		$this->db->from('receivings');
		$this->db->where('DATE_FORMAT(receiving_time, "%Y" ) = ', $year);
		$this->db->where('invoice_number IS NOT NULL');
		$result = $this->db->get()->row_array();

		return ($start_from + $result['invoice_number_year']);
	}
	
	public function is_valid_receipt(&$receipt_receiving_id)
	{
		if(!empty($receipt_receiving_id))
		{
			//POS #
			$pieces = explode(' ', $receipt_receiving_id);

			if(count($pieces) == 2 && preg_match('/(POS)/', $pieces[0]))
			{
				return $this->exists($pieces[1]);
			}
			elseif($this->config->item('invoice_enable') == TRUE)
			{
				$receiving_info = $this->get_receiving_by_invoice_number($receipt_receiving_id);
				if($receiving_info->num_rows() > 0)
				{
					$receipt_receiving_id = 'POS ' . $receiving_info->row()->receiving_id;

					return TRUE;
				}
			}
		}

		return FALSE;
	}

	public function exists($receiving_id)
	{
		$this->db->from('receivings');
		$this->db->where('receiving_id', $receiving_id);

		return ($this->db->get()->num_rows()==1);
	}

	public function update($receiving_id, $receiving_data, $payments)
	{
		$this->db->where('receiving_id', $receiving_id);
		$success = $this->db->update('receivings', $receiving_data);

		// touch payment only if update receiving is successful and there is a payments object otherwise the result would be to delete all the payments associated to the receiving
		if($success && !empty($payments))
		{
			//Run these queries as a transaction, we want to make sure we do all or nothing
			$this->db->trans_start();
			
			// first delete all payments
			$this->db->delete('receivings_payments', array('receiving_id' => $receiving_id));

			// add new payments
			foreach($payments as $payment)
			{
				$receivings_payments_data = array(
					'receiving_id' => $receiving_id,
					'payment_type' => $payment['payment_type'],
					'payment_amount' => $payment['payment_amount']
				);

				$success = $this->db->insert('receivings_payments', $receivings_payments_data);
			}
			
			$this->db->trans_complete();
			
			$success &= $this->db->trans_status();
		}
		
		return $success;
	}

	public function save($items, $supplier_id, $employee_id, $comment, $invoice_number, $payments, $receiving_id = FALSE)
	{
		if(count($items) == 0)
		{
			return -1;
		}

		$receivings_data = array(
			'receiving_time'		 => date('Y-m-d H:i:s'),
			'supplier_id'	 => $this->Supplier->exists($supplier_id) ? $supplier_id : null,
			'employee_id'	 => $employee_id,
			'comment'		 => $comment,
			'invoice_number' => $invoice_number
		);

		// Run these queries as a transaction, we want to make sure we do all or nothing
		$this->db->trans_start();

		$this->db->insert('receivings', $receivings_data);
		$receiving_id = $this->db->insert_id();

		foreach($payments as $payment_id=>$payment)
		{
			if( substr( $payment['payment_type'], 0, strlen( $this->lang->line('receivings_giftcard') ) ) == $this->lang->line('receivings_giftcard') )
			{
				// We have a gift card and we have to deduct the used value from the total value of the card.
				$splitpayment = explode( ':', $payment['payment_type'] );
				$cur_giftcard_value = $this->Giftcard->get_giftcard_value( $splitpayment[1] );
				$this->Giftcard->update_giftcard_value( $splitpayment[1], $cur_giftcard_value - $payment['payment_amount'] );
			}

			$receivings_payments_data = array(
				'receiving_id'		 => $receiving_id,
				'payment_type'	 => $payment['payment_type'],
				'payment_amount' => $payment['payment_amount']
			);
			$this->db->insert('receivings_payments', $receivings_payments_data);
		}

		foreach($items as $line=>$item)
		{
			$cur_item_info = $this->Item->get_info($item['item_id']);

			$receivings_items_data = array(
				'receiving_id'			=> $receiving_id,
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

			$this->db->insert('receivings_items', $receivings_items_data);

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
			$receiving_remarks = 'POS '.$receiving_id;
			$inv_data = array(
				'trans_date'		=> date('Y-m-d H:i:s'),
				'trans_items'		=> $item['item_id'],
				'trans_user'		=> $employee_id,
				'trans_location'	=> $item['item_location'],
				'trans_comment'		=> $receiving_remarks,
				'trans_inventory'	=> -$item['quantity']
			);
			$this->Inventory->insert($inv_data);

			$supplier = $this->Supplier->get_info($supplier_id);
 			if($supplier_id == -1 || $supplier->taxable)
 			{
				foreach($this->Item_taxes->get_info($item['item_id']) as $row)
				{
					$this->db->insert('receivings_items_taxes', array(
						'receiving_id' 	=> $receiving_id,
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
		
		return $receiving_id;
	}

	public function delete_list($receiving_ids, $employee_id, $update_inventory = TRUE) 
	{
		$result = TRUE;

		foreach($receiving_ids as $receiving_id)
		{
			$result &= $this->delete($receiving_id, $employee_id, $update_inventory);
		}

		return $result;
	}

	public function delete($receiving_id, $employee_id, $update_inventory = TRUE) 
	{
		// start a transaction to assure data integrity
		$this->db->trans_start();

		// first delete all payments
		$this->db->delete('receivings_payments', array('receiving_id' => $receiving_id));
		// then delete all taxes on items
		$this->db->delete('receivings_items_taxes', array('receiving_id' => $receiving_id));

		if($update_inventory)
		{
			// defect, not all item deletions will be undone??
			// get array with all the items involved in the receiving to update the inventory tracking
			$items = $this->get_receiving_items($receiving_id)->result_array();
			foreach($items as $item)
			{
				// create query to update inventory tracking
				$inv_data = array(
					'trans_date'      => date('Y-m-d H:i:s'),
					'trans_items'     => $item['item_id'],
					'trans_user'      => $employee_id,
					'trans_comment'   => 'Deleting receiving ' . $receiving_id,
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
		$this->db->delete('receivings_items', array('receiving_id' => $receiving_id));
		// delete receiving itself
		$this->db->delete('receivings', array('receiving_id' => $receiving_id));

		// execute transaction
		$this->db->trans_complete();
	
		return $this->db->trans_status();
	}

	public function get_receiving_items($receiving_id)
	{
		$this->db->from('receivings_items');
		$this->db->where('receiving_id', $receiving_id);

		return $this->db->get();
	}

	public function get_receiving_payments($receiving_id)
	{
		$this->db->from('receivings_payments');
		$this->db->where('receiving_id', $receiving_id);

		return $this->db->get();
	}

	public function get_payment_options($giftcard = TRUE)
	{
		$payments = array();

		$options=array('现金','微信','银行转账','支付宝','其他');
		foreach ($options as $value) {$payments[$value]=$value;}
		
		/*if($this->config->item('payment_options_order') == 'debitcreditcash')
		{
			$payments[$this->lang->line('receivings_debit')] = $this->lang->line('receivings_debit');
			$payments[$this->lang->line('receivings_credit')] = $this->lang->line('receivings_credit');
			$payments[$this->lang->line('receivings_cash')] = $this->lang->line('receivings_cash');
		}
		elseif($this->config->item('payment_options_order') == 'debitcashcredit')
		{
			$payments[$this->lang->line('receivings_debit')] = $this->lang->line('receivings_debit');
			$payments[$this->lang->line('receivings_cash')] = $this->lang->line('receivings_cash');
			$payments[$this->lang->line('receivings_credit')] = $this->lang->line('receivings_credit');
		}
		else // default: if($this->config->item('payment_options_order') == 'cashdebitcredit')
		{
			$payments[$this->lang->line('receivings_cash')] = $this->lang->line('receivings_cash');
			$payments[$this->lang->line('receivings_debit')] = $this->lang->line('receivings_debit');
			$payments[$this->lang->line('receivings_credit')] = $this->lang->line('receivings_credit');
		}

		$payments[$this->lang->line('receivings_check')] = $this->lang->line('receivings_check');

		if($giftcard)
		{
			$payments[$this->lang->line('receivings_giftcard')] = $this->lang->line('receivings_giftcard');
		}*/

		return $payments;
	}

	public function get_supplier($receiving_id)
	{
		$this->db->from('receivings');
		$this->db->where('receiving_id', $receiving_id);

		return $this->Supplier->get_info($this->db->get()->row()->supplier_id);
	}

	public function get_employee($receiving_id)
	{
		$this->db->from('receivings');
		$this->db->where('receiving_id', $receiving_id);

		return $this->Employee->get_info($this->db->get()->row()->employee_id);
	}

	public function check_invoice_number_exists($invoice_number, $receiving_id = '')
	{
		$this->db->from('receivings');
		$this->db->where('invoice_number', $invoice_number);
		if(!empty($receiving_id))
		{
			$this->db->where('receiving_id !=', $receiving_id);
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

	//We create a temp table that allows us to do easy report/receivings queries
	public function create_temp_table(array $inputs)
	{
		if($this->config->item('tax_included'))
		{
			$receiving_total = '(receivings_items.item_unit_price * receivings_items.quantity_purchased * (1 - receivings_items.discount_percent / 100))';
			$receiving_subtotal = '(receivings_items.item_unit_price * receivings_items.quantity_purchased * (1 - receivings_items.discount_percent / 100) * (100 / (100 + SUM(receivings_items_taxes.percent))))';
			$receiving_tax = '(receivings_items.item_unit_price * receivings_items.quantity_purchased * (1 - receivings_items.discount_percent / 100) * (1 - 100 / (100 + SUM(receivings_items_taxes.percent))))';
		}
		else
		{
			$receiving_total = '(receivings_items.item_unit_price * receivings_items.quantity_purchased * (1 - receivings_items.discount_percent / 100) * (1 + (SUM(receivings_items_taxes.percent) / 100)))';
			$receiving_subtotal = '(receivings_items.item_unit_price * receivings_items.quantity_purchased * (1 - receivings_items.discount_percent / 100))';
			$receiving_tax = '(receivings_items.item_unit_price * receivings_items.quantity_purchased * (1 - receivings_items.discount_percent / 100) * (SUM(receivings_items_taxes.percent) / 100))';
		}

		$receiving_cost  = '(receivings_items.item_cost_price * receivings_items.quantity_purchased)';

		$decimals = totals_decimals();

		if(empty($inputs['receiving_id']))
		{
			$where = 'WHERE DATE(receivings.receiving_time) BETWEEN ' . $this->db->escape($inputs['start_date']) . ' AND ' . $this->db->escape($inputs['end_date']);
		}
		else
		{
			$where = 'WHERE receivings.receiving_id = ' . $this->db->escape($inputs['receiving_id']);
		}

		// create a temporary table to contain all the payment types and amount
		$this->db->query('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $this->db->dbprefix('receivings_payments_temp') . 
			' (PRIMARY KEY(receiving_id), INDEX(receiving_id))
			(
				SELECT payments.receiving_id AS receiving_id, 
					IFNULL(SUM(payments.payment_amount), 0) AS receiving_payment_amount,
					GROUP_CONCAT(CONCAT(payments.payment_type, " ", payments.payment_amount) SEPARATOR ", ") AS payment_type
				FROM ' . $this->db->dbprefix('receivings_payments') . ' AS payments
				INNER JOIN ' . $this->db->dbprefix('receivings') . ' AS receivings
					ON receivings.receiving_id = payments.receiving_id
				' . "
				$where
				" . '
				GROUP BY payments.receiving_id
			)'
		);

		$this->db->query('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $this->db->dbprefix('receivings_items_temp') . 
			' (INDEX(receiving_date), INDEX(receiving_id))
			(
				SELECT
					DATE(receivings.receiving_time) AS receiving_date,
					receivings.receiving_time,
					receivings.receiving_id,
					receivings.comment,
					receivings.invoice_number,
					receivings.supplier_id,
					supplier_p.first_name AS supplier_name,
					supplier_p.first_name AS supplier_first_name,
					supplier_p.last_name AS supplier_last_name,
					supplier_p.email AS supplier_email,
					supplier_p.comments AS supplier_comments, 
					supplier.company_name AS supplier_company_name,
					receivings.employee_id,
					CONCAT(employee.first_name, " ", employee.last_name) AS employee_name,
					items.item_id,
					items.name,
					items.category,
					items.supplier_id,
					receivings_items.quantity_purchased,
					receivings_items.item_cost_price,
					receivings_items.item_unit_price,
					receivings_items.discount_percent,
					receivings_items.line,
					receivings_items.serialnumber,
					receivings_items.item_location,
					receivings_items.description,
					payments.payment_type,
					payments.receiving_payment_amount,
					IFNULL(SUM(receivings_items_taxes.percent), 0) AS item_tax_percent,
					' . "
					ROUND($receiving_subtotal, $decimals) AS subtotal,
					IFNULL(ROUND($receiving_tax, $decimals), 0) AS tax,
					IFNULL(ROUND($receiving_total, $decimals), ROUND($receiving_subtotal, $decimals)) AS total,
					ROUND($receiving_cost, $decimals) AS cost,
					ROUND($receiving_total - IFNULL($receiving_tax, 0) - $receiving_cost, $decimals) AS profit
					" . '
				FROM ' . $this->db->dbprefix('receivings_items') . ' AS receivings_items
				INNER JOIN ' . $this->db->dbprefix('receivings') . ' AS receivings
					ON receivings_items.receiving_id = receivings.receiving_id
				INNER JOIN ' . $this->db->dbprefix('items') . ' AS items
					ON receivings_items.item_id = items.item_id
				LEFT OUTER JOIN ' . $this->db->dbprefix('receivings_payments_temp') . ' AS payments
					ON receivings_items.receiving_id = payments.receiving_id		
				LEFT OUTER JOIN ' . $this->db->dbprefix('suppliers') . ' AS supplier
					ON items.supplier_id = supplier.person_id
				LEFT OUTER JOIN ' . $this->db->dbprefix('people') . ' AS supplier_p
					ON receivings.supplier_id = supplier_p.person_id
				LEFT OUTER JOIN ' . $this->db->dbprefix('suppliers') . ' AS supplier
					ON receivings.supplier_id = supplier.person_id
				LEFT OUTER JOIN ' . $this->db->dbprefix('people') . ' AS employee
					ON receivings.employee_id = employee.person_id
				LEFT OUTER JOIN ' . $this->db->dbprefix('receivings_items_taxes') . ' AS receivings_items_taxes
					ON receivings_items.receiving_id = receivings_items_taxes.receiving_id AND receivings_items.item_id = receivings_items_taxes.item_id AND receivings_items.line = receivings_items_taxes.line
				' . "
				$where
				" . '
				GROUP BY receivings.receiving_id, items.item_id, receivings_items.line
			)'
		);

		// drop the temporary table to contain memory consumption as it's no longer required
		$this->db->query('DROP TEMPORARY TABLE IF EXISTS ' . $this->db->dbprefix('receivings_payments_temp'));
	}
}
?>
