<ul id="error_message_box" class="error_message_box"></ul>

	<fieldset id="sale_basic_info" class="form-horizontal">
		<div class="form-group form-group-sm">
			<?php //echo form_label($this->lang->line('sales_receipt_number'), 'receipt_number', array('class'=>'control-label col-xs-3')); ?>
			<?php //echo anchor('sales/receipt/'.$transaction_info['sale_id'], 'POS ' . $transaction_info['sale_id'], array('target'=>'_blank', 'class'=>'control-label col-xs-8', "style"=>"text-align:left"));?>
			<?php echo form_label($controller_name."序号", 'id', array('class'=>'control-label col-xs-3')); ?>
			<div class='col-xs-8'>
				<input class="form-control input-sm" value="<?php echo $transaction_info['transaction_id'];?>">
			</div>
		</div>
		
		<div class="form-group form-group-sm">
			<?php echo form_label("创建时间", 'date', array('class'=>'control-label col-xs-3')); ?>
			<div class='col-xs-8'>
				<input class="form-control input-sm" value="<?php echo $transaction_info['transaction_time'];?>">
			</div>
		</div>

		<div class="form-group form-group-sm">
			<?php echo form_label("最后修改时间", 'date', array('class'=>'control-label col-xs-3')); ?>
			<div class='col-xs-8'>
				<input class="form-control input-sm" value="<?php echo $transaction_info['last_edit_time'];?>">
			</div>
		</div>
		
		<div class="form-group form-group-sm">
			<?php echo form_label($this->lang->line('sales_employee'), 'employee', array('class'=>'control-label col-xs-3')); ?>
			<div class='col-xs-8'>
				<input class="form-control input-sm" value="<?php echo $employee_name;?>">
			</div>
		</div>
	</fieldset>

