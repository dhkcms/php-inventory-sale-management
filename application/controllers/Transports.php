<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once("Secure_Controller.php");

class Transports extends Secure_Controller
{
	public function __construct()
	{
		parent::__construct('transports');

		$this->load->library('transport_lib');
		//$this->load->library('barcode_lib');
		//$this->load->library('email_lib');
	}

	private function check_stock_location(&$data){
		$location_id=$this->transport_lib->get_transaction_items_location();
		$location_id_default=$this->Stock_location->get_default_location_id();
		//echo $location_id;echo $location_id_default;
		if($location_id_default!=$location_id){
			$location_name=$this->Stock_location->get_location_name_session($location_id);
			$location_name_default=$this->Stock_location->get_location_name_session($location_id_default);
			$data['warning']='这笔交易是在“'.$location_name.'”进行的，不是当前所查看的仓库“'.
			$location_name_default.'”。需要将仓库切换到“'.$location_name.'”才能修改，或者点击'.
			'<a href="transports/change_transport_to_current_location" title="下面所列的所有交易物品所在的仓库地址将改成'.$location_name_default.'">这里</a>'.
			'将这笔交易的地址改成“'.$location_name_default.'”';

			$data['transaction_editable']=0;
		}
	}

	public function load(){
		$this->_reload();
	}
	public function create(){
		$this->cancel();
	}

	public function index()
	{
		//$this->_reload();
		$this->manage();
	}
	
	public function manage()
	{
		$person_id = $this->session->userdata('person_id');

		$from_transport_id  = $this->input->get('from_id');

		/*if(!$this->Employee->has_grant('reports_transports', $person_id))
		{
			redirect('no_access/transports/reports_transports');
		}
		else*/
		{
			$data['table_headers'] = get_transports_manage_table_headers();

			// filters that will be loaded in the multiselect dropdown
			if($this->config->item('invoice_enable') == TRUE)
			{
				$data['filters'] = array('only_cash' => $this->lang->line('transports_cash_filter'),
										'only_invoices' => $this->lang->line('transports_invoice_filter'));
			}
			else
			{
				$data['filters'] = array('only_cash' => $this->lang->line('transports_cash_filter'));
			}

			if(!empty($from_transport_id)){$data['from_transport_id']=$from_transport_id;}
			$data['last_search']=$this->transport_lib->get_search_params();

			$this->load->view('transaction/manage', $data);
		}
	}
	
	public function get_row($row_id)
	{
		$transport_info = $this->Transport->get_info($row_id)->row();
		$data_row = $this->xss_clean(get_transport_data_row($transport_info, $this));

		echo json_encode($data_row);
	}

	public function search()
	{
		$this->transport_lib->save_search_params();

		$search = $this->input->get('search');
		$limit  = $this->input->get('limit');
		$offset = $this->input->get('offset');
		$sort   = $this->input->get('sort');
		$order  = $this->input->get('order');

		$filters = array('transport_type' => 'all',
						'location_id' => 'all',
						'start_date' => $this->input->get('start_date'),
						'end_date' => $this->input->get('end_date'),
						'only_cash' => FALSE,
						'only_invoices' => $this->config->item('invoice_enable') && $this->input->get('only_invoices'),
						'is_valid_receipt' => $this->Transport->is_valid_receipt($search));

		// check if any filter is set in the multiselect dropdown
		$filledup = array_fill_keys($this->input->get('filters'), TRUE);
		$filters = array_merge($filters, $filledup);

		$transports = $this->Transport->search($search, $filters, $limit, $offset, $sort, $order);
		$total_rows = $this->Transport->get_found_rows($search, $filters);
		//$payments = $this->Transport->get_payments_summary($search, $filters);
		//$payment_summary = $this->xss_clean(get_transports_manage_payments_summary($payments, $transports, $this));

		$data_rows = array();
		foreach($transports->result() as $transport)
		{
			$data_rows[] = $this->xss_clean(get_transport_data_row($transport, $this));
		}

		/*if($total_rows > 0)
		{
			$data_rows[] = $this->xss_clean(get_transport_data_last_row($transports, $this));
		}*/

		echo json_encode(array('total' => $total_rows, 'rows' => $data_rows));//, 'payment_summary' => $payment_summary)
	}

	public function item_search()
	{
		$suggestions = array();
		$receipt = $search = $this->input->get('term') != '' ? $this->input->get('term') : NULL;

		if($this->transport_lib->get_mode() == 'return' && $this->Transport->is_valid_receipt($receipt))
		{
			// if a valid receipt or invoice was found the search term will be replaced with a receipt number (POS #)
			$suggestions[] = $receipt;
		}
		$suggestions = array_merge($suggestions, $this->Item_kit->get_search_suggestions($search));
		$suggestions = array_merge($suggestions, $this->Item->get_search_suggestions($search, array('search_custom' => FALSE, 'is_deleted' => FALSE), TRUE));
		
		$suggestions = $this->xss_clean($suggestions);

		echo json_encode($suggestions);
	}

	public function suggest_search()
	{
		$search = $this->input->post('term') != '' ? $this->input->post('term') : NULL;
		
		$suggestions = $this->xss_clean($this->Transport->get_search_suggestions($search));
		
		echo json_encode($suggestions);
	}

	public function select_stock_location()
	{
		$stock_location_id = $this->input->post('stock_location');
		if($this->Stock_location->exists($stock_location_id))
		{
			$this->transport_lib->set_stock_location($stock_location_id);

			/*$discount_percent = $this->Stock_location->get_info($stock_location_id)->discount_percent;

			// apply stock_location default discount to items that have 0 discount
			if($discount_percent != '')
			{	
				$this->transport_lib->apply_stock_location_discount($discount_percent);
			}*/
		}
		
		$this->_reload();
	}

	public function change_mode()
	{
		$stock_location = $this->input->post('stock_location');
		if (!$stock_location || $stock_location == $this->transport_lib->get_transaction_location())
		{
			$mode = $this->input->post('mode');
			$this->transport_lib->set_mode($mode);
		} 
		elseif($this->Stock_location->is_allowed_location($stock_location, 'transports'))
		{
			$this->transport_lib->set_transaction_location($stock_location);
		}

		$this->_reload();
	}
	
	public function set_comment() 
	{
		$this->transport_lib->set_comment($this->input->post('comment'));
	}
	public function set_mailState() 
	{
		$this->transport_lib->set_mailState($this->input->post('mail_state'));
	}
	
	public function set_invoice_number()
	{
		$this->transport_lib->set_invoice_number($this->input->post('transports_invoice_number'));
	}
	
	public function set_invoice_number_enabled()
	{
		$this->transport_lib->set_invoice_number_enabled($this->input->post('transports_invoice_number_enabled'));
	}
	
	public function set_print_after_transaction()
	{
		$this->transport_lib->set_print_after_transaction($this->input->post('transports_print_after_transport'));
	}
	
	public function set_email_receipt()
	{
 		$this->transport_lib->set_email_receipt($this->input->post('email_receipt'));
	}

	// Multiple Payments
	public function add_payment()
	{
		$data = array();
		$this->form_validation->set_rules('amount_tendered', 'lang:transports_amount_tendered', 'trim|required|callback_numeric');

		$payment_type = $this->input->post('payment_type');

		if($this->form_validation->run() == FALSE)
		{
			if($payment_type == $this->lang->line('transports_giftcard'))
			{
				$data['error'] = $this->lang->line('transports_must_enter_numeric_giftcard');
			}
			else
			{
				$data['error'] = $this->lang->line('transports_must_enter_numeric');
			}
		}
		else
		{
			if($payment_type == $this->lang->line('transports_giftcard'))
			{
				// in case of giftcard payment the register input amount_tendered becomes the giftcard number
				$giftcard_num = $this->input->post('amount_tendered');

				$payments = $this->transport_lib->get_payments();
				$payment_type = $payment_type . ':' . $giftcard_num;
				$current_payments_with_giftcard = isset($payments[$payment_type]) ? $payments[$payment_type]['payment_amount'] : 0;
				$cur_giftcard_value = $this->Giftcard->get_giftcard_value($giftcard_num);
				
				if(($cur_giftcard_value - $current_payments_with_giftcard) <= 0)
				{
					$data['error'] = $this->lang->line('giftcards_remaining_balance', $giftcard_num, to_currency($cur_giftcard_value));
				}
				else
				{
					$new_giftcard_value = $this->Giftcard->get_giftcard_value($giftcard_num) - $this->transport_lib->get_amount_due();
					$new_giftcard_value = $new_giftcard_value >= 0 ? $new_giftcard_value : 0;
					$this->transport_lib->set_giftcard_remainder($new_giftcard_value);
					$new_giftcard_value = str_replace('$', '\$', to_currency($new_giftcard_value));
					$data['warning'] = $this->lang->line('giftcards_remaining_balance', $giftcard_num, $new_giftcard_value);
					$amount_tendered = min( $this->transport_lib->get_amount_due(), $this->Giftcard->get_giftcard_value($giftcard_num) );

					$this->transport_lib->add_payment($payment_type, $amount_tendered);
				}
			}
			else
			{
				$amount_tendered = $this->input->post('amount_tendered');

				$this->transport_lib->add_payment($payment_type, $amount_tendered);
			}
		}

		$this->_reload($data);
	}

	// Multiple Payments
	public function delete_payment($payment_id)
	{
		$this->transport_lib->delete_payment($payment_id);

		$this->_reload();
	}

	public function add()
	{
		$data = array();
		
		$discount = 0;

		// check if any discount is assigned to the selected stock_location
		/*$stock_location_id = $this->transport_lib->get_stock_location();
		if($stock_location_id != -1)
		{
			// load the stock_location discount if any
			$discount_percent = $this->Stock_location->get_info($stock_location_id)->discount_percent;
			if($discount_percent != '')
			{
				$discount = $discount_percent;
			}
		}

		// if the stock_location discount is 0 or no stock_location is selected apply the default transports discount
		if($discount == 0)
		{
			$discount = $this->config->item('default_transports_discount');
		}*/

		//$mode = $this->transport_lib->get_mode();
		$quantity = 1;//($mode == 'return') ? -1 : 1;
		$item_location = $this->transport_lib->get_transaction_location();
		$item_id_or_number_or_item_kit_or_receipt = $this->input->post('item');

		/*if($mode == 'return' && $this->Transport->is_valid_receipt($item_id_or_number_or_item_kit_or_receipt))
		{
			$this->transport_lib->return_entire_transaction($item_id_or_number_or_item_kit_or_receipt);
		}
		else*/if($this->Item_kit->is_valid_item_kit($item_id_or_number_or_item_kit_or_receipt))
		{
			if(!$this->transport_lib->add_item_kit($item_id_or_number_or_item_kit_or_receipt, $item_location, $discount))
			{
				$data['error'] = $this->lang->line('transports_unable_to_add_item');
			}
		}
		else
		{
			if(!$this->transport_lib->add_item($item_id_or_number_or_item_kit_or_receipt, $quantity, $item_location, $discount))
			{
				$data['error'] = $this->lang->line('transports_unable_to_add_item');
			}
			else
			{
				$data['warning'] = $this->transport_lib->out_of_stock($item_id_or_number_or_item_kit_or_receipt, $item_location);
			}
		}

		$this->_reload($data);
	}

	public function edit_item($item_id)
	{
		$data = array();

		$this->form_validation->set_rules('price', 'lang:items_price', 'required|callback_numeric');
		$this->form_validation->set_rules('quantity', 'lang:items_quantity', 'required|callback_numeric');
		//$this->form_validation->set_rules('discount', 'lang:items_discount', 'required|callback_numeric');

		$description = $this->input->post('description');
		$serialnumber = $this->input->post('serialnumber');
		$price = parse_decimals($this->input->post('price'));
		$quantity = parse_decimals($this->input->post('quantity'));
		$discount = 0;//parse_decimals($this->input->post('discount'));
		$item_location = $this->input->post('location');

		$price=abs($price);$quantity=abs($quantity);

		if($this->form_validation->run() != FALSE)
		{
			$this->transport_lib->edit_item($item_id, $description, $serialnumber, $quantity, $discount, $price);
		}
		else
		{
			$data['error'] = $this->lang->line('transports_error_editing_item');
		}

		$data['warning'] = $this->transport_lib->out_of_stock($this->transport_lib->get_item_id($item_id), $item_location);

		$this->_reload($data);
	}

	public function delete_item($item_number)
	{
		$this->transport_lib->delete_item($item_number);

		$this->_reload();
	}

	public function remove_stock_location()
	{
		$this->transport_lib->clear_giftcard_remainder();
		$this->transport_lib->clear_invoice_number();
		$this->transport_lib->remove_stock_location();

		$this->_reload();
	}

	public function complete()
	{return;
		/*$data = array();

		$data['cart'] = $this->transport_lib->get_cart();
		$data['subtotal'] = $this->transport_lib->get_subtotal();
		$data['discounted_subtotal'] = $this->transport_lib->get_subtotal(TRUE);
		$data['tax_exclusive_subtotal'] = $this->transport_lib->get_subtotal(TRUE, TRUE);
		$data['taxes'] = $this->transport_lib->get_taxes();
		$data['total'] = $this->transport_lib->get_total();
		$data['discount'] = $this->transport_lib->get_discount();
		$data['receipt_title'] = $this->lang->line('transports_receipt');
		$data['transaction_time'] = date($this->config->item('dateformat') . ' ' . $this->config->item('timeformat'));
		$data['transaction_date'] = date($this->config->item('dateformat'));
		$data['show_stock_locations'] = $this->Stock_location->show_locations('transports');
		$data['comments'] = $this->transport_lib->get_comment();
		$data['payments'] = $this->transport_lib->get_payments();
		$data['amount_change'] = $this->transport_lib->get_amount_due() * -1;
		$data['amount_due'] = $this->transport_lib->get_amount_due();
		$employee_id = $this->Employee->get_logged_in_employee_info()->person_id;
		$employee_info = $this->Employee->get_info($employee_id);
		$data['employee'] = $employee_info->first_name  . ' ' . $employee_info->last_name[0];
		$data['company_info'] = implode("\n", array(
			$this->config->item('address'),
			$this->config->item('phone'),
			$this->config->item('account_number')
		));
		$stock_location_id = $this->transport_lib->get_stock_location();
		$stock_location_info = $this->_load_stock_location_data($stock_location_id, $data);
		$invoice_number = $this->_substitute_invoice_number($stock_location_info);

		if($this->transport_lib->is_invoice_number_enabled() && $this->Transport->check_invoice_number_exists($invoice_number))
		{
			$data['error'] = $this->lang->line('transports_invoice_number_duplicate');

			$this->_reload($data);
		}
		elseif ($this->transport_lib->get_mailState()!=3) {
			$data['error'] = "还没有完成收货";$this->_reload($data);
		}
		else 
		{
			$invoice_number = $this->transport_lib->is_invoice_number_enabled() ? $invoice_number : NULL;
			$data['invoice_number'] = $invoice_number;
			$data['transport_id_num'] = $this->Transport->save($data['cart'], $stock_location_id, $employee_id, $data['comments'], $invoice_number, $data['payments']);
			$data['transport_id'] = 'POS ' . $data['transport_id_num'];
			
			$data = $this->xss_clean($data);
			
			if($data['transport_id_num'] == -1)
			{
				$data['error_message'] = $this->lang->line('transports_transaction_failed');
			}
			else
			{
				$data['barcode'] = $this->barcode_lib->generate_receipt_barcode($data['transport_id']);
			}

			$data['cur_giftcard_value'] = $this->transport_lib->get_giftcard_remainder();
			$data['print_after_transport'] = $this->transport_lib->is_print_after_transaction();
			$data['email_receipt'] = $this->transport_lib->get_email_receipt();
			
			if($this->transport_lib->is_invoice_number_enabled())
			{
				$this->load->view('transaction/invoice', $data);
			}
			else
			{
				$this->load->view('transaction/receipt', $data);
			}

			$this->transport_lib->clear_all();
		}*/
	}

	public function send_invoice($transport_id)
	{
		$transport_data = $this->_load_transport_data($transport_id);

		$result = FALSE;
		$message = $this->lang->line('transports_invoice_no_email');

		if(!empty($transport_data['stock_location_email']))
		{
			$to = $transport_data['stock_location_email'];
			$subject = $this->lang->line('transports_invoice') . ' ' . $transport_data['invoice_number'];

			$text = $this->config->item('invoice_email_message');
			$text = str_replace('$INV', $transport_data['invoice_number'], $text);
			$text = str_replace('$CO', 'POS ' . $transport_data['transport_id'], $text);
			$text = $this->_substitute_stock_location($text, (object) $transport_data);

			// generate email attachment: invoice in pdf format
			$html = $this->load->view('transaction/invoice_email', $transport_data, TRUE);
			// load pdf helper
			$this->load->helper(array('dompdf', 'file'));
			$filename = sys_get_temp_dir() . '/' . $this->lang->line('transports_invoice') . '-' . str_replace('/', '-' , $transport_data['invoice_number']) . '.pdf';
			if(file_put_contents($filename, pdf_create($html)) !== FALSE)
			{
				$result = $this->email_lib->sendEmail($to, $subject, $text, $filename);	
			}

			$message = $this->lang->line($result ? 'transports_invoice_sent' : 'transports_invoice_unsent') . ' ' . $to;
		}

		echo json_encode(array('success' => $result, 'message' => $message, 'id' => $transport_id));

		$this->transport_lib->clear_all();

		return $result;
	}

	public function send_receipt($transport_id)
	{
		$transport_data = $this->_load_transport_data($transport_id);

		$result = FALSE;
		$message = $this->lang->line('transports_receipt_no_email');

		if(!empty($transport_data['stock_location_email']))
		{
			$transport_data['barcode'] = $this->barcode_lib->generate_receipt_barcode($transport_data['transport_id']);

			$to = $transport_data['stock_location_email'];
			$subject = $this->lang->line('transports_receipt');

			$text = $this->load->view('transaction/receipt_email', $transport_data, TRUE);
			
			$result = $this->email_lib->sendEmail($to, $subject, $text);

			$message = $this->lang->line($result ? 'transports_receipt_sent' : 'transports_receipt_unsent') . ' ' . $to;
		}

		echo json_encode(array('success' => $result, 'message' => $message, 'id' => $transport_id));

		$this->transport_lib->clear_all();

		return $result;
	}

	private function _substitute_variable($text, $variable, $object, $function)
	{
		// don't query if this variable isn't used
		if(strstr($text, $variable))
		{
			$value = call_user_func(array($object, $function));
			$text = str_replace($variable, $value, $text);
		}

		return $text;
	}

	private function _substitute_stock_location($text, $stock_location_info)
	{
		// substitute stock_location info
		$stock_location_id = $this->transport_lib->get_stock_location();
		if($stock_location_id != -1 && $stock_location_info != '')
		{
			$text = str_replace('$CU', $stock_location_info->first_name . ' ' . $stock_location_info->last_name, $text);
			$words = preg_split("/\s+/", trim($stock_location_info->first_name . ' ' . $stock_location_info->last_name));
			$acronym = '';
			foreach($words as $w)
			{
				$acronym .= $w[0];
			}
			$text = str_replace('$CI', $acronym, $text);
		}

		return $text;
	}

	private function _is_custom_invoice_number($stock_location_info)
	{
		$invoice_number = $this->config->config['transports_invoice_format'];
		$invoice_number = $this->_substitute_variables($invoice_number, $stock_location_info);

		return $this->transport_lib->get_invoice_number() != $invoice_number;
	}

	private function _substitute_variables($text, $stock_location_info)
	{
		$text = $this->_substitute_variable($text, '$YCO', $this->Transport, 'get_invoice_number_for_year');
		$text = $this->_substitute_variable($text, '$CO', $this->Transport , 'get_invoice_count');
		$text = $this->_substitute_variable($text, '$SCO', $this->Transport_suspended, 'get_invoice_count');
		$text = strftime($text);
		$text = $this->_substitute_stock_location($text, $stock_location_info);

		return $text;
	}

	private function _substitute_invoice_number($stock_location_info)
	{
		$invoice_number = $this->config->config['transports_invoice_format'];
		$invoice_number = $this->_substitute_variables($invoice_number, $stock_location_info);
		$this->transport_lib->set_invoice_number($invoice_number, TRUE);

		return $this->transport_lib->get_invoice_number();
	}

	private function _load_stock_location_data($stock_location_id, &$data, $totals = FALSE)
	{	
		$stock_location_info = '';

		if($stock_location_id != -1)
		{
			$stock_location_info = $this->Stock_location->get_info($stock_location_id);
			/*if(isset($stock_location_info->company_name))
			{
				$data['partner'] = $stock_location_info->company_name;
			}
			else
			{
				$data['partner'] = $stock_location_info->first_name . ' ' . $stock_location_info->last_name;
			}*/
			$data['partner'] = $stock_location_info->first_name;
			if(isset($stock_location_info->company_name)){$data['partner'].='('.$stock_location_info->company_name.')';}

			$data['first_name'] = $stock_location_info->first_name;
			$data['last_name'] = '';//$stock_location_info->last_name;
			$data['partner_email'] = $stock_location_info->email;
			$data['partner_address'] = $stock_location_info->address_1;
			if(!empty($stock_location_info->zip) or !empty($stock_location_info->city))
			{
				$data['partner_location'] = $stock_location_info->zip . ' ' . $stock_location_info->city;				
			}
			else
			{
				$data['partner_location'] = '';
			}
			$data['partner_account_number'] = $stock_location_info->account_number;
			$data['partner_discount_percent'] = 0;//$stock_location_info->discount_percent;
			/*if($totals)
			{
				$cust_totals = $this->Stock_location->get_totals($stock_location_id);

				$data['stock_location_total'] = $cust_totals->total;
			}*/
			$data['partner_info'] = implode("\n", array(
				$data['partner'],
				$data['partner_address'],
				$data['partner_location'],
				$data['partner_account_number']
			));
		}

		return $stock_location_info;
	}

	private function _load_transport_data($transport_id)
	{
		$this->transport_lib->clear_all();
		$transport_info = $this->Transport->get_info($transport_id)->row_array();
		$this->transport_lib->copy_entire_transaction($transport_id);
		$data = array();
		$data['cart'] = $this->transport_lib->get_cart();
		$data['payments'] = $this->transport_lib->get_payments();
		$data['subtotal'] = $this->transport_lib->get_subtotal();
		$data['discounted_subtotal'] = $this->transport_lib->get_subtotal(TRUE);
		$data['tax_exclusive_subtotal'] = $this->transport_lib->get_subtotal(TRUE, TRUE);
		$data['taxes'] = $this->transport_lib->get_taxes();
		$data['total'] = $this->transport_lib->get_total();
		$data['discount'] = $this->transport_lib->get_discount();
		$data['receipt_title'] = $this->lang->line('transports_receipt');
		$data['transaction_time'] = date($this->config->item('dateformat') . ' ' . $this->config->item('timeformat'), strtotime($transport_info['transport_time']));
		$data['transaction_date'] = date($this->config->item('dateformat'), strtotime($transport_info['transport_time']));
		$data['show_stock_locations'] = $this->Stock_location->show_locations('transports');
		$data['amount_change'] = $this->transport_lib->get_amount_due() * -1;
		$data['amount_due'] = $this->transport_lib->get_amount_due();
		$employee_info = $this->Employee->get_info($this->transport_lib->get_employee());
		$data['employee'] = $employee_info->first_name . ' ' . $employee_info->last_name[0];
		$this->_load_stock_location_data($this->transport_lib->get_stock_location(), $data);

		$data['transport_id_num'] = $transport_id;
		$data['transport_id'] = 'POS ' . $transport_id;
		$data['comments'] = $transport_info['comment'];
		$data['invoice_number'] = $transport_info['invoice_number'];
		$data['company_info'] = implode("\n", array(
			$this->config->item('address'),
			$this->config->item('phone'),
			$this->config->item('account_number')
		));
		$data['barcode'] = $this->barcode_lib->generate_receipt_barcode($data['transport_id']);
		$data['print_after_transport'] = FALSE;

		return $this->xss_clean($data);
	}

	private function _reload($data = array())
	{		
		$data['cart'] = $this->transport_lib->get_cart();	 
		$data['modes'] = array('transport' => $this->lang->line('transports_transport'), 'return' => $this->lang->line('transports_return'));
		$data['mode'] = $this->transport_lib->get_mode();
		$data['stock_locations'] = $this->Stock_location->get_allowed_locations('transports');
		$data['stock_location'] = $this->transport_lib->get_transaction_location();
		$data['subtotal'] = $this->transport_lib->get_subtotal(TRUE);
		$data['tax_exclusive_subtotal'] = $this->transport_lib->get_subtotal(TRUE, TRUE);
		$data['taxes'] = $this->transport_lib->get_taxes();
		$data['discount'] = $this->transport_lib->get_discount();
		$data['total'] = $this->transport_lib->get_total();
		$data['comment'] = $this->transport_lib->get_comment();
		$data['email_receipt'] = $this->transport_lib->get_email_receipt();
		$data['payments_total'] = $this->transport_lib->get_payments_total();
		$data['amount_due'] = $this->transport_lib->get_amount_due();
		$data['payments'] = $this->transport_lib->get_payments();
		$data['payment_options'] = $this->Transport->get_payment_options();

		$data['items_module_allowed'] = $this->Employee->has_grant('items', $this->Employee->get_logged_in_employee_info()->person_id);

		$stock_location_info = $this->_load_stock_location_data($this->transport_lib->get_stock_location(), $data, FALSE);
		$data['invoice_number'] = $this->_substitute_invoice_number($stock_location_info);
		$data['invoice_number_enabled'] = $this->transport_lib->is_invoice_number_enabled();
		$data['print_after_transport'] = $this->transport_lib->is_print_after_transaction();
		$data['payments_cover_total'] = $this->transport_lib->get_amount_due() <= 0;
		
		$data['mail_state']=$this->transport_lib->get_mailState();
		$transport_id=$this->transport_lib->get_transaction_id();
		if(FALSE!=$transport_id){$data['transaction_id']=$transport_id;}

		$data['partner_type']='stock_location';

		$data = $this->xss_clean($data);

		$this->check_stock_location($data);

		$this->load->view('transaction/register', $data);
	}

	public function show_help(){
		echo $this->lang->line('transports_help');
	}

	public function receipt($transport_id)
	{
		$data = $this->_load_transport_data($transport_id);

		$this->load->view('transaction/receipt', $data);

		$this->transport_lib->clear_all();
	}

	public function invoice($transport_id)
	{
		$data = $this->_load_transport_data($transport_id);

		$this->load->view('transaction/invoice', $data);

		$this->transport_lib->clear_all();
	}

	public function edit($transport_id)
	{
		$data = array();

		$data['employees'] = array();
		foreach($this->Employee->get_all()->result() as $employee)
		{
			foreach(get_object_vars($employee) as $property => $value)
			{
				$employee->$property = $this->xss_clean($value);
			}
			
			$data['employees'][$employee->person_id] = $employee->first_name . ' ' . $employee->last_name;
		}

		$transport_info = $this->xss_clean($this->Transport->get_info($transport_id)->row_array());

		$transport_info['transaction_id']=$transport_info['transport_id'];
		$transport_info['transaction_time']=$transport_info['transport_time'];

		$employee=$this->Employee->get_info($transport_info['employee_id']);
		$data['employee_name']=$employee->first_name;

		$data['transaction_info'] = $transport_info;

		/*$data['selected_stock_location_name'] = $transport_info['stock_location_name'];
		$data['selected_stock_location_id'] = $transport_info['stock_location_id'];
		
		$data['payments'] = array();
		foreach($this->Transport->get_transport_payments($transport_id)->result() as $payment)
		{
			foreach(get_object_vars($payment) as $property => $value)
			{
				$payment->$property = $this->xss_clean($value);
			}
			
			$data['payments'][] = $payment;
		}
		
		// don't allow gift card to be a payment option in a transport transaction edit because it's a complex change
		$data['payment_options'] = $this->xss_clean($this->Transport->get_payment_options(FALSE));*/
		
		$this->load->view('transaction/form', $data);
	}

	public function delete($transport_id = -1, $update_inventory = TRUE)
	{
		$employee_id = $this->Employee->get_logged_in_employee_info()->person_id;
		$transport_ids = $transport_id == -1 ? $this->input->post('ids') : array($transport_id);

		if($this->Transport->delete_list($transport_ids, $employee_id, $update_inventory))
		{
			echo json_encode(array('success' => TRUE, 'message' => $this->lang->line('transports_successfully_deleted') . ' ' .
							count($transport_ids) . ' ' . $this->lang->line('transports_one_or_multiple'), 'ids' => $transport_ids));
		}
		else
		{
			echo json_encode(array('success' => FALSE, 'message' => $this->lang->line('transports_unsuccessfully_deleted')));
		}
	}

	public function save($transport_id = -1)
	{
		$newdate = $this->input->post('date');

		$date_formatter = date_create_from_format($this->config->item('dateformat') . ' ' . $this->config->item('timeformat'), $newdate);

		$transport_data = array(
			'transport_time' => $date_formatter->format('Y-m-d H:i:s'),
			'stock_location_id' => $this->input->post('stock_location_id') != '' ? $this->input->post('stock_location_id') : NULL,
			'employee_id' => $this->input->post('employee_id'),
			'comment' => $this->input->post('comment'),
			'invoice_number' => $this->input->post('invoice_number') != '' ? $this->input->post('invoice_number') : NULL
		);

		// go through all the payment type input from the form, make sure the form matches the name and iterator number
		$payments = array();
		$number_of_payments = $this->input->post('number_of_payments');
		for ($i = 0; $i < $number_of_payments; ++$i)
		{
			$payment_amount = $this->input->post('payment_amount_' . $i);
			$payment_type = $this->input->post('payment_type_' . $i);
			// remove any 0 payment if by mistake any was introduced at transport time
			if($payment_amount != 0)
			{
				// search for any payment of the same type that was already added, if that's the case add up the new payment amount
				$key = FALSE;
				if(!empty($payments))
				{
					// search in the multi array the key of the entry containing the current payment_type
					// NOTE: in PHP5.5 the array_map could be replaced by an array_column
					$key = array_search($payment_type, array_map(function($v){return $v['payment_type'];}, $payments));
				}

				// if no previous payment is found add a new one
				if($key === FALSE)
				{
					$payments[] = array('payment_type' => $payment_type, 'payment_amount' => $payment_amount);
				}
				else
				{
					// add up the new payment amount to an existing payment type
					$payments[$key]['payment_amount'] += $payment_amount;
				}
			}
		}

		if($this->Transport->update($transport_id, $transport_data, $payments))
		{
			echo json_encode(array('success' => TRUE, 'message' => $this->lang->line('transports_successfully_updated'), 'id' => $transport_id));
		}
		else
		{
			echo json_encode(array('success' => FALSE, 'message' => $this->lang->line('transports_unsuccessfully_updated'), 'id' => $transport_id));
		}
	}

	public function cancel()
	{
		$this->transport_lib->clear_all();

		$this->_reload($this->transport_lib->mark_unedited_transaction());
	}

	public function suspend()
	{
		$cart = $this->transport_lib->get_cart();
		$payments = $this->transport_lib->get_payments();
		$employee_id = $this->Employee->get_logged_in_employee_info()->person_id;
		$stock_location_id = $this->transport_lib->get_stock_location();
		$stock_location_info = $this->Stock_location->get_info($stock_location_id);
		$invoice_number = $this->_is_custom_invoice_number($stock_location_info) ? $this->transport_lib->get_invoice_number() : NULL;
		$comment = $this->transport_lib->get_comment();

		$infos=array();
		$infos['mail_state']=$this->transport_lib->get_mailState();
		$infos['cart_diff']=$this->transport_lib->get_cart_diff();
		$infos['transport_items_location']=$this->transport_lib->get_transaction_items_location();

		if(empty($stock_location_id)||$stock_location_id<0){
        $this->_reload($this->transport_lib->mark_unedited_transaction(array('error'=>'请填写收获仓库地址')));return;
        }
        if($stock_location_id==$infos['transport_items_location']){
            $this->_reload($this->transport_lib->mark_unedited_transaction(
                    array('error'=>'收获仓库不能为当前所在的仓库')));return;    
        }

		$data = array();
		$transport_id=$this->Transport_suspended->save($cart, $stock_location_id, $employee_id, $comment, 
			$invoice_number, $payments,$infos,$this->transport_lib->get_transaction_id());
		if( $transport_id == '-1')
		{
			$data['error'] = $this->lang->line('transports_unsuccessfully_suspended_transport');
		}
		else
		{
			$this->transport_lib->set_transaction_id($transport_id);
			$this->transport_lib->clear_all();
			$this->transport_lib->copy_entire_suspended_transaction($transport_id);

			$data['success'] = $this->lang->line('transports_successfully_suspended_transport');
		}

		//$this->transport_lib->clear_all();

		$this->_reload($this->transport_lib->mark_unedited_transaction($data));
	}
	
	public function suspended()
	{	
		/*$data = array();
		$data['suspended_transports'] = $this->xss_clean($this->Transport_suspended->get_all()->result_array());

		$this->load->view('transaction/suspended', $data);*/
	}
	
	public function update(){
		$transport_id = $this->input->get('id');

		$this->transport_lib->clear_all();
		$this->transport_lib->copy_entire_suspended_transaction($transport_id);
		//$this->Transport_suspended->delete_together($transport_id);

		$this->_reload($this->transport_lib->mark_unedited_transaction());
	}
	public function unsuspend()
	{
		/*$transport_id = $this->input->post('suspended_transport_id');

		$this->transport_lib->clear_all();
		$this->transport_lib->copy_entire_suspended_transaction($transport_id);
		//$this->Transport_suspended->delete_together($transport_id);

		$this->_reload($this->transport_lib->mark_unedited_transaction());*/
	}
	
	public function check_invoice_number()
	{
		$transport_id = $this->input->post('transport_id');
		$invoice_number = $this->input->post('invoice_number');
		$exists = !empty($invoice_number) && $this->Transport->check_invoice_number_exists($invoice_number, $transport_id);

		echo !$exists ? 'true' : 'false';
	}

	public function change_transport_to_current_location(){
		$items = $this->transport_lib->get_cart();
		$location_id=$this->Stock_location->get_default_location_id();

		foreach ($items as $key => $item) {
			$item['item_location']=$location_id;
			$item['stock_name']=$this->Stock_location->get_location_name_session($location_id);

			if(1==$item['is_infinite']){continue;}
			$item['in_stock']=$this->Item_quantity->get_item_quantity($item['item_id'], $location_id)->quantity;

			$items[$key]=$item;
		}

		$this->transport_lib->set_cart($items);$this->transport_lib->clear_transaction_items_location();
		$this->_reload();
	}
}
?>
