<div id="required_fields_message"><?php echo $this->lang->line('common_fields_required_message'); ?></div>

<ul id="error_message_box" class="error_message_box"></ul>

<?php echo form_open('staffs/save/'.$person_info->person_id, array('id'=>'staff_form', 'class'=>'form-horizontal')); ?>
	<fieldset id="staff_basic_info">
		<?php $this->load->view("people/form_basic_info"); ?>

		<div class="form-group form-group-sm">
			<?php echo form_label($this->lang->line('customers_account_number'), 'account_number', array('class' => 'control-label col-xs-3')); ?>
			<div class='col-xs-4'>
				<?php echo form_input(array(
						'name'=>'account_number',
						'id'=>'account_number',
						'class'=>'form-control input-sm',
						'value'=>$person_info->account_number)
						);?>
			</div>
		</div>
		
	</fieldset>
<?php echo form_close(); ?>

<script type="text/javascript">

//validation and submit handling
$(document).ready(function()
{
	$('#staff_form').validate($.extend({
		submitHandler:function(form)
		{
			$(form).ajaxSubmit({
				success:function(response)
				{
					dialog_support.hide();
					table_support.handle_submit('<?php echo site_url($controller_name); ?>', response);
				},
				dataType:'json'
			});
		},
		rules:
		{
			first_name: "required",
			last_name: "required",
    		email: "email"
   		},
		messages: 
		{
     		first_name: "<?php echo $this->lang->line('common_first_name_required'); ?>",
     		last_name: "<?php echo $this->lang->line('common_last_name_required'); ?>",
     		email: "<?php echo $this->lang->line('common_email_invalid_format'); ?>",
			account_number: "<?php echo $this->lang->line('staffs_account_number_duplicate'); ?>"
		}
	}, form_support.error));
});
</script>