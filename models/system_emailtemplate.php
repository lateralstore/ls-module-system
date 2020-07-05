<?php

	/**
	 * @has_documentable_methods
	 */
	class System_EmailTemplate extends Db_ActiveRecord 
	{
		public $table_name = 'system_email_templates';
		public $reply_to_mode = 'default';
		public $log_notification = false;
		protected $api_added_columns = array();

		public static function create($values = null) 
		{
			return new self($values);
		}
		
		public function define_columns($context = null)
		{
			$this->define_column('code', 'Code')->validation()->fn('trim')->required('Please specify the template code.')->unique('Code %s is already in use. Please specify another code.')->regexp('/^[a-z_0-9:]*$/i', 'Template code can only contain latin characters, numbers, colons and underscores.');
			$this->define_column('subject', 'Subject')->validation()->fn('trim')->required('Please specify the message subject.');
			$this->define_column('is_system', 'Internal notification'); 
			$this->define_column('description', 'Description')->validation()->fn('trim')->required('Please provide the template description.');
			$this->define_column('content', 'Content')->invisible()->validation()->required('Please fill out the template content.');

			$this->define_column('reply_to_mode', 'Reply-To Address')->invisible();
			$this->define_column('reply_to_address', 'Reply To Address value')->invisible()->validation()->email(true, 'Please specify a valid email address');

			$this->define_column('allow_recipient_block', 'Allow recipient to block this notification');

			$this->defined_column_list = array();
			Backend::$events->fireEvent('core:onExtendEmailTemplateModel', $this, $context);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('is_system')->comment('Internal notifications are sent to the system administrators and use the System email layout', 'above')->tab('Message');
			$this->add_form_field('allow_recipient_block')->comment('Allows recipient to block this email through their preferences. Do not enable this feature on important transactional templates (eg. password reset and registration confirmation)', 'above')->tab('Message');
			if ($context == 'create')
			{
				$this->add_form_field('code', 'left')->comment('Template code is used by modules to refer templates', 'above')->tab('Message');
				$this->add_form_field('subject', 'right')->comment('Email message subject',  'above')->tab('Message');
				
				$this->add_form_field('description')->size('tiny')->tab('Message');
			} else
			{
				$this->add_form_field('subject')->tab('Message');
				$this->add_form_field('description')->size('tiny')->tab('Message');
			}
				
			$editor_config = System_HtmlEditorConfig::get('system', 'system_email_template');
			$field = $this->add_form_field('content')->renderAs(frm_html)->size('huge')->tab('Message')->saveCallback('save_template');
			$field->htmlPlugins .= ',save';
			$editor_config->apply_to_form_field($field);
			$field->htmlFullWidth = true;
			
			$this->add_form_field('reply_to_mode')->tab('Email Settings')->renderAs(frm_radio)->comment('Please choose which reply-to email address should be used in email messages based on this template.', 'above');
			$this->add_form_field('reply_to_address')->tab('Email Settings')->cssClassName('checkbox_align')->cssClasses('form400')->noLabel();
			
			Backend::$events->fireEvent('core:onExtendEmailTemplateForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
				{
					$form_field->optionsMethod('get_added_field_options');
					$form_field->optionStateMethod('get_added_field_option_state');
				}
			}
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('core:onGetEmailTemplateFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || ($options !== false && $current_key_value != -1))
					return $options;
			}
			
			return false;
		}
		
		public function get_reply_to_mode_options($key_value = -1)
		{
			$params = System_EmailParams::get();
			
			$result = array('default'=>'System default ('.$params->sender_email.')');
			$result['customer'] = 'Customer email address (if applicable)';
			$result['sender'] = 'Sender user email address (if applicable)';
			$result['custom'] = 'Specific email address';
			
			return $result;
		}
		
		public function before_delete($id=null)
		{
			if ($this->is_system)
				throw new Phpr_ApplicationException('System email templates cannot be deleted.');
			
			Backend::$events->fireEvent('onDeleteEmailTemplate', $this);
		}
		
		public function send_test_message()
		{
			$content = $this->content;
			$variables = Core_ModuleManager::listEmailVariables(null, true);
			
			$subject = $this->subject;
			foreach ($variables as $section=>$variables)
			{
				foreach ($variables as $variable=>$info)
				{
					$content = str_replace('{'.$variable.'}', $info[1], $content);
					$subject = str_replace('{'.$variable.'}', $info[1], $subject);
				}
			}

			$user = Phpr::$security->getUser();
			$this->subject = $subject;
			
			if ($this->is_system)
				$this->send_to_team(array($user->name=>$user->email), $content, $user->email, $user->name, 'John Smith', 'john@example.com');
			else
				$this->send($user->email, $content, $user->name, $user->email, $user->name, 'John Smith', 'john@example.com');
		}
		
		public function get_reply_address($sender_email, $sender_name, $customer_email, $customer_name)
		{
			$result = array();

			switch ($this->reply_to_mode)
			{
				case 'customer' :
					if (strlen($customer_email))
						$result[$customer_email] = strlen($customer_name) ? $customer_name : $customer_email;
				break;
				case 'sender' :
					if (strlen($sender_email))
						$result[$sender_email] = strlen($sender_name) ? $sender_name : $sender_email;
				break;
				case 'custom' :
					if (strlen($this->reply_to_address))
						$result[$this->reply_to_address] = $this->reply_to_address;
				break;
			}
			
			$params = System_EmailParams::get();

			if (!$result)
				$result[$params->sender_email] = strlen($params->sender_name) ? $params->sender_name : $params->sender_email;
				
			return $result;
		}
		
		/**
		 * Sends email message to a specified customer
		 * @param Shop_Customer $customer Specifies a customer to send a message to
		 * @param string $message_text Specifies a message text
		 */
		public function send_to_customer($customer, $message_text, $sender_email = null, $sender_name = null, $customer_email = null, $customer_name = null, $custom_data = null)
		{
			try
			{
				if(System_EmailParams::get()->allow_recipient_blocking){
					$pass = true;
					if(Shop_CustomerPreferences::get($customer, 'shop:block_non_transactional_emails') == 1){
						$pass = false;
					} else if (Shop_CustomerPreferences::get($customer, 'shop:block_email_template_'.$this->id) == 1){
						$pass = false;
					}
					if(!$pass){
						throw new Phpr_ApplicationException('Email to customer blocked by customer preferences');
					}
				}

				$layout = System_EmailLayout::find_by_code('external');
				$reply_to = $this->get_reply_address($sender_email, $sender_name, $customer_email, $customer_name);

				$customer_email = $customer_email ? $customer_email : $customer->email;
				$customer_name = $customer_name ? $customer_name : $customer->name;
				$subject = $this->subject;
				$settings_obj = null;
				
				$api_data = Backend::$events->fireEvent('core:onBeforeEmailSendToCustomer', $customer, $subject, $message_text, $customer_email, $customer_name, $custom_data, $reply_to, $this);
				foreach ($api_data as $api_data_fields)
				{
					if (array($api_data_fields))
						extract($api_data_fields);
				}

				$message_text = $this->apply_customer_email_vars($customer, $message_text);
				$message_content = $layout->format($message_text);
				$viewData = array('content'=>$message_content, 'custom_data'=>$custom_data);

				$result = Core_Email::send('system', 'email_message', $viewData, $subject, $customer_name, $customer_email, array(), $settings_obj, $reply_to);

				Backend::$events->fireEvent('core:onAfterEmailSendToCustomer', $customer, $this->subject, $message_text, $customer_email, $customer_name, $custom_data, $reply_to, $this, $result);
			}
			catch (exception $ex)
			{
				traceLog($ex->getMessage());
				return false;
			}
			return true;
		}
		
		/**
		 * Sends email message to a a list of the store team members
		 * @param mixed $users Specifies a list of users to send the message to
		 * @param string $message_text Specifies a message text
		 */
		public function send_to_team($users, $message_text, $sender_email = null, $sender_name = null, $customer_email = null, $customer_name = null, $throw_exceptions = false)
		{
			$reply_to = $this->get_reply_address($sender_email, $sender_name, $customer_email, $customer_name);

			try
			{
				$template = System_EmailLayout::find_by_code('system');
				
				$subject = $this->subject;
				$settings_obj = null;

				$api_data = Backend::$events->fireEvent('core:onBeforeEmailSendToTeam', $users, $subject, $message_text, $customer_email, $customer_name, $reply_to, $this);
				foreach ($api_data as $api_data_fields)
				{
					if (array($api_data_fields))
						extract($api_data_fields);
				}
				$message_text = $this->apply_user_email_vars($message_text);
				$message_text = $template->format($message_text);
				$viewData = array('content'=>$message_text);
				Core_Email::sendToList('system', 'email_message', $viewData, $subject, $users, $throw_exceptions, $reply_to, $settings_obj);
				
				Backend::$events->fireEvent('core:onAfterEmailSendToTeam', $users, $subject, $message_text, $customer_email, $customer_name, $reply_to, $this);
			}
			catch (exception $ex)
			{
				if ($throw_exceptions)
					throw $ex;
			}
		}
		
		/**
		 * Sends email message to a specified email address
		 * @param string $email Specifies an email address to send the message to
		 * @param string $message_text Specifies a message text
		 * @param string $name Specifies a recipient name
		 */
		public function send($email, $message_text, $name = null, $sender_email = null, $sender_name = null, $customer_email = null, $customer_name = null)
		{
			if (!$name)
				$name = $email;
				
			$template = System_EmailLayout::find_by_code('external');
			$message_text = $template->format($message_text);

			$viewData = array('content'=>$message_text);
			$reply_to = $this->get_reply_address($sender_email, $sender_name, $customer_email, $customer_name);
			
			Core_Email::send('system', 'email_message', $viewData, $this->subject, $name, $email, array(), null, $reply_to);
		}

		public function apply_customer_email_vars($customer, $message_text){
			$value_set = array(
				'this_template_unsubscribe_url' => null,
				'all_template_unsubscribe_url' => null
			);
			$params = System_EmailParams::get();
			if($params->allow_recipient_blocking){
				$pref = Shop_CustomerPreferences::get_customer_preference($customer, 'shop:block_non_transactional_emails');
				$value_set['all_template_unsubscribe_url'] = $pref->generate_preference_url(1,'/');
				$pref = Shop_CustomerPreferences::get_customer_preference($customer, 'shop:block_email_template_'.$this->id);
				$value_set['this_template_unsubscribe_url'] = $pref->generate_preference_url(1,'/');
			}
			foreach($value_set as $var => $value){
				$message_text = str_replace('{'.$var.'}', $value, $message_text);
			}

			return $message_text;
		}

		public function apply_user_email_vars($message_text, $user=null){
			$value_set = array(
				'this_template_unsubscribe_url' => null,
				'all_template_unsubscribe_url' => null
			);
			foreach($value_set as $var => $value){
				$message_text = str_replace('{'.$var.'}', $value, $message_text);
			}
			//@todo create unsub links for backend that require login
			return $message_text;
		}




		/*
		 * Event descriptions
		 */

		/**
		 * Triggered when a user tries to delete an email template in the Administration Area. 
		 * The handler can throw an exception to cancel the template deletion. Example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('onDeleteEmailTemplate', $this, 'process_template_deletion');
		 * }
		 *  
		 * public function process_template_deletion($template)
		 * {
		 *   if ($template->code == 'payment_notificaiton')
		 *     throw new Phpr_ApplicationException('You cannot delete this email template.');
		 * }
		 * </pre>
		 * @event onDeleteEmailTemplate
		 * @package core.events

		 * @param System_EmailTemplate $template The email template object.
		 */
		private function event_onDeleteEmailTemplate($template) {}
			
		/**
		 * Triggered after an email notification is sent to a customer. 
		 * Event handler example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('core:onAfterEmailSendToCustomer', $this, 'after_email_send_to_customer');
		 * }
		 *  
		 * public function after_email_send_to_customer($customer, $subject, $message_text, $customer_email, $customer_name, $custom_data)
		 * {
		 *   // Do something
		 * }
		 * </pre>
		 * @event core:onAfterEmailSendToCustomer
		 * @package core.events
		 * @see core:onBeforeEmailSendToCustomer

		 * @param Shop_Customer $customer Customer object
		 * @param string $subject Specifies the message subject
		 * @param string $text Specifies the message text
		 * @param string $customer_email Specifies the customer email address. 
		 * In some cases customer email address and name can be different from the 
		 * email and name specified in the <em>$customer</em> object.
		 * @param string $customer_name Specifies the customer name. 
		 * @param mixed $custom_data Extra parameters passed to the email template. 
		 * @param array $reply_to An array containing the reply-to email and name: array('john@example.com'=>'John Smith')
		 * @param System_EmailTemplate $template Specifies the email template
		 * @param mixed $api_result The result obtained from a third-party core:onSendEmail event handler. 
		 * This value is set only if the core:onSendEmail event was handled by a third-party module.
		 */
		private function event_onAfterEmailSendToCustomer($customer, $subject, $message_text, $customer_email, $customer_name, $custom_data, $reply_to, $template, $api_result) {}
			
		/**
		 * Triggered before an email notification is sent to a customer. 
		 * The event handler can override some variables before the message is sent. The handler function should return
		 * an associative array containing variable names and values. The supported variables are:
		 * <ul>
		 *   <li>subject</li>
		 *   <li>message_text</li>
		 *   <li>customer_email</li>
		 *   <li>customer_name</li>
		 *   <li>reply_to</li>
		 *   <li>settings_obj</li>
		 * </ul>
		 * Example: 
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('core:onBeforeEmailSendToCustomer', $this, 'before_email_send_to_customer');
		 * }
		 *  
		 * public function before_email_send_to_customer($customer, $subject, $message_text, $customer_email, $customer_name, $custom_data, $reply_to)
		 * {
		 *   $result = array(
		 *     'reply_to'=>array('john@example.com'=>'John Smith')
		 *   );
		 *
		 *   return $result;
		 * }
		 * </pre>
		 * @event core:onBeforeEmailSendToCustomer
		 * @see core:onAfterEmailSendToCustomer
		 * @package core.events

		 * @param Shop_Customer $customer Customer object
		 * @param string $subject Specifies the message subject
		 * @param string $text Specifies the message text
		 * @param string $customer_email Specifies the customer email address. 
		 * In some cases customer email address and name can be different from the 
		 * email and name specified in the <em>$customer</em> object.
		 * @param string $customer_name Specifies the customer name. 
		 * @param mixed $custom_data Extra parameters passed to the email template. 
		 * @param array $reply_to An array containing the reply-to email and name: array('john@example.com'=>'John Smith')
		 * @param System_EmailTemplate $template Specifies the email template
		 * @return array Returns an array of overridden variables.
		 */
		private function event_onBeforeEmailSendToCustomer($customer, $subject, $message_text, $customer_email, $customer_name, $custom_data, $reply_to, $template) {}

		/**
		 * Triggered after an email notification is sent to backend users.
		 * Event handler example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('core:onAfterEmailSendToTeam', $this, 'after_email_send_to_team');
		 * }
		 *  
		 * public function after_email_send_to_team($users, $subject, $message_text, $customer_email, $customer_name)
		 * {
		 *   // Do something
		 * }
		 * </pre>
		 * @event core:onAfterEmailSendToTeam
		 * @see core:onBeforeEmailSendToTeam
		 * @package core.events

		 * @param mixed $users Specifies a list of users to send the message to.
		 * The value can be either a {@link Db_DataCollection data collection} or array.
		 * @param string $subject Specifies the message subject
		 * @param string $text Specifies the message text
		 * @param string $customer_email Specifies the customer email address. 
		 * In some cases customer email address and name can be different from the 
		 * email and name specified in the <em>$customer</em> object.
		 * @param string $customer_name Specifies the customer name. 
		 * @param array $reply_to An array containing the reply-to email and name: array('john@example.com'=>'John Smith')
		 * @param System_EmailTemplate $template Specifies the email template
		 */
		private function event_onAfterEmailSendToTeam($users, $subject, $message_text, $customer_email, $customer_name, $reply_to, $template) {}

		/**
		 * Triggered before an email notification is sent to users.
		 * The event handler can override some variables before the message is sent. The handler function should return
		 * an associative array containing variable names and values. The supported variables are:
		 * <ul>
		 *   <li>subject</li>
		 *   <li>message_text</li>
		 *   <li>customer_email</li>
		 *   <li>customer_name</li>
		 *   <li>reply_to</li>
		 *   <li>settings_obj</li>
		 * </ul>
		 * Example: 
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('core:onBeforeEmailSendToTeam', $this, 'before_email_send_to_team');
		 * }
		 *  
		 * public function before_email_send_to_team($users, $subject, $message_text, $customer_email, $customer_name, $reply_to)
		 * {
		 *   $result = array(
		 *     'reply_to'=>array('john@example.com'=>'John Smith')
		 *   );
		 *
		 *   return $result;
		 * }
		 * </pre>
		 * @event core:onBeforeEmailSendToTeam
		 * @see core:onAfterEmailSendToTeam
		 * @package core.events

		 * @param mixed $users Specifies a list of users to send the message to.
		 * The value can be either a {@link Db_DataCollection data collection} or array.
		 * @param string $subject Specifies the message subject
		 * @param string $text Specifies the message text
		 * @param string $customer_email Specifies the customer email address. 
		 * In some cases customer email address and name can be different from the 
		 * email and name specified in the <em>$customer</em> object.
		 * @param string $customer_name Specifies the customer name. 
		 * @param array $reply_to An array containing the reply-to email and name: array('john@example.com'=>'John Smith')
		 * @param System_EmailTemplate $template Specifies the email template
		 * @return array Returns an array of overridden variables.
		 * The supported variables are: subject, message_text, customer_email, customer_name, reply_to, settings_obj
		 */
		private function event_onBeforeEmailSendToTeam($users, $subject, $message_text, $customer_email, $customer_name, $reply_to, $template) {}
			
		/**
		 * Allows to define new columns in the email template model.
		 * The event handler should accept two parameters - the email template object and the form execution context string. 
		 * To add new columns to the email template model, call the  {@link Db_ActiveRecord::define_column() define_column()} method of
		 * the template object. 
		 * Before you add new columns to the model, you should add them to the database (the <em>system_email_templates</em> table). 
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('core:onExtendEmailTemplateModel', $this, 'extend_email_template_model');
		 *   Backend::$events->addEvent('core:onExtendEmailTemplateForm', $this, 'extend_email_template_form');
		 * }
		 * 
		 * public function extend_email_template_model($template, $context)
		 * {
		 *   $template->define_column('x_extra_description', 'Extra description');
		 * }
		 * 
		 * public function extend_email_template_form($template, $context)
		 * {
		 *   $template->add_form_field('x_extra_description')->tab('Description');
		 * }
		 * </pre>
		 * @event core:onExtendEmailTemplateModel
		 * @package core.events
		 * @see core:onExtendEmailTemplateForm
		 * @see core:onGetEmailTemplateFieldOptions
		 * @param System_EmailTemplate $template Specifies the email template object to extend.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendEmailTemplateModel($template, $context) {}

		/**
		 * Allows to add new fields to the Create/Edit Email Template form.
		 * Usually this event is used together with the {@link core:onExtendEmailTemplateModel} event.
		 * The event handler should accept two parameters - the email template object and the form execution context string. 
		 * To add new fields to the form, call the {@link Db_ActiveRecord::add_form_field() add_form_field()} method of
		 * the template object. 
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('core:onExtendEmailTemplateModel', $this, 'extend_email_template_model');
		 *   Backend::$events->addEvent('core:onExtendEmailTemplateForm', $this, 'extend_email_template_form');
		 * }
		 * 
		 * public function extend_email_template_model($template, $context)
		 * {
		 *   $template->define_column('x_extra_description', 'Extra description');
		 * }
		 * 
		 * public function extend_email_template_form($template, $context)
		 * {
		 *   $template->add_form_field('x_extra_description')->tab('Description');
		 * }
		 * </pre>
		 * @event core:onExtendEmailTemplateForm
		 * @package core.events
		 * @see core:onExtendEmailTemplateModel
		 * @see core:onGetEmailTemplateFieldOptions
		 * @param System_EmailTemplate $template Specifies the email template object to extend.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendEmailTemplateForm($template, $context) {}

		/**
		 * Allows to populate drop-down, radio- or checkbox list fields, which have been added with {@link core:onExtendEmailTemplateModel} event.
		 * Usually you do not need to use this event for fields which represent 
		 * data relations. But if you want a standard
		 * field (corresponding an integer-typed database column, for example), to be rendered as a drop-down list, you should 
		 * handle this event.
		 *
		 * The event handler should accept 2 parameters - the field name and a current field value. If the current
		 * field value is -1, the handler should return an array containing a list of options. If the current 
		 * field value is not -1, the handler should return a string (label), corresponding the value.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('core:onExtendEmailTemplateModel', $this, 'extend_email_template_model');
		 *   Backend::$events->addEvent('core:onExtendEmailTemplateForm', $this, 'extend_email_template_form');
		 *   Backend::$events->addEvent('core:onGetEmailTemplateFieldOptions', $this, 'get_email_template_field_options');
		 * }
		 * 
		 * public function extend_email_template_model($template, $context)
		 * {
		 *   $template->define_column('x_color', 'Color');
		 * }
		 * 
		 * public function extend_email_template_form($template, $context)
		 * {
		 *   $template->add_form_field('x_color')->tab('Description')->renderAs(frm_dropdown);
		 * }
		 *
		 * public function get_email_template_field_options($field_name, $current_key_value)
		 * {
		 *   if ($field_name == 'x_color')
		 *   {
		 *     $options = array(
		 *       0 => 'Red',
		 *       1 => 'Green',
		 *       2 => 'Blue'
		 *     );
		 *
		 *     if ($current_key_value == -1)
		 *       return $options;
		 *
		 *     if (array_key_exists($current_key_value, $options))
		 *       return $options[$current_key_value];
		 *   }
		 * }
		 * </pre>
		 * @event core:onGetEmailTemplateFieldOptions
		 * @package core.events
		 * @see core:onExtendEmailTemplateModel
		 * @see core:onExtendEmailTemplateForm
		 * @param string $db_name Specifies the field name.
		 * @param string $field_value Specifies the field value.
		 * @return mixed Returns a list of options or a specific option label.
		 */
		private function event_onGetEmailTemplateFieldOptions($db_name, $field_value) {}
	}
	
?>