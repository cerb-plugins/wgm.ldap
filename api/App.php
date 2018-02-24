<?php
if(class_exists('Extension_LoginAuthenticator',true)):
class ChLdapLoginModule extends Extension_LoginAuthenticator {
	function render() {
		$request = DevblocksPlatform::getHttpRequest();
		$stack = $request->path;
		
		@array_shift($stack); // login
		
		// draws HTML form of controls needed for login information
		$tpl = DevblocksPlatform::services()->template();
		
		@$email = DevblocksPlatform::importGPC($_REQUEST['email'],'string','');
		$tpl->assign('email', $email);
		
		@$error = DevblocksPlatform::importGPC($_REQUEST['error'],'string','');
		$tpl->assign('error', $error);
		
		$tpl->display('devblocks:wgm.ldap::login/login_ldap.tpl');
	}
	
	function authenticate() {
		@$email = DevblocksPlatform::importGPC($_REQUEST['email'],'string','');
		@$password = DevblocksPlatform::importGPC($_REQUEST['password'],'string','');
		
		// Check for extension
		if(!extension_loaded('ldap'))
			return false;
		
		if(empty($email) || empty($password))
			return false;
		
		// Look up worker by email
		if(null == ($address = DAO_Address::getByEmail($email)))
			return false;
		
		if(null == ($worker = $address->getWorker()))
			return false;
		
		if($worker->auth_extension_id != $this->manifest->id)
			return false;
		
		if($worker->is_disabled)
			return false;
		
		$login_params = DevblocksPlatform::getPluginSetting('wgm.ldap', 'config_json', [], true);
		
		if(!isset($login_params['connected_account_id']) 
			|| false == ($account = DAO_ConnectedAccount::get($login_params['connected_account_id']))
			|| !($account instanceof Model_ConnectedAccount)
			)
			return false;
		
		$account_params = $account->decryptParams();
		
		$ldap_settings = [
			'host' => @$account_params['host'],
			'port' => @$account_params['port'] ?: 389,
			'username' => @$account_params['bind_dn'],
			'password' => @$account_params['bind_password'],
			
			'context_search' => @$login_params['context_search'],
			'field_email' => @$login_params['field_email'],
			'field_firstname' => @$login_params['field_firstname'],
			'field_lastname' => @$login_params['field_lastname'],
		];
		
		@$ldap = ldap_connect($ldap_settings['host'], $ldap_settings['port']);
		
		if(!$ldap)
			return false;
		
		ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
		
		@$login = ldap_bind($ldap, $ldap_settings['username'], $ldap_settings['password']);
		
		if(!$login)
			return false;
	
		$query = sprintf("(%s=%s)", $ldap_settings['field_email'], $address->email);
		@$results = ldap_search($ldap, $ldap_settings['context_search'], $query);
		@$entries = ldap_get_entries($ldap, $results);
		@$count = intval($entries['count']);
		
		if(empty($count))
			return false;
		
		// Try to bind as the worker's DN
		
		$dn = $entries[0]['dn'];
		
		if(@ldap_bind($ldap, $dn, $password)) {
			@ldap_unbind($ldap);
			return $worker;
		}
		
		@ldap_unbind($ldap);
		
		return false;
	}
};
endif;

if(class_exists('Extension_ScLoginAuthenticator',true)):
class ScLdapLoginAuthenticator extends Extension_ScLoginAuthenticator {
	function writeResponse(DevblocksHttpResponse $response) {
		$tpl = DevblocksPlatform::services()->templateSandbox();
		$umsession = ChPortalHelper::getSession();
		
		$stack = $response->path;
		@$module = array_shift($stack);
		
		switch($module) {
			default:
				$login_params = DAO_CommunityToolProperty::get(ChPortalHelper::getCode(), 'wgm.ldap.config_json', '{}', true);
				
				$params = [
					'login_prompt' => @$login_params['login_prompt'],
				];
				$tpl->assign('params', $params);
				
				$tpl->display("devblocks:wgm.ldap:portal_".ChPortalHelper::getCode().":support_center/login/ldap.tpl");
				break;
		}
	}
	
	function renderConfigForm(Model_CommunityTool $instance) {
		$tpl = DevblocksPlatform::services()->template();

		$tpl->assign('instance', $instance);
		$tpl->assign('extension', $this);
		
		$login_params = DAO_CommunityToolProperty::get($instance->code, 'wgm.ldap.config_json', '{}', true);
		$tpl->assign('login_params', $login_params);
		
		$tpl->display('devblocks:wgm.ldap::setup/config.tpl');
	}
	
	function saveConfiguration(Model_CommunityTool $instance) {
		@$params = DevblocksPlatform::importGPC($_REQUEST['login_params'], 'array', []);
		
		if(!isset($params[$this->id]))
			return;
		
		$params = $params[$this->id];
		
		DAO_CommunityToolProperty::set($instance->code, 'wgm.ldap.config_json', json_encode($params));
		return true;
	}
	
	function authenticateAction() {
		$umsession = ChPortalHelper::getSession();
		$url_writer = DevblocksPlatform::services()->url();
		$tpl = DevblocksPlatform::services()->template();

		// Clear the past session
		$umsession->logout();
		
		try {
			@$email = DevblocksPlatform::importGPC($_REQUEST['email'],'string','');
			@$password = DevblocksPlatform::importGPC($_REQUEST['password'],'string','');
			
			// Check for extension
			if(!extension_loaded('ldap'))
				throw new Exception("The authentication server is offline. Please try again later.");
			
			if(empty($email))
				throw new Exception("An email address is required.");
			
			if(empty($password))
				throw new Exception("A password is required.");
			
			// Validate email address
			
			$valid_email = imap_rfc822_parse_adrlist($email, 'host');
			
			if(empty($valid_email) || !is_array($valid_email) || empty($valid_email[0]->host) || $valid_email[0]->host=='host')
				throw new Exception("Please provide a valid email address.");
			
			$email = $valid_email[0]->mailbox . '@' . $valid_email[0]->host;
			
			$login_params = DAO_CommunityToolProperty::get(ChPortalHelper::getCode(), 'wgm.ldap.config_json', '{}', true);
			
			if(!isset($login_params['connected_account_id']) || false == ($account = DAO_ConnectedAccount::get($login_params['connected_account_id']))) {
				throw new Exception("The authentication server is offline. Please try again later.");
			}
			
			$account_params = $account->decryptParams();
			
			$ldap_settings = [
				'host' => @$account_params['host'],
				'port' => @$account_params['port'] ?: 389,
				'username' => @$account_params['bind_dn'],
				'password' => @$account_params['bind_password'],
				
				'context_search' => @$login_params['context_search'],
				'field_email' => @$login_params['field_email'],
				'field_firstname' => @$login_params['field_firstname'],
				'field_lastname' => @$login_params['field_lastname'],
			];

			@$ldap = ldap_connect($ldap_settings['host'], $ldap_settings['port']);
			
			if(!$ldap)
				throw new Exception("The authentication server is offline. Please try again later.");
			
			ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
			ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
			
			@$login = ldap_bind($ldap, $ldap_settings['username'], $ldap_settings['password']);
			
			if(!$login)
				throw new Exception("The authentication server is offline. Please try again later.");
			
			$query = sprintf("(%s=%s)", $ldap_settings['field_email'], $email);
			@$results = ldap_search($ldap, $ldap_settings['context_search'], $query);
			@$entries = ldap_get_entries($ldap, $results);
			@$count = intval($entries['count']);
			
			if(empty($count))
				throw new Exception("User not found.");
			
			// Rebind as the customer DN
			
			$dn = $entries[0]['dn'];
			
			if(@ldap_bind($ldap, $dn, $password)) {
				
				// Look up address by email
				if(null == ($address = DAO_Address::lookupAddress($email))) {
					$address_id = DAO_Address::create(array(
						DAO_Address::EMAIL => $email,
					));
					
					if(null == ($address = DAO_Address::get($address_id)))
						throw new Exception("Your account could not be created. Please try again later.");
					
					if($address->is_banned)
						throw new Exception("email.unavailable");
				}
				
				// See if the contact person exists or not
				if(!empty($address->contact_id)) {
					if(null != ($contact = DAO_Contact::get($address->contact_id))) {
						$umsession->login($contact);
						
						$original_path = $umsession->getProperty('login.original_path', '');
						$path = !empty($original_path) ? explode('/', $original_path) : array();
						
						DevblocksPlatform::redirect(new DevblocksHttpResponse($path));
						exit;
					}
					
				} else { // create
					$given_name = @$entries[0][DevblocksPlatform::strLower($ldap_settings['field_firstname'])][0];
					$surname = @$entries[0][DevblocksPlatform::strLower($ldap_settings['field_lastname'])][0];
					
					$fields = array(
						DAO_Contact::CREATED_AT => time(),
						DAO_Contact::PRIMARY_EMAIL_ID => $address->id,
						DAO_Contact::FIRST_NAME => $given_name ?: '',
						DAO_Contact::LAST_NAME => $surname ?: '',
					);
					$contact_id = DAO_Contact::create($fields);
					
					if(null != ($contact = DAO_Contact::get($contact_id))) {
						DAO_Address::update($address->id, array(
							DAO_Address::CONTACT_ID => $contact->id,
						));
						
						$umsession->login($contact);
						
						@ldap_unbind($ldap);
						
						$original_path = $umsession->getProperty('login.original_path', 'account');
						$path = !empty($original_path) ? explode('/', $original_path) : array();
						
						DevblocksPlatform::redirect(new DevblocksHttpResponse($path));
						exit;
					}
				}
				
			} else {
				throw new Exception("auth.failed");
			}
					
		} catch (Exception $e) {
			$tpl->assign('error', $e->getMessage());
		}
		
		@ldap_unbind($ldap);
		
		DevblocksPlatform::setHttpResponse(new DevblocksHttpResponse(array('portal',ChPortalHelper::getCode(),'login')));
	}
};
endif;

if(class_exists('Extension_PageMenuItem')):
class WgmLdap_SetupPluginsMenuItem extends Extension_PageMenuItem {
	const POINT = 'ldap.setup.menu.plugins.ldap';
	
	function render() {
		$tpl = DevblocksPlatform::services()->template();
		$tpl->assign('extension', $this);
		$tpl->display('devblocks:wgm.ldap::setup/menu_item.tpl');
	}
};
endif;

if(class_exists('Extension_PageSection')):
class WgmLdap_SetupSection extends Extension_PageSection {
	const ID = 'ldap.setup.section';
	
	function render() {
		// check whether extensions are loaded or not
		$extensions = array(
			'ldap' => extension_loaded('ldap')
		);
		$tpl = DevblocksPlatform::services()->template();

		$visit = CerberusApplication::getVisit();
		$visit->set(ChConfigurationPage::ID, 'ldap');
		
		$params = DevblocksPlatform::getPluginSetting('wgm.ldap', 'config_json', [], true);
		$tpl->assign('params', $params);
		
		$tpl->assign('extensions', $extensions);
		
		$tpl->display('devblocks:wgm.ldap::setup/index.tpl');
	}
	
	function saveJsonAction() {
		try {
			if(!extension_loaded('ldap'))
				throw new Exception("The 'ldap' extension is not enabled.");
			
			/*
			 * LDAP Auth
			 */
			
			@$params = DevblocksPlatform::importGPC($_REQUEST['params'],'array',[]);
			
			if(!isset($params['connected_account_id']) 
				|| false == ($account = DAO_ConnectedAccount::get($params['connected_account_id']))
				|| !($account instanceof Model_ConnectedAccount)
				)
				throw new Exception("The LDAP connected account is invalid.");
			
			$ldap_params = $account->decryptParams();
				
			@$ldap = ldap_connect(@$ldap_params['host'], @$ldap_params['port']);
			
			if(!$ldap)
				throw new Exception('LDAP server error: ' . ldap_error($ldap));
			
			ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
			ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
			
			@$login = ldap_bind($ldap, @$ldap_params['bind_dn'], @$ldap_params['bind_password']);
			
			if(!$login)
				throw new Exception('LDAP server error: ' . ldap_error($ldap));
			
			/*
			 * Worker auth
			 */
			
			if(empty(@$params['context_search']))
				throw new Exception("The 'search context' is required.");
			
			if(empty(@$params['field_email']))
				throw new Exception("The 'email field' is required.");
			
			$query = sprintf("(%s=*)", @$params['field_email']);
			@$results = ldap_search($ldap, @$params['context_search'], $query, array(@$params['field_email']), 0, 1);
			
			if(!$results)
				throw new Exception('LDAP search error: ' . ldap_error($ldap));
			
			DevblocksPlatform::setPluginSetting('wgm.ldap','config_json', $params, true);
			
			@ldap_unbind($ldap);
			
			echo json_encode(array('status'=>true,'message'=>'Saved!'));
			return;
			
		} catch (Exception $e) {
			@ldap_unbind($ldap);
			
			echo json_encode(array('status'=>false,'error'=>$e->getMessage()));
			return;
		}
	}
	
};
endif;

class ServiceProvider_Ldap extends Extension_ServiceProvider {
	const ID = 'wgm.ldap.service.provider';
	
	private function _testLdap($params) {
		if(!extension_loaded('ldap'))
			return "The 'ldap' extension is not enabled.";
		
		if(!isset($params['host']) || empty($params['host']))
			return "The 'Host' is required.";
		
		if(!isset($params['port']) || empty($params['port']))
			return "The 'Port' is required.";
		
		if(!isset($params['bind_dn']) || empty($params['bind_dn']))
			return "The 'Bind DN' is required.";
		
		if(!isset($params['bind_password']) || empty($params['bind_password']))
			return "The 'Bind Password' is required.";
		
		// Test the credentials
		
		@$ldap = ldap_connect($params['host'], $params['port']);
		
		if(!$ldap)
			return ldap_error($ldap);
		
		ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
		
		@$login = ldap_bind($ldap, $params['bind_dn'], $params['bind_password']);
		
		if(empty($login))
			return ldap_error($ldap);
		
		return true;
	}
	
	function renderConfigForm(Model_ConnectedAccount $account) {
		$tpl = DevblocksPlatform::services()->template();
		$active_worker = CerberusApplication::getActiveWorker();
		
		$params = $account->decryptParams($active_worker);
		$tpl->assign('params', $params);
		
		$tpl->display('devblocks:wgm.ldap::provider/edit_params.tpl');
	}
	
	function saveConfigForm(Model_ConnectedAccount $account, array &$params) {
		@$edit_params = DevblocksPlatform::importGPC($_REQUEST['params'], 'array', []);
		
		if(true !== ($result = $this->_testLdap($edit_params)))
			return $result;
		
		foreach($edit_params as $k => $v) {
			switch($k) {
				case 'host':
				case 'port':
				case 'bind_dn':
				case 'bind_password':
					$params[$k] = $v;
					break;
			}
		}
		
		return true;
	}
}