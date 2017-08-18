<?php
$db = DevblocksPlatform::services()->database();
$logger = DevblocksPlatform::services()->log();
$tables = $db->metaTables();
$encrypt = DevblocksPlatform::services()->encryption();

// ===========================================================================
// Move LDAP directory config to a connected account

$results = $db->GetArrayMaster("SELECT setting, value FROM devblocks_setting WHERE plugin_id = 'wgm.ldap'");
$ldap_settings = [];

foreach($results as $result) {
	$ldap_settings[$result['setting']] = $result['value'];
}

// Migrate host information to a new connected account
if(isset($ldap_settings['ldap_host']) && isset($ldap_settings['ldap_username'])) {
	$params = [
		'host' => @$ldap_settings['ldap_host'],
		'port' => @$ldap_settings['ldap_port'],
		'bind_dn' => @$ldap_settings['ldap_username'],
		'bind_password' => @$ldap_settings['ldap_password'],
	];
	
	$connected_account_id = DAO_ConnectedAccount::create([
		DAO_ConnectedAccount::CREATED_AT => time(),
		DAO_ConnectedAccount::EXTENSION_ID => 'wgm.ldap.service.provider',
		DAO_ConnectedAccount::NAME => 'LDAP: ' . @$ldap_settings['ldap_host'],
		DAO_ConnectedAccount::OWNER_CONTEXT => CerberusContexts::CONTEXT_APPLICATION,
		DAO_ConnectedAccount::OWNER_CONTEXT_ID => 0,
		DAO_ConnectedAccount::PARAMS_JSON => $encrypt->encrypt(json_encode($params)),
		DAO_ConnectedAccount::UPDATED_AT => time(),
	]);
	
	if($connected_account_id) {
		$ldap_params = [
			'connected_account_id' => $connected_account_id,
			'context_search' => $ldap_settings['priv_auth_context_search'],
			'field_email' => $ldap_settings['priv_auth_field_email'],
			'field_firstname' => $ldap_settings['priv_auth_field_firstname'],
			'field_lastname' => $ldap_settings['priv_auth_field_lastname'],
		];
		
		$db->ExecuteMaster(sprintf("REPLACE INTO devblocks_setting (plugin_id, setting, value) ".
			"VALUES (%s, %s, %s)",
			$db->qstr('wgm.ldap'),
			$db->qstr('config_json'),
			$db->qstr(json_encode($ldap_params))
		));
		
		// Migrate public auth to portal configs
		if(isset($ldap_settings['pub_auth_context_search']) && isset($ldap_settings['pub_auth_field_email'])) {
			$ldap_params = [
				'connected_account_id' => $connected_account_id,
				'context_search' => $ldap_settings['pub_auth_context_search'],
				'field_email' => $ldap_settings['pub_auth_field_email'],
				'field_firstname' => $ldap_settings['pub_auth_field_firstname'],
				'field_lastname' => $ldap_settings['pub_auth_field_lastname'],
			];
			
			$results = $db->GetArrayMaster("SELECT tool_code, property_value FROM community_tool_property WHERE property_key = 'common.login_extensions'");
			
			foreach($results as $result) {
				$vals = DevblocksPlatform::parseCsvString($result['property_value']);
				
				if(in_array('sc.login.auth.ldap', $vals)) {
					$db->ExecuteMaster(sprintf("REPLACE INTO community_tool_property (tool_code, property_key, property_value) ".
						"VALUES (%s, 'wgm.ldap.config_json', %s)",
						$db->qstr($result['tool_code']),
						$db->qstr(json_encode($ldap_params))
					));
				}
			}
		}
		
		$db->ExecuteMaster("DELETE FROM devblocks_setting WHERE plugin_id = 'wgm.ldap' AND setting IN ('ldap_host','ldap_password','ldap_port','ldap_username')");
		$db->ExecuteMaster("DELETE FROM devblocks_setting WHERE plugin_id = 'wgm.ldap' AND setting IN ('priv_auth_context_search','priv_auth_field_email','priv_auth_field_firstname','priv_auth_field_lastname')");
		$db->ExecuteMaster("DELETE FROM devblocks_setting WHERE plugin_id = 'wgm.ldap' AND setting IN ('pub_auth_context_search','pub_auth_field_email','pub_auth_field_firstname','pub_auth_field_lastname')");
	}
}

return TRUE;