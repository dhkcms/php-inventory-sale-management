<?php $this->load->view("partial/header"); ?>

<?php
if (isset($error))
{
	echo "<div class='alert alert-dismissible alert-danger'>".$error."</div>";
}

if (!empty($warning))
{
	echo "<div class='alert alert-dismissible alert-warning'>".$warning."</div>";
}

if (isset($success))
{
	echo "<div class='alert alert-dismissible alert-success'>".$success."</div>";
}

$transaction_editable=(!isset($transaction_editable)||$transaction_editable==1);
?>

<div id="register_wrapper">

<!-- Top register controls -->

	<?php $tabindex = 0; ?>

	<?php echo form_open($controller_name."/add", array('id'=>'add_item_form', 'class'=>'form-horizontal panel panel-default')); ?>
		<div class="panel-body form-group">
			<ul>
				<li class="pull-left first_li">
					<label for="item" class='control-label'>物品(包)名称</label>
				</li>
				<li class="pull-left">
					<?php echo form_input(array('name'=>'item', 'id'=>'item', 'class'=>'form-control input-sm', 'size'=>'35','placeholder'=>"请在这里输入并选择正确的名称",'tabindex'=>++$tabindex)); ?>
					<span class="ui-helper-hidden-accessible" role="status"></span>
				</li>

				<li class="pull-right">
					<a href="<?php echo $controller_name;?>/manage<?php if(!empty($transaction_id)){echo '?from_id='.$transaction_id;} ?>" class="btn btn-info btn-sm pull-right">返回列表</a>
				</li>

				<li class="pull-right">
				<?php if(!empty($transaction_id)) { ?>
				<a href="<?php echo $controller_name;?>/edit/<?php echo $transaction_id?>" class="modal-dlg btn btn-info btn-sm pull-right">更多信息</a>
				<?php } ?>
				</li>

				<!--<li>
				<a href="<?php echo $controller_name;?>/show_help" class="modal-dlg btn btn-info btn-sm pull-right">帮助</a>
				</li>-->


			</ul>
		</div>
	<?php echo form_close(); ?>


<!-- Sale Items List -->
	
	<table class="sales_table_100" id="register">
		<thead>
			<tr>
				<th style="width: 5%;"><?php echo $this->lang->line('common_delete'); ?></th>
				<th style="width: 10%;"><?php echo $this->lang->line('sales_item_number'); ?></th>
				<th style="width: 35%;"><?php echo $this->lang->line('sales_item_name'); ?></th>
				<th style="width: 15%;"><?php echo $this->lang->line('sales_price'); ?></th>
				<th style="width: 15%;"><?php echo $this->lang->line('sales_quantity'); ?></th>
				<th style="width: 15%;"><?php echo $this->lang->line('sales_total'); ?></th>
				<th style="width: 5%;"><?php echo $this->lang->line('sales_update'); ?></th>
			</tr>
		</thead>

		<tbody id="cart_contents">
			<?php
			if(count($cart) == 0)
			{
			?>
				<tr>
					<td colspan='8'>
						<div class='alert alert-dismissible alert-info'><?php echo $this->lang->line('sales_no_items_in_cart'); ?></div>
					</td>
				</tr>
			<?php
			}
			else
			{//print_r($cart);
				foreach(array_reverse($cart, true) as $line=>$item)
				{					
			?>
					<?php echo form_open($controller_name."/edit_item/$line", array('class'=>'form-horizontal', 'id'=>'cart_'.$line)); ?>
						<tr>
							<td>
								<?php if($transaction_editable){ ?>
								<?php echo anchor($controller_name."/delete_item/$line", '<span class="glyphicon glyphicon-trash"></span>');?>
								<?php } ?>
							</td>
							
							<td><?php echo $item['item_id'];if(1==$item['is_item_kit']){echo "号物品包";} ?></td>
							<td style="align: center;">
								<a href="<?php if(1==$item['is_item_kit']){echo 'item_kits/view/';}else{echo 'items/view/';}echo $item['item_id'];?>" class="modal-dlg"><?php echo $item['name']; ?></a>

								<?php if(0==$item['is_infinite']){?>
								<br /> <?php echo '[' . to_quantity_decimals($item['in_stock']) . ' in ' . $item['stock_name'] . ']'; ?>
								<?php echo form_hidden('location', $item['item_location']); ?>
								<?php } ?>
							</td>

							<?php
							if ($items_module_allowed)
							{
							?>
								<td><?php echo form_input(array('name'=>'price', 'class'=>'form-control input-sm', 'value'=>to_currency_no_money($item['price']), 'tabindex'=>++$tabindex));?></td>
							<?php
							}
							else
							{
							?>
								<td>
									<?php echo to_currency($item['price']); ?>
									<?php echo form_hidden('price', to_currency_no_money($item['price'])); ?>
								</td>
							<?php
							}
							?>

							<td>
								<?php
								if($item['is_serialized']==1)
								{
									echo to_quantity_decimals($item['quantity']);
									echo form_hidden('quantity', $item['quantity']);
								}
								else
								{								
									echo form_input(array('name'=>'quantity', 'class'=>'form-control input-sm', 'value'=>to_quantity_decimals($item['quantity']), 'tabindex'=>++$tabindex));
								}
								?>
							</td>

							<td><?php echo to_currency($item['price']*$item['quantity']-$item['price']*$item['quantity']*$item['discount']/100); ?></td>
							<td>
								<?php if($transaction_editable){ ?>
								<a href="javascript:document.getElementById('<?php echo 'cart_'.$line ?>').submit();" title=<?php echo $this->lang->line('sales_update')?> ><span class="glyphicon glyphicon-refresh"></span></a>
								<?php } ?>
							</td>
						</tr>
					<?php echo form_close(); ?>
			<?php
				}
			}
			?>
		</tbody>
	</table>
</div>

<!-- Overall Sale -->

<div id="overall_sale" class="panel panel-default">
	<div class="panel-body">
		<?php
		if(isset($partner))
		{
		?>
			<table class="sales_table_100">
				<tr>
					<th style='width: 55%;'><?php echo $this->lang->line("sales_customer"); ?></th>
					<th style="width: 45%; text-align: right;"><?php echo $partner; ?></th>
				</tr>
				<?php
				if(!empty($partner_email))
				{
				?>
					<tr>
						<th style='width: 55%;'><?php echo $this->lang->line("sales_customer_email"); ?></th>
						<th style="width: 45%; text-align: right;"><?php echo $partner_email; ?></th>
					</tr>
				<?php
				}
				?>
				<?php
				if(!empty($partner_address))
				{
				?>
					<tr>
						<th style='width: 55%;'><?php echo $this->lang->line("sales_customer_address"); ?></th>
						<th style="width: 45%; text-align: right;"><?php echo $partner_address; ?></th>
					</tr>
				<?php
				}
				?>
				<?php
				if(!empty($partner_location))
				{
				?>
					<tr>
						<th style='width: 55%;'><?php echo $this->lang->line("sales_customer_location"); ?></th>
						<th style="width: 45%; text-align: right;"><?php echo $partner_location; ?></th>
					</tr>
				<?php
				}
				?>
			</table>

			<?php echo anchor($controller_name."/remove_".$partner_type, '<span class="glyphicon glyphicon-remove">&nbsp</span>' . $this->lang->line('common_remove').' '.$partner_type,
								array('class'=>'btn btn-danger btn-sm', 'id'=>'remove_customer_button')); ?>
		<?php
		}
		else
		{
		?>
			<?php echo form_open($controller_name."/select_".$partner_type, array('id'=>'select_customer_form', 'class'=>'form-horizontal')); ?>
				<div class="form-group" id="select_customer">
					<label id="customer_label" for="customer" class="control-label" style="margin-bottom: 1em; margin-top: -1em;"><?php echo $this->lang->line($controller_name.'_select_'.$partner_type); ?></label>
					<?php echo form_input(array('name'=>$partner_type, 'id'=>'customer', 'class'=>'form-control input-sm','placeholder'=>'输入并选择'.$partner_type.'的名字'));?>
				</div>
			<?php echo form_close(); ?>
		<?php
		}
		?>
	
		<?php
		// Only show this part if there are Items already in the sale.
		if(count($cart) > 0)
		{
		?>
			<table class="sales_table_100" id="payment_totals">
				<tr>
					<th style='width: 55%;'><?php echo $this->lang->line('sales_total'); ?></th>
					<th style="width: 45%; text-align: right;"><?php echo to_currency($total); ?></th>
				</tr>
				<tr>
					<th style="width: 55%;"><?php echo $this->lang->line('sales_payments_total');?></th>
					<th style="width: 45%; text-align: right;"><?php echo to_currency($payments_total); ?></th>
				</tr>
				<tr>
					<th style="width: 55%;"><?php echo $this->lang->line('transaction_amount_due');?></th>
					<th style="width: 45%; text-align: right;"><?php echo to_currency($amount_due); ?></th>
				</tr>
			</table>

			<div id="payment_details">
					<?php
					// Show Complete sale button instead of Add Payment if there is no amount due left
					if($payments_cover_total){
					?>
					<?php
					}
					else
					{
					?>

						<?php echo form_open($controller_name."/add_payment", array('id'=>'add_payment_form', 'class'=>'form-horizontal','onsubmit'=>$transaction_editable?"":"return false")); ?>
							<table class="sales_table_100">
								<tr>
									<td><?php echo $this->lang->line('sales_payment');?></td>
									<td>
										<?php echo form_dropdown('payment_type', $payment_options, array(), array('id'=>'payment_types', 'class'=>'selectpicker show-menu-arrow', 'data-style'=>'btn-default btn-sm', 'data-width'=>'auto')); ?>
									</td>
								</tr>
								<tr>
									<td><span id="amount_tendered_label"><?php echo $this->lang->line('transaction_amount_tendered'); ?></span></td>
									<td>
										<?php echo form_input(array('name'=>'amount_tendered', 'id'=>'amount_tendered', 'class'=>'form-control input-sm', 'value'=>to_currency_no_money($amount_due), 'size'=>'5', 'tabindex'=>++$tabindex)); ?>
									</td>
								</tr>
							</table>
						<?php echo form_close(); ?>

						<div class='btn btn-sm btn-success pull-right' id='add_payment_button' tabindex='<?php echo ++$tabindex; ?>'><span class="glyphicon glyphicon-credit-card">&nbsp</span><?php echo $this->lang->line('sales_add_payment'); ?></div>
					<?php
					}
					?>

				<?php
				// Only show this part if there is at least one payment entered.
				if(count($payments) > 0)
				{
				?>
					<table class="sales_table_100" id="register">
						<thead>
							<tr>
								<th style="width: 10%;"><?php echo $this->lang->line('common_delete'); ?></th>
								<th style="width: 60%;"><?php echo $this->lang->line('sales_payment_type'); ?></th>
								<th style="width: 20%;"><?php echo $this->lang->line('sales_payment_amount'); ?></th>
							</tr>
						</thead>
			
						<tbody id="payment_contents">
							<?php
							foreach($payments as $payment_id=>$payment)
							{
							?>
								<tr>
									<td>
										<?php if($transaction_editable){ ?>
										<?php echo anchor($controller_name."/delete_payment/$payment_id", '<span class="glyphicon glyphicon-trash"></span>'); ?>
										<?php } ?>
									</td>
									<td><?php echo $payment['payment_type']; ?></td>
									<td style="text-align: right;"><?php echo to_currency( $payment['payment_amount'] ); ?></td>
								</tr>
							<?php
							}
							?>
						</tbody>
					</table>
				<?php
				}
				?>
			</div>

			<?php echo form_open($controller_name."/cancel", array('id'=>'buttons_form')); ?>
				<div class="form-group" id="buttons_sale">
					<div class='btn btn-sm btn-default pull-left' id='suspend_sale_button'><span class="glyphicon glyphicon-align-justify">&nbsp</span>保存</div>

					<div class='btn btn-sm btn-danger pull-right' id='cancel_sale_button'><span class="glyphicon glyphicon-remove">&nbsp</span>放弃更改</div>
				</div>
			<?php echo form_close(); ?>

				<div class="info_details">
					<table class="sales_table_100">
						<?php if($controller_name!='manufactures'){ ?>
						<tr>
							<td>
								<?php echo form_label('收货状态', 'mail_state', array('class'=>'control-label', 'id'=>'mail_state_label', 'for'=>'mail_states')); ?>
							</td>
							<td>
								<?php echo form_dropdown('mail_state',array('0'=>'未发货','1'=>'已发货未到货','2'=>'已到货未收货','3'=>'已收货'),$mail_state, array('id'=>'mail_states', 'class'=>'selectpicker show-menu-arrow', 'data-style'=>'btn-default btn-sm', 'data-width'=>'auto')); ?>
							</td>
						</tr>
						<?php } ?>
						<tr>
							<td>
								<?php echo form_label($this->lang->line('common_comments'), 'comments', array('class'=>'control-label', 'id'=>'comment_label', 'for'=>'comment')); ?>
							</td>
							<td>
								<?php echo form_input(array('name'=>'comment', 'id'=>'comment', 'class'=>'form-control input-sm', 'value'=>$comment)); ?>
							</td>
						</tr>
					</table>
				</div>

				<div class="container-fluid">
				<?php
				// Only show this part if the payment cover the total
				if($payments_cover_total)
				{
				?>
				<?php
				if ($mode == "sale" && $this->config->item('invoice_enable') == TRUE)
				{
				?>
					<div class="row">
						<div class="form-group form-group-sm">
							<div class="col-xs-6">
								<label class="control-label checkbox" for="sales_invoice_enable">
									<?php echo form_checkbox(array('name'=>'sales_invoice_enable', 'id'=>'sales_invoice_enable', 'value'=>1, 'checked'=>$invoice_number_enabled)); ?>
									<?php echo $this->lang->line('sales_invoice_enable');?>
								</label>
							</div>

							<div class="col-xs-6">
								<div class="input-group input-group-sm">
									<span class="input-group-addon input-sm">#</span>
									<?php echo form_input(array('name'=>'sales_invoice_number', 'id'=>'sales_invoice_number', 'class'=>'form-control input-sm', 'value'=>$invoice_number));?>
								</div>
							</div>
						</div>
					</div>
				<?php
				}
				?>
			<?php
			}
			?>
			</div>
		<?php
		}
		?>
	</div>
</div>

<?php if($transaction_editable){ ?>
<script type="text/javascript">
var edited=<?php echo empty($sale_edited)?'true':$sale_edited; ?>;

$(document).ready(function()
{
    $("#item").autocomplete(
	{
		source: '<?php echo site_url($controller_name."/item_search"); ?>',
    	minChars: 0,
    	autoFocus: false,
       	delay: 500,
		select: function (a, ui) {
			$(this).val(ui.item.value);
			$("#add_item_form").submit();
			return false;
		}
    });

	$('#item').focus();

	$('#item').keypress(function (e) {
		if (e.which == 13) {
			$('#add_item_form').submit();
			return false;
		}
	});

    /*$('#item').blur(function()
    {
        $(this).val("<?php echo $this->lang->line('sales_start_typing_item_name'); ?>");
    });*/

    var clear_fields = function()
    {
        /*if ($(this).val().match("<?php echo $this->lang->line('sales_start_typing_item_name') . '|' . $this->lang->line('sales_start_typing_customer_name'); ?>"))
        {
            $(this).val('');
        }*/
    };

    $("#customer").autocomplete(
    {
		source: '<?php echo site_url($partner_type."s/suggest"); ?>',
    	minChars: 0,
    	delay: 10,
		select: function (a, ui) {
			$(this).val(ui.item.value);
			$("#select_customer_form").submit();
		}
    });

	$('#item, #customer').click(clear_fields).dblclick(function(event)
	{
		$(this).autocomplete("search");
	});

	/*$('#customer').blur(function()
    {
    	$(this).val("<?php echo $this->lang->line('sales_start_typing_customer_name'); ?>");
    });*/

	$('#comment').keyup(function() 
	{edited=true;
		$.post('<?php echo site_url($controller_name."/set_comment");?>', {comment: $('#comment').val()});
	});

	$('#mail_states').change(function() 
	{edited=true;
		$.post('<?php echo site_url($controller_name."/set_mailState");?>', {mail_state: $('#mail_states').val()});
	});

	<?php
	if ($this->config->item('invoice_enable') == TRUE) 
	{
	?>
		$('#sales_invoice_number').keyup(function() 
		{
			$.post('<?php echo site_url($controller_name."/set_invoice_number");?>', {sales_invoice_number: $('#sales_invoice_number').val()});
		});

		var enable_invoice_number = function() 
		{
			var enabled = $("#sales_invoice_enable").is(":checked");
			$("#sales_invoice_number").prop("disabled", !enabled).parents('tr').show();
			return enabled;
		}

		enable_invoice_number();
		
		$("#sales_invoice_enable").change(function()
		{
			var enabled = enable_invoice_number();
			$.post('<?php echo site_url($controller_name."/set_invoice_number_enabled");?>', {sales_invoice_number_enabled: enabled});
		});
	<?php
	}
	?>

	$("#sales_print_after_sale").change(function()
	{
		$.post('<?php echo site_url($controller_name."/set_print_after_transaction");?>', {sales_print_after_sale: $(this).is(":checked")});
	});
	
	$('#email_receipt').change(function() 
	{
		$.post('<?php echo site_url($controller_name."/set_email_receipt");?>', {email_receipt: $('#email_receipt').is(':checked') ? '1' : '0'});
	});
	
	$("#suspend_sale_button").click(function()
	{ 	
		$('#buttons_form').attr('action', '<?php echo site_url($controller_name."/suspend"); ?>');
		$('#buttons_form').submit();
	});

    $("#cancel_sale_button").click(function()
    {
    	if (edited&&!confirm('<?php echo $this->lang->line("sales_confirm_cancel_sale"); ?>')){
    		return;
    	}
		$('#buttons_form').attr('action', '<?php echo site_url($controller_name."/cancel"); ?>');
    	$('#buttons_form').submit();
    });

	$("#add_payment_button").click(function()
	{
		$('#add_payment_form').submit();
    });

	$("#payment_types").change(check_payment_type_giftcard).ready(check_payment_type_giftcard);

	$("#cart_contents input").keypress(function(event)
	{
		if (event.which == 13)
		{
			$(this).parents("tr").prevAll("form:first").submit();
		}
	});

	$("#amount_tendered").keypress(function(event)
	{
		if( event.which == 13 )
		{
			$('#add_payment_form').submit();
		}
	});
	
	//dialog_support.init("a.modal-dlg, button.modal-dlg");

	table_support.handle_submit = function(resource, response, stay_open)
	{
		if(response.success) {
			if (resource.match(/customers$/))
			{
				$("#customer").val(response.id);
				$("#select_customer_form").submit();
			}
			else
			{
				var $stock_location = $("select[name='stock_location']").val();
				$("#item_location").val($stock_location);
				$("#item").val(response.id);
				if (stay_open)
				{
					$("#add_item_form").ajaxSubmit();
				}
				else
				{
					$("#add_item_form").submit();
				}
			}
		}
	}
});

function check_payment_type_giftcard()
{return;
	if ($("#payment_types").val() == "<?php echo $this->lang->line('sales_giftcard'); ?>")
	{
		$("#amount_tendered_label").html("<?php echo $this->lang->line('sales_giftcard_number'); ?>");
		$("#amount_tendered:enabled").val('').focus();
	}
	else
	{
		$("#amount_tendered_label").html("<?php echo $this->lang->line('sales_amount_tendered'); ?>");
		$("#amount_tendered:enabled").val('<?php echo to_currency_no_money($amount_due); ?>');
	}
}

</script>
<?php } ?>

<?php $this->load->view("partial/footer"); ?>
