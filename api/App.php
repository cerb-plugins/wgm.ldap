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
			'host' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'ldap_host', ''),
			'port' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'ldap_port', '389'),
			'username' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'ldap_username', ''),
			'password' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'ldap_password', ''),
			
			'context_search' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'priv_auth_context_search', ''),
			'field_email' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'priv_auth_field_email', ''),
			'field_firstname' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'priv_auth_field_firstname', ''),
			'field_lastname' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'priv_auth_field_lastname', ''),
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

		if(empty($count))
			return false;
		
		// Try to bind as the worker's DN
		
		$dn = $entries[0]['dn'];
		
		if(@ldap_bind($ldap, $dn, $password)) {
			$session = DevblocksPlatform::getSessionService();
			$visit = new CerberusVisit();
			$visit->setWorker($worker);
			$session->setVisit($visit);
			
			@ldap_unbind($ldap);
			return true;
		}
		
		@ldap_unbind($ldap);
		
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
				'host' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'ldap_host', ''),
				'port' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'ldap_port', '389'),
				'username' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'ldap_username', ''),
				'password' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'ldap_password', ''),
				
				'context_search' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'pub_auth_context_search', ''),
				'field_email' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'pub_auth_field_email', ''),
				'field_firstname' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'pub_auth_field_firstname', ''),
				'field_lastname' => DevblocksPlatform::getPluginSetting('wgm.ldap', 'pub_auth_field_lastname', ''),
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
			
			// Rebind as the customer DN
			
			$dn = $entries[0]['dn'];
			
			if(@ldap_bind($ldap, $dn, $password)) {
				
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
						
						@ldap_unbind($ldap);
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
			'ldap_host' => DevblocksPlatform::getPluginSetting('wgm.ldap','ldap_host',''),
			'ldap_port' => DevblocksPlatform::getPluginSetting('wgm.ldap','ldap_port',389),
			'ldap_username' => DevblocksPlatform::getPluginSetting('wgm.ldap','ldap_username',''),
			'ldap_password' => DevblocksPlatform::getPluginSetting('wgm.ldap','ldap_password',''),
			
			'priv_auth_context_search' => DevblocksPlatform::getPluginSetting('wgm.ldap','priv_auth_context_search',''),
			'priv_auth_field_email' => DevblocksPlatform::getPluginSetting('wgm.ldap','priv_auth_field_email',''),
			'priv_auth_field_firstname' => DevblocksPlatform::getPluginSetting('wgm.ldap','priv_auth_field_firstname',''),
			'priv_auth_field_lastname' => DevblocksPlatform::getPluginSetting('wgm.ldap','priv_auth_field_lastname',''),
			
			'pub_auth_context_search' => DevblocksPlatform::getPluginSetting('wgm.ldap','pub_auth_context_search',''),
			'pub_auth_field_email' => DevblocksPlatform::getPluginSetting('wgm.ldap','pub_auth_field_email',''),
			'pub_auth_field_firstname' => DevblocksPlatform::getPluginSetting('wgm.ldap','pub_auth_field_firstname',''),
			'pub_auth_field_lastname' => DevblocksPlatform::getPluginSetting('wgm.ldap','pub_auth_field_lastname',''),
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
			 * LDAP Auth
			 */
			
			@$ldap_host = DevblocksPlatform::importGPC($_REQUEST['ldap_host'],'string','');
			@$ldap_port = DevblocksPlatform::importGPC($_REQUEST['ldap_port'],'integer',389);
			@$ldap_username = DevblocksPlatform::importGPC($_REQUEST['ldap_username'],'string','');
			@$ldap_password = DevblocksPlatform::importGPC($_REQUEST['ldap_password'],'string','');
			
			if(empty($ldap_host) || empty($ldap_port) || empty($ldap_username) || empty($ldap_password))
				throw new Exception("The LDAP connection details are required.");
			
			@$ldap = ldap_connect($ldap_host, $ldap_port);
			
			if(!$ldap)
				throw new Exception("Failed to connect to worker auth host.");
			
			ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
			ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
			
			@$login = ldap_bind($ldap, $ldap_username, $ldap_password);
			
			if(!$login)
				throw new Exception("Failed to authenticate on worker auth host.");
			
			DevblocksPlatform::setPluginSetting('wgm.ldap','ldap_host',$ldap_host);
			DevblocksPlatform::setPluginSetting('wgm.ldap','ldap_port',$ldap_port);
			DevblocksPlatform::setPluginSetting('wgm.ldap','ldap_username',$ldap_username);
			DevblocksPlatform::setPluginSetting('wgm.ldap','ldap_password',$ldap_password);
			
			/*
			 * Worker auth
			 */
			
			@$priv_auth_context_search = DevblocksPlatform::importGPC($_REQUEST['priv_auth_context_search'],'string','');
			@$priv_auth_field_email = DevblocksPlatform::importGPC($_REQUEST['priv_auth_field_email'],'string','');
			@$priv_auth_field_firstname = DevblocksPlatform::importGPC($_REQUEST['priv_auth_field_firstname'],'string','');
			@$priv_auth_field_lastname = DevblocksPlatform::importGPC($_REQUEST['priv_auth_field_lastname'],'string','');
			
			$query = sprintf("(%s=*)", $priv_auth_field_email);
			@$results = ldap_search($ldap, $priv_auth_context_search, $query, array($priv_auth_field_email), 0, 1);
			
			if(!$results)
				throw new Exception("Failed to retrieve worker search results.");
				
			DevblocksPlatform::setPluginSetting('wgm.ldap','priv_auth_context_search',$priv_auth_context_search);
			DevblocksPlatform::setPluginSetting('wgm.ldap','priv_auth_field_email',$priv_auth_field_email);
			DevblocksPlatform::setPluginSetting('wgm.ldap','priv_auth_field_firstname',$priv_auth_field_firstname);
			DevblocksPlatform::setPluginSetting('wgm.ldap','priv_auth_field_lastname',$priv_auth_field_lastname);
			
			/*
			 * Customer auth
			 */
			
			@$pub_auth_context_search = DevblocksPlatform::importGPC($_REQUEST['pub_auth_context_search'],'string','');
			@$pub_auth_field_email = DevblocksPlatform::importGPC($_REQUEST['pub_auth_field_email'],'string','');
			@$pub_auth_field_firstname = DevblocksPlatform::importGPC($_REQUEST['pub_auth_field_firstname'],'string','');
			@$pub_auth_field_lastname = DevblocksPlatform::importGPC($_REQUEST['pub_auth_field_lastname'],'string','');

			$query = sprintf("(%s=*)", $pub_auth_field_email);
			@$results = ldap_search($ldap, $pub_auth_context_search, $query, array($pub_auth_field_email), 0, 1);
			
			if(!$results)
				throw new Exception("Failed to retrieve customer search results.");
				
			DevblocksPlatform::setPluginSetting('wgm.ldap','pub_auth_context_search',$pub_auth_context_search);
			DevblocksPlatform::setPluginSetting('wgm.ldap','pub_auth_field_email',$pub_auth_field_email);
			DevblocksPlatform::setPluginSetting('wgm.ldap','pub_auth_field_firstname',$pub_auth_field_firstname);
			DevblocksPlatform::setPluginSetting('wgm.ldap','pub_auth_field_lastname',$pub_auth_field_lastname);

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