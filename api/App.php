<?php
if(class_exists('Extension_LoginAuthenticator',true)):
class ChLdapLoginModule extends Extension_LoginAuthenticator {
	function renderLoginForm() {
		$request = DevblocksPlatform::getHttpRequest();
		$stack = $request->path;
		
		@array_shift($stack); // login
		
		// draws HTML form of controls needed for login information
		$tpl = DevblocksPlatform::getTemplateService();
		
		// Must be a valid page controller
		@$redir_path = explode('/',urldecode(DevblocksPlatform::importGPC($_REQUEST["url"],"string","")));
		if(is_array($redir_path) && isset($redir_path[0]) && CerberusApplication::getPageManifestByUri($redir_path[0]))
			$tpl->assign('original_path', implode('/',$redir_path));
		
		switch(array_shift($stack)) {
			case 'too_many':
				@$secs = array_shift($stack);
				$tpl->assign('error', sprintf("The maximum number of simultaneous workers are currently signed on.  The next session expires in %s.", ltrim(_DevblocksTemplateManager::modifier_devblocks_prettytime($secs,true),'+')));
				break;
			case 'failed':
				$tpl->assign('error', 'Login failed.');
				break;
		}
		
		$tpl->display('devblocks:wgm.ldap::login/login_ldap.tpl');
	}
	
	function authenticate() {
		@$email = DevblocksPlatform::importGPC($_REQUEST['email'],'string','');
		@$password = DevblocksPlatform::importGPC($_REQUEST['password'],'string','');
		
		// Check for extension
		if(!extension_loaded('ldap'))
			return false;
		
		// Look up worker by email
		if(null == ($address = DAO_AddressToWorker::getByAddress($email)))
			return false;
		
		if(null == ($worker = DAO_Worker::get($address->worker_id)))
			return false;
		
		$ldap_settings = array(
			'host' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'priv_auth_host', ''),
			'port' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'priv_auth_port', '389'),
			'username' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'priv_auth_username', ''),
			'password' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'priv_auth_password', ''),
			'context_search' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'priv_auth_context_search', ''),
			'field_email' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'priv_auth_field_email', ''),
			'field_firstname' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'priv_auth_field_firstname', ''),
			'field_lastname' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'priv_auth_field_lastname', ''),
			'field_password' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'priv_auth_field_password', ''),
			'field_password_type' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'priv_auth_field_password_type', ''),
		);
		
		@$ldap = ldap_connect($ldap_settings['host'], $ldap_settings['port']);
		
		if(!$ldap)
			return false;
		
		ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
		
		@$login = ldap_bind($ldap, $ldap_settings['username'], $ldap_settings['password']);
		
		if(!$login)
			return false;
	
		$query = sprintf("(%s=%s)", $ldap_settings['field_email'], $address->address);
		@$results = ldap_search($ldap, $ldap_settings['context_search'], $query);
		@$entries = ldap_get_entries($ldap, $results);
		@$count = intval($entries['count']);

		@ldap_unbind($ldap);
		
		if(empty($count))
			return false;
		
		// MD5, SHA1, plaintext, etc.
		@$entry_pass = $entries[0][strtolower($ldap_settings['field_password'])][0];

		switch($ldap_settings['field_password_type']) {
			case 'md5':
				$password = md5($password);
				break;
			case 'sha1':
				$password = sha1($password);
				break;
		}
		
		if($entry_pass == $password) {
			$session = DevblocksPlatform::getSessionService();
			$visit = new CerberusVisit();
			$visit->setWorker($worker);
			$session->setVisit($visit);
			return true;
		}
		
		return false;
	}
};
endif;

if(class_exists('Extension_ScLoginAuthenticator',true)):
class ScLdapLoginAuthenticator extends Extension_ScLoginAuthenticator {
	function writeResponse(DevblocksHttpResponse $response) {
		$tpl = DevblocksPlatform::getTemplateService();
		$umsession = ChPortalHelper::getSession();
		
		$stack = $response->path;
		@$module = array_shift($stack);
		
		switch($module) {
			default:
				$tpl->display("devblocks:wgm.ldap:portal_".ChPortalHelper::getCode().":support_center/login/ldap.tpl");
				break;
		}
	}	
	
	function authenticateAction() {
		$umsession = ChPortalHelper::getSession();
		$url_writer = DevblocksPlatform::getUrlService();
		$openid = DevblocksPlatform::getOpenIDService();
		$tpl = DevblocksPlatform::getTemplateService();

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
			
			$valid_email = imap_rfc822_parse_adrlist($email,'host');
			
			if(empty($valid_email) || !is_array($valid_email) || empty($valid_email[0]->host) || $valid_email[0]->host=='host')
				throw new Exception("Please provide a valid email address.");
			
			$email = $valid_email[0]->mailbox . '@' . $valid_email[0]->host; 

			// LDAP
			$ldap_settings = array(
				'host' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'pub_auth_host', ''),
				'port' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'pub_auth_port', '389'),
				'username' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'pub_auth_username', ''),
				'password' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'pub_auth_password', ''),
				'context_search' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'pub_auth_context_search', ''),
				'field_email' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'pub_auth_field_email', ''),
				'field_firstname' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'pub_auth_field_firstname', ''),
				'field_lastname' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'pub_auth_field_lastname', ''),
				'field_password' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'pub_auth_field_password', ''),
				'field_password_type' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'pub_auth_field_password_type', ''),
			);
			
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
			
			// MD5, SHA1, plaintext, etc.
			@$entry_pass = $entries[0][strtolower($ldap_settings['field_password'])][0];
			
			switch($ldap_settings['field_password_type']) {
				case 'md5':
					$password = md5($password);
					break;
				case 'sha1':
					$password = sha1($password);
					break;
			}
			
			if($entry_pass == $password) {
				// Look up address by email
				if(null == ($address = DAO_Address::lookupAddress($email))) {
					$address_id = DAO_Address::create(array(
						DAO_Address::EMAIL => $email,
						DAO_Address::FIRST_NAME => @$entries[0][strtolower($ldap_settings['field_firstname'])][0],
						DAO_Address::LAST_NAME => @$entries[0][strtolower($ldap_settings['field_lastname'])][0],
					));
					
					if(null == ($address = DAO_Address::get($address_id)))
						throw new Exception("Your account could not be created. Please try again later.");
				}
				
				// See if the contact person exists or not
				if(!empty($address->contact_person_id)) {
					if(null != ($contact = DAO_ContactPerson::get($address->contact_person_id))) {
						$umsession->login($contact);
						header("Location: " . $url_writer->write('', true));
						exit;
					}
					
				} else { // create
					$fields = array(
						DAO_ContactPerson::CREATED => time(),
						DAO_ContactPerson::EMAIL_ID => $address->id,
					);
					$contact_id = DAO_ContactPerson::create($fields);
					
					if(null != ($contact = DAO_ContactPerson::get($contact_id))) {
						DAO_Address::update($address->id, array(
							DAO_Address::CONTACT_PERSON_ID => $contact->id,
						));
						
						$umsession->login($contact);
						header("Location: " . $url_writer->write('account', true));
						exit;
					}
				}
				
			} else {
				throw new Exception("Invalid password.");
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
		$tpl = DevblocksPlatform::getTemplateService();
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
		$tpl = DevblocksPlatform::getTemplateService();

		$visit = CerberusApplication::getVisit();
		$visit->set(ChConfigurationPage::ID, 'ldap');
		
		$params = array(
			'priv_auth_host' => DevblocksPlatform::getPluginSetting('wgm.ldap','priv_auth_host',''),
			'priv_auth_port' => DevblocksPlatform::getPluginSetting('wgm.ldap','priv_auth_port',389),
			'priv_auth_username' => DevblocksPlatform::getPluginSetting('wgm.ldap','priv_auth_username',''),
			'priv_auth_password' => DevblocksPlatform::getPluginSetting('wgm.ldap','priv_auth_password',''),
			'priv_auth_context_search' => DevblocksPlatform::getPluginSetting('wgm.ldap','priv_auth_context_search',''),
			'priv_auth_field_email' => DevblocksPlatform::getPluginSetting('wgm.ldap','priv_auth_field_email',''),
			'priv_auth_field_firstname' => DevblocksPlatform::getPluginSetting('wgm.ldap','priv_auth_field_firstname',''),
			'priv_auth_field_lastname' => DevblocksPlatform::getPluginSetting('wgm.ldap','priv_auth_field_lastname',''),
			'priv_auth_field_password' => DevblocksPlatform::getPluginSetting('wgm.ldap','priv_auth_field_password',''),
			'priv_auth_field_password_type' => DevblocksPlatform::getPluginSetting('wgm.ldap','priv_auth_field_password_type',''),
			
			'pub_auth_host' => DevblocksPlatform::getPluginSetting('wgm.ldap','pub_auth_host',''),
			'pub_auth_port' => DevblocksPlatform::getPluginSetting('wgm.ldap','pub_auth_port',389),
			'pub_auth_username' => DevblocksPlatform::getPluginSetting('wgm.ldap','pub_auth_username',''),
			'pub_auth_password' => DevblocksPlatform::getPluginSetting('wgm.ldap','pub_auth_password',''),
			'pub_auth_context_search' => DevblocksPlatform::getPluginSetting('wgm.ldap','pub_auth_context_search',''),
			'pub_auth_field_email' => DevblocksPlatform::getPluginSetting('wgm.ldap','pub_auth_field_email',''),
			'pub_auth_field_firstname' => DevblocksPlatform::getPluginSetting('wgm.ldap','pub_auth_field_firstname',''),
			'pub_auth_field_lastname' => DevblocksPlatform::getPluginSetting('wgm.ldap','pub_auth_field_lastname',''),
			'pub_auth_field_password' => DevblocksPlatform::getPluginSetting('wgm.ldap','pub_auth_field_password',''),
			'pub_auth_field_password_type' => DevblocksPlatform::getPluginSetting('wgm.ldap','pub_auth_field_password_type',''),
		);
		
		$tpl->assign('params', $params);
		$tpl->assign('extensions', $extensions);
		
		$tpl->display('devblocks:wgm.ldap::setup/index.tpl');
	}
	
	function saveJsonAction() {
		try {
			if(!extension_loaded('ldap'))
				throw new Exception("The 'ldap' extension is not enabled.");
			
			/*
			 * Worker auth
			 */
			
			@$priv_auth_host = DevblocksPlatform::importGPC($_REQUEST['priv_auth_host'],'string','');
			@$priv_auth_port = DevblocksPlatform::importGPC($_REQUEST['priv_auth_port'],'integer',389);
			@$priv_auth_username = DevblocksPlatform::importGPC($_REQUEST['priv_auth_username'],'string','');
			@$priv_auth_password = DevblocksPlatform::importGPC($_REQUEST['priv_auth_password'],'string','');
			@$priv_auth_context_search = DevblocksPlatform::importGPC($_REQUEST['priv_auth_context_search'],'string','');
			@$priv_auth_field_email = DevblocksPlatform::importGPC($_REQUEST['priv_auth_field_email'],'string','');
			@$priv_auth_field_firstname = DevblocksPlatform::importGPC($_REQUEST['priv_auth_field_firstname'],'string','');
			@$priv_auth_field_lastname = DevblocksPlatform::importGPC($_REQUEST['priv_auth_field_lastname'],'string','');
			@$priv_auth_field_password = DevblocksPlatform::importGPC($_REQUEST['priv_auth_field_password'],'string','');
			@$priv_auth_field_password_type = DevblocksPlatform::importGPC($_REQUEST['priv_auth_field_password_type'],'string','');
			
			if(!empty($priv_auth_host) && !empty($priv_auth_username) && !empty($priv_auth_password)) {
				@$ldap = ldap_connect($priv_auth_host, $priv_auth_port);
				
				if(!$ldap)
					throw new Exception("Failed to connect to worker auth host.");
				
				ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
				ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
				
				@$login = ldap_bind($ldap, $priv_auth_username, $priv_auth_password);
				
				if(!$login)
					throw new Exception("Failed to authenticate on worker auth host.");
				
				$query = sprintf("(%s=*)", $priv_auth_field_email);
				@$results = ldap_search($ldap, $priv_auth_context_search, $query, array($priv_auth_field_email), 0, 1);
				
				if(!$results)
					throw new Exception("Failed to retrieve worker search results.");
				
				ldap_unbind($ldap);
			}
			
			DevblocksPlatform::setPluginSetting('wgm.ldap','priv_auth_host',$priv_auth_host);
			DevblocksPlatform::setPluginSetting('wgm.ldap','priv_auth_port',$priv_auth_port);
			DevblocksPlatform::setPluginSetting('wgm.ldap','priv_auth_username',$priv_auth_username);
			DevblocksPlatform::setPluginSetting('wgm.ldap','priv_auth_password',$priv_auth_password);
			DevblocksPlatform::setPluginSetting('wgm.ldap','priv_auth_context_search',$priv_auth_context_search);
			DevblocksPlatform::setPluginSetting('wgm.ldap','priv_auth_field_email',$priv_auth_field_email);
			DevblocksPlatform::setPluginSetting('wgm.ldap','priv_auth_field_firstname',$priv_auth_field_firstname);
			DevblocksPlatform::setPluginSetting('wgm.ldap','priv_auth_field_lastname',$priv_auth_field_lastname);
			DevblocksPlatform::setPluginSetting('wgm.ldap','priv_auth_field_password',$priv_auth_field_password);
			DevblocksPlatform::setPluginSetting('wgm.ldap','priv_auth_field_password_type',$priv_auth_field_password_type);
			
			/*
			 * Customer auth
			 */
			
			@$pub_auth_host = DevblocksPlatform::importGPC($_REQUEST['pub_auth_host'],'string','');
			@$pub_auth_port = DevblocksPlatform::importGPC($_REQUEST['pub_auth_port'],'integer',389);
			@$pub_auth_username = DevblocksPlatform::importGPC($_REQUEST['pub_auth_username'],'string','');
			@$pub_auth_password = DevblocksPlatform::importGPC($_REQUEST['pub_auth_password'],'string','');
			@$pub_auth_context_search = DevblocksPlatform::importGPC($_REQUEST['pub_auth_context_search'],'string','');
			@$pub_auth_field_email = DevblocksPlatform::importGPC($_REQUEST['pub_auth_field_email'],'string','');
			@$pub_auth_field_firstname = DevblocksPlatform::importGPC($_REQUEST['pub_auth_field_firstname'],'string','');
			@$pub_auth_field_lastname = DevblocksPlatform::importGPC($_REQUEST['pub_auth_field_lastname'],'string','');
			@$pub_auth_field_password = DevblocksPlatform::importGPC($_REQUEST['pub_auth_field_password'],'string','');
			@$pub_auth_field_password_type = DevblocksPlatform::importGPC($_REQUEST['pub_auth_field_password_type'],'string','');

			if(!empty($pub_auth_host) && !empty($pub_auth_username) && !empty($pub_auth_password)) {
				@$ldap = ldap_connect($pub_auth_host, $pub_auth_port);
				
				if(!$ldap)
					throw new Exception("Failed to connect to customer auth host.");
				
				ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
				ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
				
				@$login = ldap_bind($ldap, $pub_auth_username, $pub_auth_password);
				
				if(!$login)
					throw new Exception("Failed to authenticate on customer auth host.");
				
				$query = sprintf("(%s=*)", $pub_auth_field_email);
				@$results = ldap_search($ldap, $pub_auth_context_search, $query, array($pub_auth_field_email), 0, 1);
				
				if(!$results)
					throw new Exception("Failed to retrieve customer search results.");
				
				ldap_unbind($ldap);
			}
			
			DevblocksPlatform::setPluginSetting('wgm.ldap','pub_auth_host',$pub_auth_host);
			DevblocksPlatform::setPluginSetting('wgm.ldap','pub_auth_port',$pub_auth_port);
			DevblocksPlatform::setPluginSetting('wgm.ldap','pub_auth_username',$pub_auth_username);
			DevblocksPlatform::setPluginSetting('wgm.ldap','pub_auth_password',$pub_auth_password);
			DevblocksPlatform::setPluginSetting('wgm.ldap','pub_auth_context_search',$pub_auth_context_search);
			DevblocksPlatform::setPluginSetting('wgm.ldap','pub_auth_field_email',$pub_auth_field_email);
			DevblocksPlatform::setPluginSetting('wgm.ldap','pub_auth_field_firstname',$pub_auth_field_firstname);
			DevblocksPlatform::setPluginSetting('wgm.ldap','pub_auth_field_lastname',$pub_auth_field_lastname);
			DevblocksPlatform::setPluginSetting('wgm.ldap','pub_auth_field_password',$pub_auth_field_password);
			DevblocksPlatform::setPluginSetting('wgm.ldap','pub_auth_field_password_type',$pub_auth_field_password_type);
			
		    echo json_encode(array('status'=>true,'message'=>'Saved!'));
		    return;
			
		} catch (Exception $e) {
			echo json_encode(array('status'=>false,'error'=>$e->getMessage()));
			return;
		}
	}
	
};
endif;