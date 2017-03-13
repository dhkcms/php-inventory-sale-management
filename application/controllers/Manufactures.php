<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once("Secure_Controller.php");

class Manufactures extends Secure_Controller
{
	public function __construct()
	{
		parent::__construct('manufactures');

		$this->load->library('manufacture_lib');
		//$this->load->library('barcode_lib');
		//$this->load->library('email_lib');
	}

	private function check_stock_location(&$data){
		$location_id=$this->manufacture_lib->get_transaction_items_location();
		$location_id_default=$this->Stock_location->get_default_location_id();
		//echo $location_id;echo $location_id_default;
		if($location_id_default!=$location_id){
			$location_name=$this->Stock_location->get_location_name_session($location_id);
			$location_name_default=$this->Stock_location->get_location_name_session($location_id_default);
			$data['warning']='这笔交易是在“'.$location_name.'”进行的，不是当前所查看的仓库“'.
			$location_name_default.'”。需要将仓库切换到“'.$location_name.'”才能修改，或者点击'.
			'<a href="manufactures/change_manufacture_to_current_location" title="下面所列的所有交易物品所在的仓库地址将改成'.$location_name_default.'">这里</a>'.
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

		$from_manufacture_id  = $this->input->get('from_id');

		/*if(!$this->Employee->has_grant('reports_manufactures', $person_id))
		{
			redirect('no_access/manufactures/reports_manufactures');
		}
		else*/
		{
			$data['table_headers'] = get_manufactures_manage_table_headers();

			// filters that will be loaded in the multiselect dropdown
			if($this->config->item('invoice_enable') == TRUE)
			{
				$data['filters'] = array('only_cash' => $this->lang->line('manufactures_cash_filter'),
										'only_invoices' => $this->lang->line('manufactures_invoice_filter'));
			}
			else
			{
				$data['filters'] = array('only_cash' => $this->lang->line('manufactures_cash_filter'));
			}

			if(!empty($from_manufacture_id)){$data['from_manufacture_id']=$from_manufacture_id;}
			$data['last_search']=$this->manufacture_lib->get_search_params();

			$this->load->view('transaction/manage', $data);
		}
	}
	
	public function get_row($row_id)
	{
		$manufacture_info = $this->Manufacture->get_info($row_id)->row();
		$data_row = $this->xss_clean(get_manufacture_data_row($manufacture_info, $this));

		echo json_encode($data_row);
	}

	public function search()
	{
		$this->manufacture_lib->save_search_params();

		$search = $this->input->get('search');
		$limit  = $this->input->get('limit');
		$offset = $this->input->get('offset');
		$sort   = $this->input->get('sort');
		$order  = $this->input->get('order');

		$filters = array('manufacture_type' => 'all',
						'location_id' => 'all',
						'start_date' => $this->input->get('start_date'),
						'end_date' => $this->input->get('end_date'),
						'only_cash' => FALSE,
						'only_invoices' => $this->config->item('invoice_enable') && $this->input->get('only_invoices'),
						'is_valid_receipt' => $this->Manufacture->is_valid_receipt($search));

		// check if any filter is set in the multiselect dropdown
		$filledup = array_fill_keys($this->input->get('filters'), TRUE);
		$filters = array_merge($filters, $filledup);

		$manufactures = $this->Manufacture->search($search, $filters, $limit, $offset, $sort, $order);
		$total_rows = $this->Manufacture->get_found_rows($search, $filters);
		//$payments = $this->Manufacture->get_payments_summary($search, $filters);
		//$payment_summary = $this->xss_clean(get_manufactures_manage_payments_summary($payments, $manufactures, $this));

		$data_rows = array();
		foreach($manufactures->result() as $manufacture)
		{
			$data_rows[] = $this->xss_clean(get_manufacture_data_row($manufacture, $this));
		}

		/*if($total_rows > 0)
		{
			$data_rows[] = $this->xss_clean(get_manufacture_data_last_row($manufactures, $this));
		}*/

		echo json_encode(array('total' => $total_rows, 'rows' => $data_rows));//, 'payment_summary' => $payment_summary)
	}

	public function item_search()
	{
		$suggestions = array();
		$receipt = $search = $this->input->get('term') != '' ? $this->input->get('term') : NULL;

		if($this->manufacture_lib->get_mode() == 'return' && $this->Manufacture->is_valid_receipt($receipt))
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
		
		$suggestions = $this->xss_clean($this->Manufacture->get_search_suggestions($search));
		
		echo json_encode($suggestions);
	}

	public function select_staff()
	{
		$staff_id = $this->input->post('staff');
		if($this->Staff->exists($staff_id))
		{
			$this->manufacture_lib->set_staff($staff_id);

			/*$discount_percent = $this->Staff->get_info($staff_id)->discount_percent;

			// apply staff default discount to items that have 0 discount
			if($discount_percent != '')
			{	
				$this->manufacture_lib->apply_staff_discount($discount_percent);
			}*/
		}
		
		$this->_reload();
	}

	public function change_mode()
	{
		$stock_location = $this->input->post('stock_location');
		if (!$stock_location || $stock_location == $this->manufacture_lib->get_transaction_location())
		{
			$mode = $this->input->post('mode');
			$this->manufacture_lib->set_mode($mode);
		} 
		elseif($this->Stock_location->is_allowed_location($stock_location, 'manufactures'))
		{
			$this->manufacture_lib->set_transaction_location($stock_location);
		}

		$this->_reload();
	}
	
	public function set_comment() 
	{
		$this->manufacture_lib->set_comment($this->input->post('comment'));
	}
	public function set_mailState() 
	{
		$this->manufacture_lib->set_mailState($this->input->post('mail_state'));
	}
	
	public function set_invoice_number()
	{
		$this->manufacture_lib->set_invoice_number($this->input->post('manufactures_invoice_number'));
	}
	
	public function set_invoice_number_enabled()
	{
		$this->manufacture_lib->set_invoice_number_enabled($this->input->post('manufactures_invoice_number_enabled'));
	}
	
	public function set_print_after_transaction()
	{
		$this->manufacture_lib->set_print_after_transaction($this->input->post('manufactures_print_after_manufacture'));
	}
	
	public function set_email_receipt()
	{
 		$this->manufacture_lib->set_email_receipt($this->input->post('email_receipt'));
	}

	// Multiple Payments
	public function add_payment()
	{
		$data = array();
		$this->form_validation->set_rules('amount_tendered', 'lang:manufactures_amount_tendered', 'trim|required|callback_numeric');

		$payment_type = $this->input->post('payment_type');

		if($this->form_validation->run() == FALSE)
		{
			if($payment_type == $this->lang->line('manufactures_giftcard'))
			{
				$data['error'] = $this->lang->line('manufactures_must_enter_numeric_giftcard');
			}
			else
			{
				$data['error'] = $this->lang->line('manufactures_must_enter_numeric');
			}
		}
		else
		{
			if($payment_type == $this->lang->line('manufactures_giftcard'))
			{
				// in case of giftcard payment the register input amount_tendered becomes the giftcard number
				$giftcard_num = $this->input->post('amount_tendered');

				$payments = $this->manufacture_lib->get_payments();
				$payment_type = $payment_type . ':' . $giftcard_num;
				$current_payments_with_giftcard = isset($payments[$payment_type]) ? $payments[$payment_type]['payment_amount'] : 0;
				$cur_giftcard_value = $this->Giftcard->get_giftcard_value($giftcard_num);
				
				if(($cur_giftcard_value - $current_payments_with_giftcard) <= 0)
				{
					$data['error'] = $this->lang->line('giftcards_remaining_balance', $giftcard_num, to_currency($cur_giftcard_value));
				}
				else
				{
					$new_giftcard_value = $this->Giftcard->get_giftcard_value($giftcard_num) - $this->manufacture_lib->get_amount_due();
					$new_giftcard_value = $new_giftcard_value >= 0 ? $new_giftcard_value : 0;
					$this->manufacture_lib->set_giftcard_remainder($new_giftcard_value);
					$new_giftcard_value = str_replace('$', '\$', to_currency($new_giftcard_value));
					$data['warning'] = $this->lang->line('giftcards_remaining_balance', $giftcard_num, $new_giftcard_value);
					$amount_tendered = min( $this->manufacture_lib->get_amount_due(), $this->Giftcard->get_giftcard_value($giftcard_num) );

					$this->manufacture_lib->add_payment($payment_type, $amount_tendered);
				}
			}
			else
			{
				$amount_tendered = $this->input->post('amount_tendered');

				$this->manufacture_lib->add_payment($payment_type, $amount_tendered);
			}
		}

		$this->_reload($data);
	}

	// Multiple Payments
	public function delete_payment($payment_id)
	{
		$this->manufacture_lib->delete_payment($payment_id);

		$this->_reload();
	}

	public function add()
	{
		$data = array();
		
		$discount = 0;

		// check if any discount is assigned to the selected staff
		/*$staff_id = $this->manufacture_lib->get_staff();
		if($staff_id != -1)
		{
			// load the staff discount if any
			$discount_percent = $this->Staff->get_info($staff_id)->discount_percent;
			if($discount_percent != '')
			{
				$discount = $discount_percent;
			}
		}

		// if the staff discount is 0 or no staff is selected apply the default manufactures discount
		if($discount == 0)
		{
			$discount = $this->config->item('default_manufactures_discount');
		}*/

		//$mode = $this->manufacture_lib->get_mode();
		$quantity = 1;//($mode == 'return') ? -1 : 1;
		$item_location = $this->manufacture_lib->get_transaction_location();
		$item_id_or_number_or_item_kit_or_receipt = $this->input->post('item');

		/*if($mode == 'return' && $this->Manufacture->is_valid_receipt($item_id_or_number_or_item_kit_or_receipt))
		{
			$this->manufacture_lib->return_entire_transaction($item_id_or_number_or_item_kit_or_receipt);
		}
		else*/if($this->Item_kit->is_valid_item_kit($item_id_or_number_or_item_kit_or_receipt))
		{
			if(!$this->manufacture_lib->add_item_kit($item_id_or_number_or_item_kit_or_receipt, $item_location, $discount))
			{
				$data['error'] = $this->lang->line('manufactures_unable_to_add_item');
			}
		}
		else
		{
			if(!$this->manufacture_lib->add_item($item_id_or_number_or_item_kit_or_receipt, $quantity, $item_location, $discount))
			{
				$data['error'] = $this->lang->line('manufactures_unable_to_add_item');
			}
			else
			{
				$data['warning'] = $this->manufacture_lib->out_of_stock($item_id_or_number_or_item_kit_or_receipt, $item_location);
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

		$price=abs($price);

		if($this->form_validation->run() != FALSE)
		{
			$this->manufacture_lib->edit_item($item_id, $description, $serialnumber, $quantity, $discount, $price);
		}
		else
		{
			$data['error'] = $this->lang->line('manufactures_error_editing_item');
		}

		$data['warning'] = $this->manufacture_lib->out_of_stock($this->manufacture_lib->get_item_id($item_id), $item_location);

		$this->_reload($data);
	}

	public function delete_item($item_number)
	{
		$this->manufacture_lib->delete_item($item_number);

		$this->_reload();
	}

	public function remove_staff()
	{
		$this->manufacture_lib->clear_giftcard_remainder();
		$this->manufacture_lib->clear_invoice_number();
		$this->manufacture_lib->remove_staff();

		$this->_reload();
	}

	public function complete()
	{return;
		/*$data = array();

		$data['cart'] = $this->manufacture_lib->get_cart();
		$data['subtotal'] = $this->manufacture_lib->get_subtotal();
		$data['discounted_subtotal'] = $this->manufacture_lib->get_subtotal(TRUE);
		$data['tax_exclusive_subtotal'] = $this->manufacture_lib->get_subtotal(TRUE, TRUE);
		$data['taxes'] = $this->manufacture_lib->get_taxes();
		$data['total'] = $this->manufacture_lib->get_total();
		$data['discount'] = $this->manufacture_lib->get_discount();
		$data['receipt_title'] = $this->lang->line('manufactures_receipt');
		$data['transaction_time'] = date($this->config->item('dateformat') . ' ' . $this->config->item('timeformat'));
		$data['transaction_date'] = date($this->config->item('dateformat'));
		$data['show_stock_locations'] = $this->Stock_location->show_locations('manufactures');
		$data['comments'] = $this->manufacture_lib->get_comment();
		$data['payments'] = $this->manufacture_lib->get_payments();
		$data['amount_change'] = $this->manufacture_lib->get_amount_due() * -1;
		$data['amount_due'] = $this->manufacture_lib->get_amount_due();
		$employee_id = $this->Employee->get_logged_in_employee_info()->person_id;
		$employee_info = $this->Employee->get_info($employee_id);
		$data['employee'] = $employee_info->first_name  . ' ' . $employee_info->last_name[0];
		$data['company_info'] = implode("\n", array(
			$this->config->item('address'),
			$this->config->item('phone'),
			$this->config->item('account_number')
		));
		$staff_id = $this->manufacture_lib->get_staff();
		$staff_info = $this->_load_staff_data($staff_id, $data);
		$invoice_number = $this->_substitute_invoice_number($staff_info);

		if($this->manufacture_lib->is_invoice_number_enabled() && $this->Manufacture->check_invoice_number_exists($invoice_number))
		{
			$data['error'] = $this->lang->line('manufactures_invoice_number_duplicate');

			$this->_reload($data);
		}
		elseif ($this->manufacture_lib->get_mailState()!=3) {
			$data['error'] = "还没有完成收货";$this->_reload($data);
		}
		else 
		{
			$invoice_number = $this->manufacture_lib->is_invoice_number_enabled() ? $invoice_number : NULL;
			$data['invoice_number'] = $invoice_number;
			$data['manufacture_id_num'] = $this->Manufacture->save($data['cart'], $staff_id, $employee_id, $data['comments'], $invoice_number, $data['payments']);
			$data['manufacture_id'] = 'POS ' . $data['manufacture_id_num'];
			
			$data = $this->xss_clean($data);
			
			if($data['manufacture_id_num'] == -1)
			{
				$data['error_message'] = $this->lang->line('manufactures_transaction_failed');
			}
			else
			{
				$data['barcode'] = $this->barcode_lib->generate_receipt_barcode($data['manufacture_id']);
			}

			$data['cur_giftcard_value'] = $this->manufacture_lib->get_giftcard_remainder();
			$data['print_after_manufacture'] = $this->manufacture_lib->is_print_after_transaction();
			$data['email_receipt'] = $this->manufacture_lib->get_email_receipt();
			
			if($this->manufacture_lib->is_invoice_number_enabled())
			{
				$this->load->view('transaction/invoice', $data);
			}
			else
			{
				$this->load->view('transaction/receipt', $data);
			}

			$this->manufacture_lib->clear_all();
		}*/
	}

	public function send_invoice($manufacture_id)
	{
		$manufacture_data = $this->_load_manufacture_data($manufacture_id);

		$result = FALSE;
		$message = $this->lang->line('manufactures_invoice_no_email');

		if(!empty($manufacture_data['staff_email']))
		{
			$to = $manufacture_data['staff_email'];
			$subject = $this->lang->line('manufactures_invoice') . ' ' . $manufacture_data['invoice_number'];

			$text = $this->config->item('invoice_email_message');
			$text = str_replace('$INV', $manufacture_data['invoice_number'], $text);
			$text = str_replace('$CO', 'POS ' . $manufacture_data['manufacture_id'], $text);
			$text = $this->_substitute_staff($text, (object) $manufacture_data);

			// generate email attachment: invoice in pdf format
			$html = $this->load->view('transaction/invoice_email', $manufacture_data, TRUE);
			// load pdf helper
			$this->load->helper(array('dompdf', 'file'));
			$filename = sys_get_temp_dir() . '/' . $this->lang->line('manufactures_invoice') . '-' . str_replace('/', '-' , $manufacture_data['invoice_number']) . '.pdf';
			if(file_put_contents($filename, pdf_create($html)) !== FALSE)
			{
				$result = $this->email_lib->sendEmail($to, $subject, $text, $filename);	
			}

			$message = $this->lang->line($result ? 'manufactures_invoice_sent' : 'manufactures_invoice_unsent') . ' ' . $to;
		}

		echo json_encode(array('success' => $result, 'message' => $message, 'id' => $manufacture_id));

		$this->manufacture_lib->clear_all();

		return $result;
	}

	public function send_receipt($manufacture_id)
	{
		$manufacture_data = $this->_load_manufacture_data($manufacture_id);

		$result = FALSE;
		$message = $this->lang->line('manufactures_receipt_no_email');

		if(!empty($manufacture_data['staff_email']))
		{
			$manufacture_data['barcode'] = $this->barcode_lib->generate_receipt_barcode($manufacture_data['manufacture_id']);

			$to = $manufacture_data['staff_email'];
			$subject = $this->lang->line('manufactures_receipt');

			$text = $this->load->view('transaction/receipt_email', $manufacture_data, TRUE);
			
			$result = $this->email_lib->sendEmail($to, $subject, $text);

			$message = $this->lang->line($result ? 'manufactures_receipt_sent' : 'manufactures_receipt_unsent') . ' ' . $to;
		}

		echo json_encode(array('success' => $result, 'message' => $message, 'id' => $manufacture_id));

		$this->manufacture_lib->clear_all();

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

	private function _substitute_staff($text, $staff_info)
	{
		// substitute staff info
		$staff_id = $this->manufacture_lib->get_staff();
		if($staff_id != -1 && $staff_info != '')
		{
			$text = str_replace('$CU', $staff_info->first_name . ' ' . $staff_info->last_name, $text);
			$words = preg_split("/\s+/", trim($staff_info->first_name . ' ' . $staff_info->last_name));
			$acronym = '';
			foreach($words as $w)
			{
				$acronym .= $w[0];
			}
			$text = str_replace('$CI', $acronym, $text);
		}

		return $text;
	}

	private function _is_custom_invoice_number($staff_info)
	{
		$invoice_number = $this->config->config['manufactures_invoice_format'];
		$invoice_number = $this->_substitute_variables($invoice_number, $staff_info);

		return $this->manufacture_lib->get_invoice_number() != $invoice_number;
	}

	private function _substitute_variables($text, $staff_info)
	{
		$text = $this->_substitute_variable($text, '$YCO', $this->Manufacture, 'get_invoice_number_for_year');
		$text = $this->_substitute_variable($text, '$CO', $this->Manufacture , 'get_invoice_count');
		$text = $this->_substitute_variable($text, '$SCO', $this->Manufacture_suspended, 'get_invoice_count');
		$text = strftime($text);
		$text = $this->_substitute_staff($text, $staff_info);

		return $text;
	}

	private function _substitute_invoice_number($staff_info)
	{
		$invoice_number = $this->config->config['manufactures_invoice_format'];
		$invoice_number = $this->_substitute_variables($invoice_number, $staff_info);
		$this->manufacture_lib->set_invoice_number($invoice_number, TRUE);

		return $this->manufacture_lib->get_invoice_number();
	}

	private function _load_staff_data($staff_id, &$data, $totals = FALSE)
	{	
		$staff_info = '';

		if($staff_id != -1)
		{
			$staff_info = $this->Staff->get_info($staff_id);
			/*if(isset($staff_info->company_name))
			{
				$data['partner'] = $staff_info->company_name;
			}
			else
			{
				$data['partner'] = $staff_info->first_name . ' ' . $staff_info->last_name;
			}*/
			$data['partner'] = $staff_info->first_name;
			if(isset($staff_info->company_name)){$data['partner'].='('.$staff_info->company_name.')';}

			$data['first_name'] = $staff_info->first_name;
			$data['last_name'] = '';//$staff_info->last_name;
			$data['partner_email'] = $staff_info->email;
			$data['partner_address'] = $staff_info->address_1;
			if(!empty($staff_info->zip) or !empty($staff_info->city))
			{
				$data['partner_location'] = $staff_info->zip . ' ' . $staff_info->city;				
			}
			else
			{
				$data['partner_location'] = '';
			}
			$data['partner_account_number'] = $staff_info->account_number;
			$data['partner_discount_percent'] = 0;//$staff_info->discount_percent;
			/*if($totals)
			{
				$cust_totals = $this->Staff->get_totals($staff_id);

				$data['staff_total'] = $cust_totals->total;
			}*/
			$data['partner_info'] = implode("\n", array(
				$data['partner'],
				$data['partner_address'],
				$data['partner_location'],
				$data['partner_account_number']
			));
		}

		return $staff_info;
	}

	private function _load_manufacture_data($manufacture_id)
	{
		$this->manufacture_lib->clear_all();
		$manufacture_info = $this->Manufacture->get_info($manufacture_id)->row_array();
		$this->manufacture_lib->copy_entire_transaction($manufacture_id);
		$data = array();
		$data['cart'] = $this->manufacture_lib->get_cart();
		$data['payments'] = $this->manufacture_lib->get_payments();
		$data['subtotal'] = $this->manufacture_lib->get_subtotal();
		$data['discounted_subtotal'] = $this->manufacture_lib->get_subtotal(TRUE);
		$data['tax_exclusive_subtotal'] = $this->manufacture_lib->get_subtotal(TRUE, TRUE);
		$data['taxes'] = $this->manufacture_lib->get_taxes();
		$data['total'] = $this->manufacture_lib->get_total();
		$data['discount'] = $this->manufacture_lib->get_discount();
		$data['receipt_title'] = $this->lang->line('manufactures_receipt');
		$data['transaction_time'] = date($this->config->item('dateformat') . ' ' . $this->config->item('timeformat'), strtotime($manufacture_info['manufacture_time']));
		$data['transaction_date'] = date($this->config->item('dateformat'), strtotime($manufacture_info['manufacture_time']));
		$data['show_stock_locations'] = $this->Stock_location->show_locations('manufactures');
		$data['amount_change'] = $this->manufacture_lib->get_amount_due() * -1;
		$data['amount_due'] = $this->manufacture_lib->get_amount_due();
		$employee_info = $this->Employee->get_info($this->manufacture_lib->get_employee());
		$data['employee'] = $employee_info->first_name . ' ' . $employee_info->last_name[0];
		$this->_load_staff_data($this->manufacture_lib->get_staff(), $data);

		$data['manufacture_id_num'] = $manufacture_id;
		$data['manufacture_id'] = 'POS ' . $manufacture_id;
		$data['comments'] = $manufacture_info['comment'];
		$data['invoice_number'] = $manufacture_info['invoice_number'];
		$data['company_info'] = implode("\n", array(
			$this->config->item('address'),
			$this->config->item('phone'),
			$this->config->item('account_number')
		));
		$data['barcode'] = $this->barcode_lib->generate_receipt_barcode($data['manufacture_id']);
		$data['print_after_manufacture'] = FALSE;

		return $this->xss_clean($data);
	}

	private function _reload($data = array())
	{		
		$data['cart'] = $this->manufacture_lib->get_cart();	 
		$data['modes'] = array('manufacture' => $this->lang->line('manufactures_manufacture'), 'return' => $this->lang->line('manufactures_return'));
		$data['mode'] = $this->manufacture_lib->get_mode();
		$data['stock_locations'] = $this->Stock_location->get_allowed_locations('manufactures');
		$data['stock_location'] = $this->manufacture_lib->get_transaction_location();
		$data['subtotal'] = $this->manufacture_lib->get_subtotal(TRUE);
		$data['tax_exclusive_subtotal'] = $this->manufacture_lib->get_subtotal(TRUE, TRUE);
		$data['taxes'] = $this->manufacture_lib->get_taxes();
		$data['discount'] = $this->manufacture_lib->get_discount();
		$data['total'] = $this->manufacture_lib->get_total();
		$data['comment'] = $this->manufacture_lib->get_comment();
		$data['email_receipt'] = $this->manufacture_lib->get_email_receipt();
		$data['payments_total'] = $this->manufacture_lib->get_payments_total();
		$data['amount_due'] = $this->manufacture_lib->get_amount_due();
		$data['payments'] = $this->manufacture_lib->get_payments();
		$data['payment_options'] = $this->Manufacture->get_payment_options();

		$data['items_module_allowed'] = $this->Employee->has_grant('items', $this->Employee->get_logged_in_employee_info()->person_id);

		$staff_info = $this->_load_staff_data($this->manufacture_lib->get_staff(), $data, FALSE);
		$data['invoice_number'] = $this->_substitute_invoice_number($staff_info);
		$data['invoice_number_enabled'] = $this->manufacture_lib->is_invoice_number_enabled();
		$data['print_after_manufacture'] = $this->manufacture_lib->is_print_after_transaction();
		$data['payments_cover_total'] = $this->manufacture_lib->get_amount_due() <= 0;
		
		$data['mail_state']=$this->manufacture_lib->get_mailState();
		$manufacture_id=$this->manufacture_lib->get_transaction_id();
		if(FALSE!=$manufacture_id){$data['transaction_id']=$manufacture_id;}

		$data['partner_type']='staff';

		$data = $this->xss_clean($data);

		$this->check_stock_location($data);

		$this->load->view('transaction/register', $data);
	}

	public function show_help(){
		echo $this->lang->line('manufactures_help');
	}

	public function receipt($manufacture_id)
	{
		$data = $this->_load_manufacture_data($manufacture_id);

		$this->load->view('transaction/receipt', $data);

		$this->manufacture_lib->clear_all();
	}

	public function invoice($manufacture_id)
	{
		$data = $this->_load_manufacture_data($manufacture_id);

		$this->load->view('transaction/invoice', $data);

		$this->manufacture_lib->clear_all();
	}

	public function edit($manufacture_id)
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

		$manufacture_info = $this->xss_clean($this->Manufacture->get_info($manufacture_id)->row_array());

		$manufacture_info['transaction_id']=$manufacture_info['manufacture_id'];
		$manufacture_info['transaction_time']=$manufacture_info['manufacture_time'];

		$employee=$this->Employee->get_info($manufacture_info['employee_id']);
		$data['employee_name']=$employee->first_name;

		$data['transaction_info'] = $manufacture_info;

		/*$data['selected_staff_name'] = $manufacture_info['staff_name'];
		$data['selected_staff_id'] = $manufacture_info['staff_id'];
		
		$data['payments'] = array();
		foreach($this->Manufacture->get_manufacture_payments($manufacture_id)->result() as $payment)
		{
			foreach(get_object_vars($payment) as $property => $value)
			{
				$payment->$property = $this->xss_clean($value);
			}
			
			$data['payments'][] = $payment;
		}
		
		// don't allow gift card to be a payment option in a manufacture transaction edit because it's a complex change
		$data['payment_options'] = $this->xss_clean($this->Manufacture->get_payment_options(FALSE));*/
		
		$this->load->view('transaction/form', $data);
	}

	public function delete($manufacture_id = -1, $update_inventory = TRUE)
	{
		$employee_id = $this->Employee->get_logged_in_employee_info()->person_id;
		$manufacture_ids = $manufacture_id == -1 ? $this->input->post('ids') : array($manufacture_id);

		if($this->Manufacture->delete_list($manufacture_ids, $employee_id, $update_inventory))
		{
			echo json_encode(array('success' => TRUE, 'message' => $this->lang->line('manufactures_successfully_deleted') . ' ' .
							count($manufacture_ids) . ' ' . $this->lang->line('manufactures_one_or_multiple'), 'ids' => $manufacture_ids));
		}
		else
		{
			echo json_encode(array('success' => FALSE, 'message' => $this->lang->line('manufactures_unsuccessfully_deleted')));
		}
	}

	public function save($manufacture_id = -1)
	{
		$newdate = $this->input->post('date');

		$date_formatter = date_create_from_format($this->config->item('dateformat') . ' ' . $this->config->item('timeformat'), $newdate);

		$manufacture_data = array(
			'manufacture_time' => $date_formatter->format('Y-m-d H:i:s'),
			'staff_id' => $this->input->post('staff_id') != '' ? $this->input->post('staff_id') : NULL,
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
			// remove any 0 payment if by mistake any was introduced at manufacture time
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

		if($this->Manufacture->update($manufacture_id, $manufacture_data, $payments))
		{
			echo json_encode(array('success' => TRUE, 'message' => $this->lang->line('manufactures_successfully_updated'), 'id' => $manufacture_id));
		}
		else
		{
			echo json_encode(array('success' => FALSE, 'message' => $this->lang->line('manufactures_unsuccessfully_updated'), 'id' => $manufacture_id));
		}
	}

	public function cancel()
	{
		$this->manufacture_lib->clear_all();

		$this->_reload($this->manufacture_lib->mark_unedited_transaction());
	}

	public function suspend()
	{
		$cart = $this->manufacture_lib->get_cart();
		$payments = $this->manufacture_lib->get_payments();
		$employee_id = $this->Employee->get_logged_in_employee_info()->person_id;
		$staff_id = $this->manufacture_lib->get_staff();
		$staff_info = $this->Staff->get_info($staff_id);
		$invoice_number = $this->_is_custom_invoice_number($staff_info) ? $this->manufacture_lib->get_invoice_number() : NULL;
		$comment = $this->manufacture_lib->get_comment();

		$infos=array();
		$infos['mail_state']=$this->manufacture_lib->get_mailState();
		$infos['cart_diff']=$this->manufacture_lib->get_cart_diff();
		$infos['manufacture_items_location']=$this->manufacture_lib->get_transaction_items_location();

		//SAVE manufacture to database
		$data = array();
		$manufacture_id=$this->Manufacture_suspended->save($cart, $staff_id, $employee_id, $comment, 
			$invoice_number, $payments,$infos,$this->manufacture_lib->get_transaction_id());
		if( $manufacture_id == '-1')
		{
			$data['error'] = $this->lang->line('manufactures_unsuccessfully_suspended_manufacture');
		}
		else
		{
			$this->manufacture_lib->set_transaction_id($manufacture_id);
			$this->manufacture_lib->clear_all();
			$this->manufacture_lib->copy_entire_suspended_transaction($manufacture_id);

			$data['success'] = $this->lang->line('manufactures_successfully_suspended_manufacture');
		}

		//$this->manufacture_lib->clear_all();

		$this->_reload($this->manufacture_lib->mark_unedited_transaction($data));
	}
	
	public function suspended()
	{	
		/*$data = array();
		$data['suspended_manufactures'] = $this->xss_clean($this->Manufacture_suspended->get_all()->result_array());

		$this->load->view('transaction/suspended', $data);*/
	}
	
	public function update(){
		$manufacture_id = $this->input->get('id');

		$this->manufacture_lib->clear_all();
		$this->manufacture_lib->copy_entire_suspended_transaction($manufacture_id);
		//$this->Manufacture_suspended->delete_together($manufacture_id);

		$this->_reload($this->manufacture_lib->mark_unedited_transaction());
	}
	public function unsuspend()
	{
		/*$manufacture_id = $this->input->post('suspended_manufacture_id');

		$this->manufacture_lib->clear_all();
		$this->manufacture_lib->copy_entire_suspended_transaction($manufacture_id);
		//$this->Manufacture_suspended->delete_together($manufacture_id);

		$this->_reload($this->manufacture_lib->mark_unedited_transaction());*/
	}
	
	public function check_invoice_number()
	{
		$manufacture_id = $this->input->post('manufacture_id');
		$invoice_number = $this->input->post('invoice_number');
		$exists = !empty($invoice_number) && $this->Manufacture->check_invoice_number_exists($invoice_number, $manufacture_id);

		echo !$exists ? 'true' : 'false';
	}

	public function change_manufacture_to_current_location(){
		$items = $this->manufacture_lib->get_cart();
		$location_id=$this->Stock_location->get_default_location_id();

		foreach ($items as $key => $item) {
			$item['item_location']=$location_id;
			$item['stock_name']=$this->Stock_location->get_location_name_session($location_id);

			if(1==$item['is_infinite']){continue;}
			$item['in_stock']=$this->Item_quantity->get_item_quantity($item['item_id'], $location_id)->quantity;

			$items[$key]=$item;
		}

		$this->manufacture_lib->set_cart($items);$this->manufacture_lib->clear_transaction_items_location();
		$this->_reload();
	}
}
?>
