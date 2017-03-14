<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once("Secure_Controller.php");

class Receivings extends Secure_Controller
{
	public function __construct()
	{
		parent::__construct('receivings');

		$this->load->library('receiving_lib');
		//$this->load->library('barcode_lib');
		//$this->load->library('email_lib');
	}

	private function check_stock_location(&$data){
		$location_id=$this->receiving_lib->get_transaction_items_location();
		$location_id_default=$this->Stock_location->get_default_location_id();
		//echo $location_id;echo $location_id_default;
		if($location_id_default!=$location_id){
			$location_name=$this->Stock_location->get_location_name_session($location_id);
			$location_name_default=$this->Stock_location->get_location_name_session($location_id_default);
			$data['warning']='这笔交易是在“'.$location_name.'”进行的，不是当前所查看的仓库“'.
			$location_name_default.'”。需要将仓库切换到“'.$location_name.'”才能修改，或者点击'.
			'<a href="receivings/change_receiving_to_current_location" title="下面所列的所有交易物品所在的仓库地址将改成'.$location_name_default.'">这里</a>'.
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

		$from_receiving_id  = $this->input->get('from_id');

		/*if(!$this->Employee->has_grant('reports_receivings', $person_id))
		{
			redirect('no_access/receivings/reports_receivings');
		}
		else*/
		{
			$data['table_headers'] = get_receivings_manage_table_headers();

			// filters that will be loaded in the multiselect dropdown
			if($this->config->item('invoice_enable') == TRUE)
			{
				$data['filters'] = array('only_cash' => $this->lang->line('receivings_cash_filter'),
										'only_invoices' => $this->lang->line('receivings_invoice_filter'));
			}
			else
			{
				$data['filters'] = array('only_cash' => $this->lang->line('receivings_cash_filter'));
			}

			if(!empty($from_receiving_id)){$data['from_receiving_id']=$from_receiving_id;}
			$data['last_search']=$this->receiving_lib->get_search_params();

			$this->load->view('transaction/manage', $data);
		}
	}
	
	public function get_row($row_id)
	{
		$receiving_info = $this->Receiving->get_info($row_id)->row();
		$data_row = $this->xss_clean(get_receiving_data_row($receiving_info, $this));

		echo json_encode($data_row);
	}

	public function search()
	{
		$this->receiving_lib->save_search_params();

		$search = $this->input->get('search');
		$limit  = $this->input->get('limit');
		$offset = $this->input->get('offset');
		$sort   = $this->input->get('sort');
		$order  = $this->input->get('order');

		$filters = array('receiving_type' => 'all',
						'location_id' => 'all',
						'start_date' => $this->input->get('start_date'),
						'end_date' => $this->input->get('end_date'),
						'only_cash' => FALSE,
						'only_invoices' => $this->config->item('invoice_enable') && $this->input->get('only_invoices'),
						'is_valid_receipt' => $this->Receiving->is_valid_receipt($search));

		// check if any filter is set in the multiselect dropdown
		$filledup = array_fill_keys($this->input->get('filters'), TRUE);
		$filters = array_merge($filters, $filledup);

		$receivings = $this->Receiving->search($search, $filters, $limit, $offset, $sort, $order);
		$total_rows = $this->Receiving->get_found_rows($search, $filters);
		//$payments = $this->Receiving->get_payments_summary($search, $filters);
		//$payment_summary = $this->xss_clean(get_receivings_manage_payments_summary($payments, $receivings, $this));

		$data_rows = array();
		foreach($receivings->result() as $receiving)
		{
			$data_rows[] = $this->xss_clean(get_receiving_data_row($receiving, $this));
		}

		/*if($total_rows > 0)
		{
			$data_rows[] = $this->xss_clean(get_receiving_data_last_row($receivings, $this));
		}*/

		echo json_encode(array('total' => $total_rows, 'rows' => $data_rows));//, 'payment_summary' => $payment_summary)
	}

	public function item_search()
	{
		$suggestions = array();
		$receipt = $search = $this->input->get('term') != '' ? $this->input->get('term') : NULL;

		if($this->receiving_lib->get_mode() == 'return' && $this->Receiving->is_valid_receipt($receipt))
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
		
		$suggestions = $this->xss_clean($this->Receiving->get_search_suggestions($search));
		
		echo json_encode($suggestions);
	}

	public function select_supplier()
	{
		$supplier_id = $this->input->post('supplier');
		if($this->Supplier->exists($supplier_id))
		{
			$this->receiving_lib->set_supplier($supplier_id);

			/*$discount_percent = $this->Supplier->get_info($supplier_id)->discount_percent;

			// apply supplier default discount to items that have 0 discount
			if($discount_percent != '')
			{	
				$this->receiving_lib->apply_supplier_discount($discount_percent);
			}*/
		}
		
		$this->_reload();
	}

	public function change_mode()
	{
		$stock_location = $this->input->post('stock_location');
		if (!$stock_location || $stock_location == $this->receiving_lib->get_transaction_location())
		{
			$mode = $this->input->post('mode');
			$this->receiving_lib->set_mode($mode);
		} 
		elseif($this->Stock_location->is_allowed_location($stock_location, 'receivings'))
		{
			$this->receiving_lib->set_transaction_location($stock_location);
		}

		$this->_reload();
	}
	
	public function set_comment() 
	{
		$this->receiving_lib->set_comment($this->input->post('comment'));
	}
	public function set_mailState() 
	{
		$this->receiving_lib->set_mailState($this->input->post('mail_state'));
	}
	
	public function set_invoice_number()
	{
		$this->receiving_lib->set_invoice_number($this->input->post('receivings_invoice_number'));
	}
	
	public function set_invoice_number_enabled()
	{
		$this->receiving_lib->set_invoice_number_enabled($this->input->post('receivings_invoice_number_enabled'));
	}
	
	public function set_print_after_transaction()
	{
		$this->receiving_lib->set_print_after_transaction($this->input->post('receivings_print_after_receiving'));
	}
	
	public function set_email_receipt()
	{
 		$this->receiving_lib->set_email_receipt($this->input->post('email_receipt'));
	}

	// Multiple Payments
	public function add_payment()
	{
		$data = array();
		$this->form_validation->set_rules('amount_tendered', 'lang:receivings_amount_tendered', 'trim|required|callback_numeric');

		$payment_type = $this->input->post('payment_type');

		if($this->form_validation->run() == FALSE)
		{
			if($payment_type == $this->lang->line('receivings_giftcard'))
			{
				$data['error'] = $this->lang->line('receivings_must_enter_numeric_giftcard');
			}
			else
			{
				$data['error'] = $this->lang->line('receivings_must_enter_numeric');
			}
		}
		else
		{
			if($payment_type == $this->lang->line('receivings_giftcard'))
			{
				// in case of giftcard payment the register input amount_tendered becomes the giftcard number
				$giftcard_num = $this->input->post('amount_tendered');

				$payments = $this->receiving_lib->get_payments();
				$payment_type = $payment_type . ':' . $giftcard_num;
				$current_payments_with_giftcard = isset($payments[$payment_type]) ? $payments[$payment_type]['payment_amount'] : 0;
				$cur_giftcard_value = $this->Giftcard->get_giftcard_value($giftcard_num);
				
				if(($cur_giftcard_value - $current_payments_with_giftcard) <= 0)
				{
					$data['error'] = $this->lang->line('giftcards_remaining_balance', $giftcard_num, to_currency($cur_giftcard_value));
				}
				else
				{
					$new_giftcard_value = $this->Giftcard->get_giftcard_value($giftcard_num) - $this->receiving_lib->get_amount_due();
					$new_giftcard_value = $new_giftcard_value >= 0 ? $new_giftcard_value : 0;
					$this->receiving_lib->set_giftcard_remainder($new_giftcard_value);
					$new_giftcard_value = str_replace('$', '\$', to_currency($new_giftcard_value));
					$data['warning'] = $this->lang->line('giftcards_remaining_balance', $giftcard_num, $new_giftcard_value);
					$amount_tendered = min( $this->receiving_lib->get_amount_due(), $this->Giftcard->get_giftcard_value($giftcard_num) );

					$this->receiving_lib->add_payment($payment_type, $amount_tendered);
				}
			}
			else
			{
				$amount_tendered = $this->input->post('amount_tendered');

				$this->receiving_lib->add_payment($payment_type, $amount_tendered);
			}
		}

		$this->_reload($data);
	}

	// Multiple Payments
	public function delete_payment($payment_id)
	{
		$this->receiving_lib->delete_payment($payment_id);

		$this->_reload();
	}

	public function add()
	{
		$data = array();
		
		$discount = 0;

		// check if any discount is assigned to the selected supplier
		/*$supplier_id = $this->receiving_lib->get_supplier();
		if($supplier_id != -1)
		{
			// load the supplier discount if any
			$discount_percent = $this->Supplier->get_info($supplier_id)->discount_percent;
			if($discount_percent != '')
			{
				$discount = $discount_percent;
			}
		}

		// if the supplier discount is 0 or no supplier is selected apply the default receivings discount
		if($discount == 0)
		{
			$discount = $this->config->item('default_receivings_discount');
		}*/

		//$mode = $this->receiving_lib->get_mode();
		$quantity = 1;//($mode == 'return') ? -1 : 1;
		$item_location = $this->receiving_lib->get_transaction_location();
		$item_id_or_number_or_item_kit_or_receipt = $this->input->post('item');

		/*if($mode == 'return' && $this->Receiving->is_valid_receipt($item_id_or_number_or_item_kit_or_receipt))
		{
			$this->receiving_lib->return_entire_transaction($item_id_or_number_or_item_kit_or_receipt);
		}
		else*/if($this->Item_kit->is_valid_item_kit($item_id_or_number_or_item_kit_or_receipt))
		{
			if(!$this->receiving_lib->add_item_kit($item_id_or_number_or_item_kit_or_receipt, $item_location, $discount))
			{
				$data['error'] = $this->lang->line('receivings_unable_to_add_item');
			}
		}
		else
		{
			if(!$this->receiving_lib->add_item($item_id_or_number_or_item_kit_or_receipt, $quantity, $item_location, $discount))
			{
				$data['error'] = $this->lang->line('receivings_unable_to_add_item');
			}
			else
			{
				$data['warning'] = $this->receiving_lib->out_of_stock($item_id_or_number_or_item_kit_or_receipt, $item_location);
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
			$this->receiving_lib->edit_item($item_id, $description, $serialnumber, $quantity, $discount, $price);
		}
		else
		{
			$data['error'] = $this->lang->line('receivings_error_editing_item');
		}

		$data['warning'] = $this->receiving_lib->out_of_stock($this->receiving_lib->get_item_id($item_id), $item_location);

		$this->_reload($data);
	}

	public function delete_item($item_number)
	{
		$this->receiving_lib->delete_item($item_number);

		$this->_reload();
	}

	public function remove_supplier()
	{
		$this->receiving_lib->clear_giftcard_remainder();
		$this->receiving_lib->clear_invoice_number();
		$this->receiving_lib->remove_supplier();

		$this->_reload();
	}

	public function complete()
	{return;
		/*$data = array();

		$data['cart'] = $this->receiving_lib->get_cart();
		$data['subtotal'] = $this->receiving_lib->get_subtotal();
		$data['discounted_subtotal'] = $this->receiving_lib->get_subtotal(TRUE);
		$data['tax_exclusive_subtotal'] = $this->receiving_lib->get_subtotal(TRUE, TRUE);
		$data['taxes'] = $this->receiving_lib->get_taxes();
		$data['total'] = $this->receiving_lib->get_total();
		$data['discount'] = $this->receiving_lib->get_discount();
		$data['receipt_title'] = $this->lang->line('receivings_receipt');
		$data['transaction_time'] = date($this->config->item('dateformat') . ' ' . $this->config->item('timeformat'));
		$data['transaction_date'] = date($this->config->item('dateformat'));
		$data['show_stock_locations'] = $this->Stock_location->show_locations('receivings');
		$data['comments'] = $this->receiving_lib->get_comment();
		$data['payments'] = $this->receiving_lib->get_payments();
		$data['amount_change'] = $this->receiving_lib->get_amount_due() * -1;
		$data['amount_due'] = $this->receiving_lib->get_amount_due();
		$employee_id = $this->Employee->get_logged_in_employee_info()->person_id;
		$employee_info = $this->Employee->get_info($employee_id);
		$data['employee'] = $employee_info->first_name  . ' ' . $employee_info->last_name[0];
		$data['company_info'] = implode("\n", array(
			$this->config->item('address'),
			$this->config->item('phone'),
			$this->config->item('account_number')
		));
		$supplier_id = $this->receiving_lib->get_supplier();
		$supplier_info = $this->_load_supplier_data($supplier_id, $data);
		$invoice_number = $this->_substitute_invoice_number($supplier_info);

		if($this->receiving_lib->is_invoice_number_enabled() && $this->Receiving->check_invoice_number_exists($invoice_number))
		{
			$data['error'] = $this->lang->line('receivings_invoice_number_duplicate');

			$this->_reload($data);
		}
		elseif ($this->receiving_lib->get_mailState()!=3) {
			$data['error'] = "还没有完成收货";$this->_reload($data);
		}
		else 
		{
			$invoice_number = $this->receiving_lib->is_invoice_number_enabled() ? $invoice_number : NULL;
			$data['invoice_number'] = $invoice_number;
			$data['receiving_id_num'] = $this->Receiving->save($data['cart'], $supplier_id, $employee_id, $data['comments'], $invoice_number, $data['payments']);
			$data['receiving_id'] = 'POS ' . $data['receiving_id_num'];
			
			$data = $this->xss_clean($data);
			
			if($data['receiving_id_num'] == -1)
			{
				$data['error_message'] = $this->lang->line('receivings_transaction_failed');
			}
			else
			{
				$data['barcode'] = $this->barcode_lib->generate_receipt_barcode($data['receiving_id']);
			}

			$data['cur_giftcard_value'] = $this->receiving_lib->get_giftcard_remainder();
			$data['print_after_receiving'] = $this->receiving_lib->is_print_after_transaction();
			$data['email_receipt'] = $this->receiving_lib->get_email_receipt();
			
			if($this->receiving_lib->is_invoice_number_enabled())
			{
				$this->load->view('transaction/invoice', $data);
			}
			else
			{
				$this->load->view('transaction/receipt', $data);
			}

			$this->receiving_lib->clear_all();
		}*/
	}

	public function send_invoice($receiving_id)
	{
		$receiving_data = $this->_load_receiving_data($receiving_id);

		$result = FALSE;
		$message = $this->lang->line('receivings_invoice_no_email');

		if(!empty($receiving_data['supplier_email']))
		{
			$to = $receiving_data['supplier_email'];
			$subject = $this->lang->line('receivings_invoice') . ' ' . $receiving_data['invoice_number'];

			$text = $this->config->item('invoice_email_message');
			$text = str_replace('$INV', $receiving_data['invoice_number'], $text);
			$text = str_replace('$CO', 'POS ' . $receiving_data['receiving_id'], $text);
			$text = $this->_substitute_supplier($text, (object) $receiving_data);

			// generate email attachment: invoice in pdf format
			$html = $this->load->view('transaction/invoice_email', $receiving_data, TRUE);
			// load pdf helper
			$this->load->helper(array('dompdf', 'file'));
			$filename = sys_get_temp_dir() . '/' . $this->lang->line('receivings_invoice') . '-' . str_replace('/', '-' , $receiving_data['invoice_number']) . '.pdf';
			if(file_put_contents($filename, pdf_create($html)) !== FALSE)
			{
				$result = $this->email_lib->sendEmail($to, $subject, $text, $filename);	
			}

			$message = $this->lang->line($result ? 'receivings_invoice_sent' : 'receivings_invoice_unsent') . ' ' . $to;
		}

		echo json_encode(array('success' => $result, 'message' => $message, 'id' => $receiving_id));

		$this->receiving_lib->clear_all();

		return $result;
	}

	public function send_receipt($receiving_id)
	{
		$receiving_data = $this->_load_receiving_data($receiving_id);

		$result = FALSE;
		$message = $this->lang->line('receivings_receipt_no_email');

		if(!empty($receiving_data['supplier_email']))
		{
			$receiving_data['barcode'] = $this->barcode_lib->generate_receipt_barcode($receiving_data['receiving_id']);

			$to = $receiving_data['supplier_email'];
			$subject = $this->lang->line('receivings_receipt');

			$text = $this->load->view('transaction/receipt_email', $receiving_data, TRUE);
			
			$result = $this->email_lib->sendEmail($to, $subject, $text);

			$message = $this->lang->line($result ? 'receivings_receipt_sent' : 'receivings_receipt_unsent') . ' ' . $to;
		}

		echo json_encode(array('success' => $result, 'message' => $message, 'id' => $receiving_id));

		$this->receiving_lib->clear_all();

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

	private function _substitute_supplier($text, $supplier_info)
	{
		// substitute supplier info
		$supplier_id = $this->receiving_lib->get_supplier();
		if($supplier_id != -1 && $supplier_info != '')
		{
			$text = str_replace('$CU', $supplier_info->first_name . ' ' . $supplier_info->last_name, $text);
			$words = preg_split("/\s+/", trim($supplier_info->first_name . ' ' . $supplier_info->last_name));
			$acronym = '';
			foreach($words as $w)
			{
				$acronym .= $w[0];
			}
			$text = str_replace('$CI', $acronym, $text);
		}

		return $text;
	}

	private function _is_custom_invoice_number($supplier_info)
	{
		$invoice_number = $this->config->config['receivings_invoice_format'];
		$invoice_number = $this->_substitute_variables($invoice_number, $supplier_info);

		return $this->receiving_lib->get_invoice_number() != $invoice_number;
	}

	private function _substitute_variables($text, $supplier_info)
	{
		$text = $this->_substitute_variable($text, '$YCO', $this->Receiving, 'get_invoice_number_for_year');
		$text = $this->_substitute_variable($text, '$CO', $this->Receiving , 'get_invoice_count');
		$text = $this->_substitute_variable($text, '$SCO', $this->Receiving_suspended, 'get_invoice_count');
		$text = strftime($text);
		$text = $this->_substitute_supplier($text, $supplier_info);

		return $text;
	}

	private function _substitute_invoice_number($supplier_info)
	{
		$invoice_number = $this->config->config['receivings_invoice_format'];
		$invoice_number = $this->_substitute_variables($invoice_number, $supplier_info);
		$this->receiving_lib->set_invoice_number($invoice_number, TRUE);

		return $this->receiving_lib->get_invoice_number();
	}

	private function _load_supplier_data($supplier_id, &$data, $totals = FALSE)
	{	
		$supplier_info = '';

		if($supplier_id != -1)
		{
			$supplier_info = $this->Supplier->get_info($supplier_id);
			/*if(isset($supplier_info->company_name))
			{
				$data['partner'] = $supplier_info->company_name;
			}
			else
			{
				$data['partner'] = $supplier_info->first_name . ' ' . $supplier_info->last_name;
			}*/
			$data['partner'] = $supplier_info->first_name;
			if(isset($supplier_info->company_name)){$data['partner'].='('.$supplier_info->company_name.')';}

			$data['first_name'] = $supplier_info->first_name;
			$data['last_name'] = '';//$supplier_info->last_name;
			$data['partner_email'] = $supplier_info->email;
			$data['partner_address'] = $supplier_info->address_1;
			if(!empty($supplier_info->zip) or !empty($supplier_info->city))
			{
				$data['partner_location'] = $supplier_info->zip . ' ' . $supplier_info->city;				
			}
			else
			{
				$data['partner_location'] = '';
			}
			$data['partner_account_number'] = $supplier_info->account_number;
			$data['partner_discount_percent'] = 0;//$supplier_info->discount_percent;
			/*if($totals)
			{
				$cust_totals = $this->Supplier->get_totals($supplier_id);

				$data['supplier_total'] = $cust_totals->total;
			}*/
			$data['partner_info'] = implode("\n", array(
				$data['partner'],
				$data['partner_address'],
				$data['partner_location'],
				$data['partner_account_number']
			));
		}

		return $supplier_info;
	}

	private function _load_receiving_data($receiving_id)
	{
		$this->receiving_lib->clear_all();
		$receiving_info = $this->Receiving->get_info($receiving_id)->row_array();
		$this->receiving_lib->copy_entire_transaction($receiving_id);
		$data = array();
		$data['cart'] = $this->receiving_lib->get_cart();
		$data['payments'] = $this->receiving_lib->get_payments();
		$data['subtotal'] = $this->receiving_lib->get_subtotal();
		$data['discounted_subtotal'] = $this->receiving_lib->get_subtotal(TRUE);
		$data['tax_exclusive_subtotal'] = $this->receiving_lib->get_subtotal(TRUE, TRUE);
		$data['taxes'] = $this->receiving_lib->get_taxes();
		$data['total'] = $this->receiving_lib->get_total();
		$data['discount'] = $this->receiving_lib->get_discount();
		$data['receipt_title'] = $this->lang->line('receivings_receipt');
		$data['transaction_time'] = date($this->config->item('dateformat') . ' ' . $this->config->item('timeformat'), strtotime($receiving_info['receiving_time']));
		$data['transaction_date'] = date($this->config->item('dateformat'), strtotime($receiving_info['receiving_time']));
		$data['show_stock_locations'] = $this->Stock_location->show_locations('receivings');
		$data['amount_change'] = $this->receiving_lib->get_amount_due() * -1;
		$data['amount_due'] = $this->receiving_lib->get_amount_due();
		$employee_info = $this->Employee->get_info($this->receiving_lib->get_employee());
		$data['employee'] = $employee_info->first_name . ' ' . $employee_info->last_name[0];
		$this->_load_supplier_data($this->receiving_lib->get_supplier(), $data);

		$data['receiving_id_num'] = $receiving_id;
		$data['receiving_id'] = 'POS ' . $receiving_id;
		$data['comments'] = $receiving_info['comment'];
		$data['invoice_number'] = $receiving_info['invoice_number'];
		$data['company_info'] = implode("\n", array(
			$this->config->item('address'),
			$this->config->item('phone'),
			$this->config->item('account_number')
		));
		$data['barcode'] = $this->barcode_lib->generate_receipt_barcode($data['receiving_id']);
		$data['print_after_receiving'] = FALSE;

		return $this->xss_clean($data);
	}

	private function _reload($data = array())
	{		
		$data['cart'] = $this->receiving_lib->get_cart();	 
		$data['modes'] = array('receiving' => $this->lang->line('receivings_receiving'), 'return' => $this->lang->line('receivings_return'));
		$data['mode'] = $this->receiving_lib->get_mode();
		$data['stock_locations'] = $this->Stock_location->get_allowed_locations('receivings');
		$data['stock_location'] = $this->receiving_lib->get_transaction_location();
		$data['subtotal'] = $this->receiving_lib->get_subtotal(TRUE);
		$data['tax_exclusive_subtotal'] = $this->receiving_lib->get_subtotal(TRUE, TRUE);
		$data['taxes'] = $this->receiving_lib->get_taxes();
		$data['discount'] = $this->receiving_lib->get_discount();
		$data['total'] = $this->receiving_lib->get_total();
		$data['comment'] = $this->receiving_lib->get_comment();
		$data['email_receipt'] = $this->receiving_lib->get_email_receipt();
		$data['payments_total'] = $this->receiving_lib->get_payments_total();
		$data['amount_due'] = $this->receiving_lib->get_amount_due();
		$data['payments'] = $this->receiving_lib->get_payments();
		$data['payment_options'] = $this->Receiving->get_payment_options();

		$data['items_module_allowed'] = $this->Employee->has_grant('items', $this->Employee->get_logged_in_employee_info()->person_id);

		$supplier_info = $this->_load_supplier_data($this->receiving_lib->get_supplier(), $data, FALSE);
		$data['invoice_number'] = $this->_substitute_invoice_number($supplier_info);
		$data['invoice_number_enabled'] = $this->receiving_lib->is_invoice_number_enabled();
		$data['print_after_receiving'] = $this->receiving_lib->is_print_after_transaction();
		$data['payments_cover_total'] = $this->receiving_lib->get_amount_due() <= 0;
		
		$data['mail_state']=$this->receiving_lib->get_mailState();
		$receiving_id=$this->receiving_lib->get_transaction_id();
		if(FALSE!=$receiving_id){$data['transaction_id']=$receiving_id;}

		$data['partner_type']='supplier';

		$data = $this->xss_clean($data);

		$this->check_stock_location($data);

		$this->load->view('transaction/register', $data);
	}

	public function show_help(){
		echo $this->lang->line('receivings_help');
	}

	public function list_items(){
		$items=$this->receiving_lib->get_cart();
		$items=$this->receiving_lib->unfold_cart($items);

		echo '<table class="table"><thead><tr><th>名称</th><th>数量</th></tr></thead><tbody>';
		foreach ($items as $item) {
			$name=($this->Item->get_info($item['item_id'])->name);
			$id=$item['quantity'];

			echo "<tr><td>".$name."</td><td>".$id."</td></tr>";
		}
		echo "</tbody></table>";
	}

	public function receipt($receiving_id)
	{
		$data = $this->_load_receiving_data($receiving_id);

		$this->load->view('transaction/receipt', $data);

		$this->receiving_lib->clear_all();
	}

	public function invoice($receiving_id)
	{
		$data = $this->_load_receiving_data($receiving_id);

		$this->load->view('transaction/invoice', $data);

		$this->receiving_lib->clear_all();
	}

	public function edit($receiving_id)
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

		$receiving_info = $this->xss_clean($this->Receiving->get_info($receiving_id)->row_array());

		$receiving_info['transaction_id']=$receiving_info['receiving_id'];
		$receiving_info['transaction_time']=$receiving_info['receiving_time'];

		$employee=$this->Employee->get_info($receiving_info['employee_id']);
		$data['employee_name']=$employee->first_name;

		$data['transaction_info'] = $receiving_info;

		/*$data['selected_supplier_name'] = $receiving_info['supplier_name'];
		$data['selected_supplier_id'] = $receiving_info['supplier_id'];
		
		$data['payments'] = array();
		foreach($this->Receiving->get_receiving_payments($receiving_id)->result() as $payment)
		{
			foreach(get_object_vars($payment) as $property => $value)
			{
				$payment->$property = $this->xss_clean($value);
			}
			
			$data['payments'][] = $payment;
		}
		
		// don't allow gift card to be a payment option in a receiving transaction edit because it's a complex change
		$data['payment_options'] = $this->xss_clean($this->Receiving->get_payment_options(FALSE));*/
		
		$this->load->view('transaction/form', $data);
	}

	public function delete($receiving_id = -1, $update_inventory = TRUE)
	{
		$employee_id = $this->Employee->get_logged_in_employee_info()->person_id;
		$receiving_ids = $receiving_id == -1 ? $this->input->post('ids') : array($receiving_id);

		if($this->Receiving->delete_list($receiving_ids, $employee_id, $update_inventory))
		{
			echo json_encode(array('success' => TRUE, 'message' => $this->lang->line('receivings_successfully_deleted') . ' ' .
							count($receiving_ids) . ' ' . $this->lang->line('receivings_one_or_multiple'), 'ids' => $receiving_ids));
		}
		else
		{
			echo json_encode(array('success' => FALSE, 'message' => $this->lang->line('receivings_unsuccessfully_deleted')));
		}
	}

	public function save($receiving_id = -1)
	{
		$newdate = $this->input->post('date');

		$date_formatter = date_create_from_format($this->config->item('dateformat') . ' ' . $this->config->item('timeformat'), $newdate);

		$receiving_data = array(
			'receiving_time' => $date_formatter->format('Y-m-d H:i:s'),
			'supplier_id' => $this->input->post('supplier_id') != '' ? $this->input->post('supplier_id') : NULL,
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
			// remove any 0 payment if by mistake any was introduced at receiving time
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

		if($this->Receiving->update($receiving_id, $receiving_data, $payments))
		{
			echo json_encode(array('success' => TRUE, 'message' => $this->lang->line('receivings_successfully_updated'), 'id' => $receiving_id));
		}
		else
		{
			echo json_encode(array('success' => FALSE, 'message' => $this->lang->line('receivings_unsuccessfully_updated'), 'id' => $receiving_id));
		}
	}

	public function cancel()
	{
		$this->receiving_lib->clear_all();

		$this->_reload($this->receiving_lib->mark_unedited_transaction());
	}

	public function suspend()
	{
		$cart = $this->receiving_lib->get_cart();
		$payments = $this->receiving_lib->get_payments();
		$employee_id = $this->Employee->get_logged_in_employee_info()->person_id;
		$supplier_id = $this->receiving_lib->get_supplier();
		$supplier_info = $this->Supplier->get_info($supplier_id);
		$invoice_number = $this->_is_custom_invoice_number($supplier_info) ? $this->receiving_lib->get_invoice_number() : NULL;
		$comment = $this->receiving_lib->get_comment();

		$infos=array();
		$infos['mail_state']=$this->receiving_lib->get_mailState();
		$infos['cart_diff']=$this->receiving_lib->get_cart_diff();
		$infos['receiving_items_location']=$this->receiving_lib->get_transaction_items_location();

		//SAVE receiving to database
		$data = array();
		$receiving_id=$this->Receiving_suspended->save($cart, $supplier_id, $employee_id, $comment, 
			$invoice_number, $payments,$infos,$this->receiving_lib->get_transaction_id());
		if( $receiving_id == '-1')
		{
			$data['error'] = $this->lang->line('receivings_unsuccessfully_suspended_receiving');
		}
		else
		{
			$this->receiving_lib->set_transaction_id($receiving_id);
			$this->receiving_lib->clear_all();
			$this->receiving_lib->copy_entire_suspended_transaction($receiving_id);

			$data['success'] = $this->lang->line('receivings_successfully_suspended_receiving');
		}

		//$this->receiving_lib->clear_all();

		$this->_reload($this->receiving_lib->mark_unedited_transaction($data));
	}
	
	public function suspended()
	{	
		/*$data = array();
		$data['suspended_receivings'] = $this->xss_clean($this->Receiving_suspended->get_all()->result_array());

		$this->load->view('transaction/suspended', $data);*/
	}
	
	public function update(){
		$receiving_id = $this->input->get('id');

		$this->receiving_lib->clear_all();
		$this->receiving_lib->copy_entire_suspended_transaction($receiving_id);
		//$this->Receiving_suspended->delete_together($receiving_id);

		$this->_reload($this->receiving_lib->mark_unedited_transaction());
	}
	public function unsuspend()
	{
		/*$receiving_id = $this->input->post('suspended_receiving_id');

		$this->receiving_lib->clear_all();
		$this->receiving_lib->copy_entire_suspended_transaction($receiving_id);
		//$this->Receiving_suspended->delete_together($receiving_id);

		$this->_reload($this->receiving_lib->mark_unedited_transaction());*/
	}
	
	public function check_invoice_number()
	{
		$receiving_id = $this->input->post('receiving_id');
		$invoice_number = $this->input->post('invoice_number');
		$exists = !empty($invoice_number) && $this->Receiving->check_invoice_number_exists($invoice_number, $receiving_id);

		echo !$exists ? 'true' : 'false';
	}

	public function change_receiving_to_current_location(){
		$items = $this->receiving_lib->get_cart();
		$location_id=$this->Stock_location->get_default_location_id();

		foreach ($items as $key => $item) {
			$item['item_location']=$location_id;
			$item['stock_name']=$this->Stock_location->get_location_name_session($location_id);

			if(1==$item['is_infinite']){continue;}
			$item['in_stock']=$this->Item_quantity->get_item_quantity($item['item_id'], $location_id)->quantity;

			$items[$key]=$item;
		}

		$this->receiving_lib->set_cart($items);$this->receiving_lib->clear_transaction_items_location();
		$this->_reload();
	}
}
?>
