<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Transport_lib
{
	protected $CI;
	public $prefix='transport';

  	public function __construct()
	{
		$this->CI =& get_instance();
	}

	public function get_cart()
	{
		if(!$this->CI->session->userdata($this->prefix.'s_cart'))
		{
			$this->set_cart(array());
		}

		return $this->CI->session->userdata($this->prefix.'s_cart');
	}

	public function set_cart($cart_data)
	{
		$this->CI->session->set_userdata($this->prefix.'s_cart', $cart_data);
	}

	public function empty_cart()
	{
		$this->CI->session->unset_userdata($this->prefix.'s_cart');

		$this->CI->session->unset_userdata($this->prefix.'s_cart_backup');
	}
	
	public function get_comment() 
	{
		// avoid returning a NULL that results in a 0 in the comment if nothing is set/available
		$comment = $this->CI->session->userdata($this->prefix.'s_comment');

    	return empty($comment) ? '' : $comment;
	}

	public function set_comment($comment) 
	{
		$this->CI->session->set_userdata($this->prefix.'s_comment', $comment);
	}

	public function clear_comment() 	
	{
		$this->CI->session->unset_userdata($this->prefix.'s_comment');
	}
	
	public function get_invoice_number()
	{
		return $this->CI->session->userdata($this->prefix.'s_invoice_number');
	}
	
	public function set_invoice_number($invoice_number, $keep_custom = FALSE)
	{
		$current_invoice_number = $this->CI->session->userdata($this->prefix.'s_invoice_number');
		if(!$keep_custom || empty($current_invoice_number))
		{
			$this->CI->session->set_userdata($this->prefix.'s_invoice_number', $invoice_number);
		}
	}
	
	public function clear_invoice_number()
	{
		$this->CI->session->unset_userdata($this->prefix.'s_invoice_number');
	}
	
	public function is_invoice_number_enabled() 
	{
		return ($this->CI->session->userdata($this->prefix.'s_invoice_number_enabled') == 'true' ||
				$this->CI->session->userdata($this->prefix.'s_invoice_number_enabled') == '1') &&
				$this->CI->config->item('invoice_enable') == TRUE;
	}
	
	public function set_invoice_number_enabled($invoice_number_enabled)
	{
		return $this->CI->session->set_userdata($this->prefix.'s_invoice_number_enabled', $invoice_number_enabled);
	}
	
	public function is_print_after_transaction() 
	{
		return ($this->CI->session->userdata($this->prefix.'s_print_after_transport') == 'true' ||
				$this->CI->session->userdata($this->prefix.'s_print_after_transport') == '1');
	}
	
	public function set_print_after_transaction($print_after_transport)
	{
		return $this->CI->session->set_userdata($this->prefix.'s_print_after_transport', $print_after_transport);
	}
	
	public function get_email_receipt() 
	{
		return $this->CI->session->userdata($this->prefix.'s_email_receipt');
	}

	public function set_email_receipt($email_receipt) 
	{
		$this->CI->session->set_userdata($this->prefix.'s_email_receipt', $email_receipt);
	}

	public function clear_email_receipt() 	
	{
		$this->CI->session->unset_userdata($this->prefix.'s_email_receipt');
	}

	// Multiple Payments
	public function get_payments()
	{
		if(!$this->CI->session->userdata($this->prefix.'s_payments'))
		{
			$this->set_payments(array());
		}

		return $this->CI->session->userdata($this->prefix.'s_payments');
	}

	// Multiple Payments
	public function set_payments($payments_data)
	{
		$this->CI->session->set_userdata($this->prefix.'s_payments', $payments_data);
	}

	// Multiple Payments
	public function add_payment($payment_id, $payment_amount)
	{
		$payments = $this->get_payments();
		if(isset($payments[$payment_id]))
		{
			//payment_method already exists, add to payment_amount
			$payments[$payment_id]['payment_amount'] = bcadd($payments[$payment_id]['payment_amount'], $payment_amount);
		}
		else
		{
			//add to existing array
			$payment = array($payment_id => array('payment_type' => $payment_id, 'payment_amount' => $payment_amount));
			
			$payments += $payment;
		}

		$this->set_payments($payments);
	}

	// Multiple Payments
	public function edit_payment($payment_id, $payment_amount)
	{
		$payments = $this->get_payments();
		if(isset($payments[$payment_id]))
		{
			$payments[$payment_id]['payment_type'] = $payment_id;
			$payments[$payment_id]['payment_amount'] = $payment_amount;
			$this->set_payments($payments);

			return TRUE;
		}

		return FALSE;
	}

	// Multiple Payments
	public function delete_payment($payment_id)
	{
		$payments = $this->get_payments();
		unset($payments[urldecode($payment_id)]);
		$this->set_payments($payments);
	}

	// Multiple Payments
	public function empty_payments()
	{
		$this->CI->session->unset_userdata($this->prefix.'s_payments');
	}

	// Multiple Payments
	public function get_payments_total()
	{
		$subtotal = 0;
		foreach($this->get_payments() as $payments)
		{
		    $subtotal = bcadd($payments['payment_amount'], $subtotal);
		}

		return $subtotal;
	}

	// Multiple Payments
	public function get_amount_due()
	{
		$payment_total = $this->get_payments_total();
		$transports_total = $this->get_total();
		$amount_due = bcsub($transports_total, $payment_total);
		$precision = $this->CI->config->item('currency_decimals');
		$rounded_due = bccomp(round($amount_due, $precision, PHP_ROUND_HALF_EVEN), 0, $precision);
		// take care of rounding error introduced by round tripping payment amount to the browser
 		return  $rounded_due == 0 ? 0 : $amount_due;
	}

	public function get_stock_location()
	{
		if(!$this->CI->session->userdata($this->prefix.'s_stock_location'))
		{
			$this->set_stock_location(-1);
		}

		return $this->CI->session->userdata($this->prefix.'s_stock_location');
	}

	public function set_stock_location($stock_location_id)
	{
		$this->CI->session->set_userdata($this->prefix.'s_stock_location', $stock_location_id);
	}

	public function remove_stock_location()
	{
		$this->CI->session->unset_userdata($this->prefix.'s_stock_location');
	}
	
	public function get_employee()
	{
		if(!$this->CI->session->userdata($this->prefix.'s_employee'))
		{
			$this->set_employee(-1);
		}

		return $this->CI->session->userdata($this->prefix.'s_employee');
	}

	public function set_employee($employee_id)
	{
		$this->CI->session->set_userdata($this->prefix.'s_employee', $employee_id);
	}

	public function remove_employee()
	{
		$this->CI->session->unset_userdata($this->prefix.'s_employee');
	}

	public function get_mode()
	{
		if(!$this->CI->session->userdata($this->prefix.'s_mode'))
		{
			$this->set_mode($this->prefix.'');
		}

		return $this->CI->session->userdata($this->prefix.'s_mode');
	}

	public function set_mode($mode)
	{
		$this->CI->session->set_userdata($this->prefix.'s_mode', $mode);
	}

	public function clear_mode()
	{
		$this->CI->session->unset_userdata($this->prefix.'s_mode');
	}

    public function get_transaction_location()
    {
    	return $this->CI->Stock_location->get_default_location_id();

        /*if(!$this->CI->session->userdata($this->prefix.'s_location'))
        {
			$this->set_transaction_location($this->CI->Stock_location->get_default_location_id());
        }

        return $this->CI->session->userdata($this->prefix.'s_location');*/
    }

    public function set_transaction_location($location)
    {
        //$this->CI->session->set_userdata($this->prefix.'s_location', $location);
    }
    
    public function clear_transaction_location()
    {
    	//$this->CI->session->unset_userdata($this->prefix.'s_location');
    }
    
    public function set_giftcard_remainder($value)
    {
    	$this->CI->session->set_userdata($this->prefix.'s_giftcard_remainder', $value);
    }
    
    public function get_giftcard_remainder()
    {
    	return $this->CI->session->userdata($this->prefix.'s_giftcard_remainder');
    }
    
    public function clear_giftcard_remainder()
    {
    	$this->CI->session->unset_userdata($this->prefix.'s_giftcard_remainder');
    }

	public function add_item(&$item_id, $quantity = 1, $item_location, $discount = 0, $price = NULL, 
		$description = NULL, $serialnumber = NULL, $include_deleted = FALSE,$options=array())
	{
		$is_item_kit=(1==empty($options['is_item_kit'])?0:$options['is_item_kit']);
		$item_info = $is_item_kit?$this->CI->Item_kit->get_info_like_item($item_id)
								:$this->CI->Item->get_info_by_id_or_number($item_id);

		//make sure item exists		
		if(empty($item_info))
		{
			$item_id = -1;
            return FALSE;			
		}
		
		$item_id = $item_info->item_id;

		// Serialization and Description

		//Get all items in the cart so far...
		$items = $this->get_cart();

        //We need to loop through all items in the cart.
        //If the item is already there, get it's key($updatekey).
        //We also need to get the next key that we are going to use in case we need to add the
        //item to the cart. Since items can be deleted, we can't use a count. we use the highest key + 1.

        $maxkey = 0;                       //Highest key so far
        $itemalreadyintransport = FALSE;        //We did not find the item yet.
		$insertkey = 0;                    //Key to use for new entry.
		$updatekey = 0;                    //Key to use to update(quantity)

		foreach($items as $item)
		{
            //We primed the loop so maxkey is 0 the first time.
            //Also, we have stored the key in the element itself so we can compare.

			if($maxkey <= $item['line'])
			{
				$maxkey = $item['line'];
			}

			$is_item_kit_1=(1==empty($item['is_item_kit'])?0:$item['is_item_kit']);

			if($item['item_id'] == $item_id && $item['item_location'] == $item_location && $is_item_kit_1 == $is_item_kit)
			{
				$itemalreadyintransport = TRUE;
				$updatekey = $item['line'];
                if(!$item_info->is_serialized)
                {
                    $quantity = bcadd($quantity, $items[$updatekey]['quantity']);
                }
			}
		}

		$insertkey = $maxkey+1;
		//array/cart records are identified by $insertkey and item_id is just another field.
		$price = 0;
		$total = $this->get_item_total($quantity, $price, $discount);
        $discounted_total = $this->get_item_total($quantity, $price, $discount, TRUE);
		//Item already exists and is not serialized, add to quantity
		if(!$itemalreadyintransport || $item_info->is_serialized)
		{
            $item = array($insertkey => array(
                    'item_id' => $item_id,
                    'item_location' => $item_location,
                    'stock_name' => $this->CI->Stock_location->get_location_name_session($item_location),
                    'line' => $insertkey,
                    'name' => $item_info->name,
                    'item_number' => $item_info->item_number,
                    'description' => $description != NULL ? $description : $item_info->description,
                    'serialnumber' => $serialnumber != NULL ? $serialnumber : '',
                    'allow_alt_description' => $item_info->allow_alt_description,
                    'is_serialized' => $item_info->is_serialized,
                    'quantity' => $quantity,
                    'discount' => $discount,
                    'in_stock' => (1==$item_info->is_infinite)?0:$this->CI->Item_quantity->get_item_quantity($item_id, $item_location)->quantity,
                    'price' => $price,
                    'total' => $total,
                    'discounted_total' => $discounted_total,
                    'is_infinite' => $item_info->is_infinite,
                    'is_item_kit' => $is_item_kit?1:0
                )
            );
			//add to existing array
			$items += $item;
		}
        else
        {
            $line = &$items[$updatekey];
            $line['quantity'] = $quantity;
            $line['total'] = $total;
            $line['discounted_total'] = $discounted_total;
        }

		$this->set_cart($items);

		return TRUE;
	}
	
	public function out_of_stock($item_id, $item_location)
	{
		//make sure item exists		
		if($item_id != -1)
		{
			$item_info = $this->CI->Item->get_info_by_id_or_number($item_id);
			if($item_info->is_infinite){return '';}

			$item_quantity = $this->CI->Item_quantity->get_item_quantity($item_id, $item_location)->quantity;
			$quantity_added = $this->get_quantity_already_added($item_id, $item_location);

			if($item_quantity - $quantity_added < 0)
			{
				return $this->CI->lang->line($this->prefix.'s_quantity_less_than_zero');
			}
			elseif($item_quantity - $quantity_added < $this->CI->Item->get_info_by_id_or_number($item_id)->reorder_level)
			{
				return $this->CI->lang->line($this->prefix.'s_quantity_less_than_reorder_level');
			}
		}

		return '';
	}
	
	public function get_quantity_already_added($item_id, $item_location,$items=NULL)
	{
		if(empty($items)){$items = $this->get_cart();}
		$quantity_already_added = 0;
		foreach($items as $item)
		{
			if($item['item_id'] == $item_id && $item['item_location'] == $item_location)
			{
				$quantity_already_added+=$item['quantity'];
			}
		}
		
		return $quantity_already_added;
	}
	
	public function get_item_id($line_to_get)
	{
		$items = $this->get_cart();

		foreach($items as $line=>$item)
		{
			if($line == $line_to_get)
			{
				return $item['item_id'];
			}
		}
		
		return -1;
	}

	public function edit_item($line, $description, $serialnumber, $quantity, $discount, $price)
	{
		$items = $this->get_cart();
		if(isset($items[$line]))	
		{
			$line = &$items[$line];
			$line['description'] = $description;
			$line['serialnumber'] = $serialnumber;
			$line['quantity'] = $quantity;
			$line['discount'] = $discount;
			$line['price'] = $price;
			$line['total'] = $this->get_item_total($quantity, $price, $discount);
			$line['discounted_total'] = $this->get_item_total($quantity, $price, $discount, TRUE);
			$this->set_cart($items);
		}

		return FALSE;
	}

	public function delete_item($line)
	{
		$items = $this->get_cart();
		unset($items[$line]);
		$this->set_cart($items);
	}

	public function return_entire_transaction($receipt_transport_id)
	{
		//POS #
		$pieces = explode(' ', $receipt_transport_id);
		$transport_id = $pieces[1];

		$this->empty_cart();
		$this->remove_stock_location();

		foreach($this->CI->Transport->get_transport_items($transport_id)->result() as $row)
		{
			$this->add_item($row->item_id, -$row->quantity_purchased, $row->item_location, $row->discount_percent, $row->item_unit_price, $row->description, $row->serialnumber, TRUE);
		}

		$this->set_stock_location($this->CI->Transport->get_stock_location($transport_id)->person_id);
	}
	
	public function add_item_kit($external_item_kit_id, $item_location, $discount)
	{
		//KIT #
		$pieces = explode(' ', $external_item_kit_id);
		$item_kit_id = $pieces[1];
		$result = TRUE;
		
		$options=array('is_item_kit'=>1);
		$result &= $this->add_item($item_kit_id,1,$item_location,$discount,NULL,NULL,NULL,FALSE,$options);

		/*foreach($this->CI->Item_kit_items->get_info($item_kit_id) as $item_kit_item)
		{
			$result &= $this->add_item($item_kit_item['item_id'], $item_kit_item['quantity'], $item_location, $discount);
		}*/
		
		return $result;
	}

	public function copy_entire_transaction($transport_id)
	{
		$this->empty_cart();
		$this->remove_stock_location();

		foreach($this->CI->Transport->get_transport_items($transport_id)->result() as $row)
		{
			$this->add_item($row->item_id, $row->quantity_purchased, $row->item_location, $row->discount_percent, $row->item_unit_price, $row->description, $row->serialnumber, TRUE);
		}

		foreach($this->CI->Transport->get_transport_payments($transport_id)->result() as $row)
		{
			$this->add_payment($row->payment_type, $row->payment_amount);
		}

		$this->set_stock_location($this->CI->Transport->get_stock_location($transport_id)->person_id);
		$this->set_employee($this->CI->Transport->get_employee($transport_id)->person_id);
	}
	
	public function copy_entire_suspended_transaction($transport_id)
	{
		$this->empty_cart();
		$this->remove_stock_location();

		foreach($this->CI->Transport_suspended->get_transaction_items($transport_id)->result() as $row)
		{
			$options=array('is_item_kit'=>$row->is_item_kit);
			$this->add_item($row->item_id, $row->quantity_purchased, $row->item_location, $row->discount_percent, 
				$row->item_unit_price, $row->description, $row->serialnumber,FALSE,$options);
		}
		foreach($this->CI->Transport_suspended->get_transaction_payments($transport_id)->result() as $row)
		{
			$this->add_payment($row->payment_type, $row->payment_amount);
		}
		$suspended_transport_info = $this->CI->Transport_suspended->get_info($transport_id)->row();
		$this->set_stock_location($suspended_transport_info->person_id);
		$this->set_comment($suspended_transport_info->comment);
		$this->set_invoice_number($suspended_transport_info->invoice_number);

		$this->set_mailState($suspended_transport_info->mail_state);
		$this->set_transaction_id($transport_id);
		$this->set_transaction_items_location($suspended_transport_info->transport_items_location);
		$this->set_cart_backup();
	}

	public function clear_all()
	{
		$this->set_invoice_number_enabled(FALSE);
		$this->clear_mode();
		$this->empty_cart();
		$this->clear_comment();
		$this->clear_email_receipt();
		$this->clear_invoice_number();
		$this->clear_giftcard_remainder();
		$this->empty_payments();
		$this->remove_stock_location();
		
		$this->clear_transaction_id();$this->clear_mailState();$this->clear_transaction_items_location();
	}
	
	public function is_stock_location_taxable()
	{
		return FALSE;
		/*$stock_location_id = $this->get_stock_location();
		$stock_location = $this->CI->Stock_location->get_info($stock_location_id);
		
		//Do not charge transports tax if we have a stock_location that is not taxable
		return $stock_location->taxable or $stock_location_id == -1;*/
	}

	public function get_taxes()
	{
		$taxes = array();

		//Do not charge transports tax if we have a stock_location that is not taxable
		if($this->is_stock_location_taxable())
		{
			foreach($this->get_cart() as $line => $item)
			{
				$tax_info = $this->CI->Item_taxes->get_info($item['item_id']);

				foreach($tax_info as $tax)
				{
					$name = to_tax_decimals($tax['percent']) . '% ' . $tax['name'];
					$tax_amount = $this->get_item_tax($item['quantity'], $item['price'], $item['discount'], $tax['percent']);

					if(!isset($taxes[$name]))
					{
						$taxes[$name] = 0;
					}

					$taxes[$name] = bcadd($taxes[$name], $tax_amount);
				}
			}
		}

		return $taxes;
	}

	public function apply_stock_location_discount($discount_percent)
	{	
		// Get all items in the cart so far...
		$items = $this->get_cart();
		
		foreach($items as &$item)
		{
			$quantity = $item['quantity'];
			$price = $item['price'];

			// set a new discount only if the current one is 0
			if($item['discount'] == 0)
			{
				$item['discount'] = $discount_percent;
				$item['total'] = $this->get_item_total($quantity, $price, $discount_percent);
				$item['discounted_total'] = $this->get_item_total($quantity, $price, $discount_percent, TRUE);
			}
		}

		$this->set_cart($items);
	}
	
	public function get_discount()
	{
		$discount = 0;
		/*foreach($this->get_cart() as $item)
		{
			if($item['discount'] > 0)
			{
				$item_discount = $this->get_item_discount($item['quantity'], $item['price'], $item['discount']);
				$discount = bcadd($discount, $item_discount);
			}
		}*/

		return $discount;
	}

	public function get_subtotal($include_discount = FALSE, $exclude_tax = FALSE)
	{
		return $this->calculate_subtotal($include_discount, $exclude_tax);
	}
	
	public function get_item_total_tax_exclusive($item_id, $quantity, $price, $discount_percentage, $include_discount = FALSE) 
	{
		$tax_info = $this->CI->Item_taxes->get_info($item_id);
		$item_price = $this->get_item_total($quantity, $price, $discount_percentage, $include_discount);
		// only additive tax here
		foreach($tax_info as $tax)
		{
			$tax_percentage = $tax['percent'];
			$item_price = bcsub($item_price, $this->get_item_tax($quantity, $price, $discount_percentage, $tax_percentage));
		}
		
		return $item_price;
	}
	
	public function get_item_total($quantity, $price, $discount_percentage, $include_discount = FALSE)  
	{
		$total = bcmul($quantity, $price);
		if($include_discount)
		{
			$discount_amount = $this->get_item_discount($quantity, $price, $discount_percentage);

			return bcsub($total, $discount_amount);
		}

		return $total;
	}
	
	public function get_item_discount($quantity, $price, $discount_percentage)
	{
		$total = bcmul($quantity, $price);
		$discount_fraction = bcdiv($discount_percentage, 100);

		return bcmul($total, $discount_fraction);
	}
	
	public function get_item_tax($quantity, $price, $discount_percentage, $tax_percentage) 
	{
		$price = $this->get_item_total($quantity, $price, $discount_percentage, TRUE);
		if($this->CI->config->config['tax_included'])
		{
			$tax_fraction = bcadd(100, $tax_percentage);
			$tax_fraction = bcdiv($tax_fraction, 100);
			$price_tax_excl = bcdiv($price, $tax_fraction);

			return bcsub($price, $price_tax_excl);
		}
		$tax_fraction = bcdiv($tax_percentage, 100);

		return bcmul($price, $tax_fraction);
	}

	public function calculate_subtotal($include_discount = FALSE, $exclude_tax = FALSE) 
	{
		$subtotal = 0;
		foreach($this->get_cart() as $item)
		{
			if($exclude_tax && $this->CI->config->config['tax_included'])
			{
				$subtotal = bcadd($subtotal, $this->get_item_total_tax_exclusive($item['item_id'], $item['quantity'], $item['price'], $item['discount'], $include_discount));
			}
			else 
			{
				$subtotal = bcadd($subtotal, $this->get_item_total($item['quantity'], $item['price'], $item['discount'], $include_discount));
			}
		}

		return $subtotal;
	}

	public function get_total()
	{
		$total = $this->calculate_subtotal(TRUE);		
		if(!$this->CI->config->config['tax_included'])
		{
			foreach($this->get_taxes() as $tax)
			{
				$total = bcadd($total, $tax);
			}
		}

		return $total;
	}
    
    public function get_mailState() 
	{
		$state=$this->CI->session->userdata($this->prefix.'_mail_state');

		return empty($state)?0:$state;
	}

	public function set_mailState($state) 
	{
		$this->CI->session->set_userdata($this->prefix.'_mail_state', $state);
	}

	public function clear_mailState() 	
	{
		$this->CI->session->unset_userdata($this->prefix.'_mail_state');
	}

	public function get_transaction_id(){
		$id=$this->CI->session->userdata($this->prefix.'_id');

		return empty($id)?FALSE:$id;
	}
	public function set_transaction_id($id){
		$this->CI->session->set_userdata($this->prefix.'_id', $id);
	}
	public function clear_transaction_id(){
		$this->CI->session->unset_userdata($this->prefix.'_id');
	}

	public function get_transaction_items_location(){
		$location_id=$this->CI->session->userdata($this->prefix.'_items_location');
		if(empty($location_id)){
			$this->clear_transaction_items_location();
			$location_id=$this->CI->session->userdata($this->prefix.'_items_location');
		}

		return $location_id;
	}
	public function set_transaction_items_location($location_id){
		$this->CI->session->set_userdata($this->prefix.'_items_location', $location_id);
	}
	public function clear_transaction_items_location(){
		//$this->CI->session->unset_userdata($this->prefix.'_items_location');
		$this->set_transaction_items_location($this->get_transaction_location());
	}

	public function get_cart_backup(){
		return $this->CI->session->userdata($this->prefix.'s_cart_backup');
	}
	public function set_cart_backup(){
		$this->CI->session->set_userdata($this->prefix.'s_cart_backup', $this->get_cart());
	}
	public function get_quantity_already_added_backup($item_id, $item_location,$items=NULL){
		if(empty($items)){$items = $this->get_cart_backup();}
		$quantity_already_added = 0;
		foreach($items as $item)
		{
			if($item['item_id'] == $item_id && $item['item_location'] == $item_location)
			{
				$quantity_already_added+=$item['quantity'];
			}
		}
		
		return $quantity_already_added;
	}
	public function unfold_cart($items){
		$flat_cart=array();
		foreach ($items as $item) {
			$kit_items=array();$factor=1;
			if($item['is_item_kit']==1){
				$kit_items=$this->CI->Item_kit_items->get_info($item['item_id']);
				$factor=$item['quantity'];
			}
			else{$kit_items[]=$item;}

			foreach ($kit_items as $kit_item) {
				$kit_item['quantity']*=$factor;$kit_item['item_location']=$item['item_location'];

				if(array_key_exists($kit_item['item_id'],$flat_cart)){
					$flat_cart[$kit_item['item_id']]['quantity']+=$kit_item['quantity'];
				}else{
					$flat_cart[$kit_item['item_id']]=$kit_item;
				}
			}
		}
		return $flat_cart;
	}
	public function get_cart_diff(){
		$items=$this->get_cart();$items_bak=$this->get_cart_backup();$datas=array();
		$items=$this->unfold_cart($items);$items_bak=$this->unfold_cart($items_bak);

		foreach($items as $item){
			$id=$item['item_id'];$loc=$item['item_location'];
			if(array_key_exists($id,$datas)&&array_key_exists($loc,$datas[$id])){continue;}

			$quantity=$this->get_quantity_already_added($id,$loc,$items);
			$quantity_bak=$this->get_quantity_already_added_backup($id,$loc,$items_bak);

			if(!array_key_exists($id,$datas)){$datas[$id]=array();}
			$datas[$id][$loc]=array('old'=>$quantity_bak,'new'=>$quantity);
		}

		foreach ($items_bak as $line => $item) {
			$id=$item['item_id'];$loc=$item['item_location'];
			if(array_key_exists($id,$datas)&&array_key_exists($loc,$datas[$id])){continue;}

			$quantity=0;
			$quantity_bak=$this->get_quantity_already_added_backup($id,$loc,$items_bak);

			if(!array_key_exists($id,$datas)){$datas[$id]=array();}
			$datas[$id][$loc]=array('old'=>$quantity_bak,'new'=>$quantity);
		}

		//print_r($datas);
		return $datas;
	}

	public function mark_unedited_transaction($data=array()){
		$data[$this->prefix.'_edited']='false';
		return $data;
	}

	public function save_search_params(){
		$this->CI->session->set_userdata($this->prefix.'s_search_params', $_GET);
	}
	public function get_search_params(){
		return $this->CI->session->userdata($this->prefix.'s_search_params');	
	}
}

?>
