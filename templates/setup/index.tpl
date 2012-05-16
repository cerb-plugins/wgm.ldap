<h2>{'ldap.common.ldap'|devblocks_translate}</h2>
{if !$extensions.ldap}
<b>The LDAP extension is not installed.</b>
{else}
<form action="javascript:;" method="post" id="frmSetupLdap" onsubmit="return false;">
	<input type="hidden" name="c" value="config">
	<input type="hidden" name="a" value="handleSectionAction">
	<input type="hidden" name="section" value="ldap">
	<input type="hidden" name="action" value="saveJson">
	
	<fieldset style="float:left;width:30%;">
		<legend>Directory</legend>

		<b>Host:*</b><br>
		<input type="text" name="ldap_host" value="{$params.ldap_host}" size="64"><br>
		<i>example: ldap.example.com</i><br>
		<br>
		
		<b>Port:*</b><br>
		<input type="text" name="ldap_port" value="{$params.ldap_port}" size="5"><br>
		<i>example: 389</i><br>
		<br>
		
		<b>LDAP User:*</b><br>
		<input type="text" name="ldap_username" value="{$params.ldap_username}" size="64"><br>
		<i>example: cn=admin,OU=users,DC=example,DC=com</i><br>
		<br>
		
		<b>LDAP Password:*</b><br>
		<input type="password" name="ldap_password" value="{$params.ldap_password}" size="64"><br>
		<br>
	</fieldset>
	
	<fieldset style="float:left;width:30%;">
		<legend>Worker authentication</legend>

		<b>Search context:*</b><br>
		<input type="text" name="priv_auth_context_search" value="{$params.priv_auth_context_search}" size="64"><br>
		<i>example: OU=staff,DC=example,DC=com</i><br>
		<br>
		
		<b>Email field:*</b><br>
		<input type="text" name="priv_auth_field_email" value="{$params.priv_auth_field_email}" size="64"><br>
		<i>example: mail</i><br>
		<br>
		
		<b>First name (given name) field:</b> (optional)<br>
		<input type="text" name="priv_auth_field_firstname" value="{$params.priv_auth_field_firstname}" size="64"><br>
		<i>example: givenName</i><br>
		<br>
		
		<b>Last name (surname) field:</b> (optional)<br>
		<input type="text" name="priv_auth_field_lastname" value="{$params.priv_auth_field_lastname}" size="64"><br>
		<i>example: sn</i><br>
		<br>
		
	</fieldset>
	
	<fieldset style="float:left;width:30%;">
		<legend>Customer authentication</legend>

		<b>Search context:*</b><br>
		<input type="text" name="pub_auth_context_search" value="{$params.pub_auth_context_search}" size="64"><br>
		<i>example: OU=customers,DC=example,DC=com</i><br>
		<br>
		
		<b>Email field:*</b><br>
		<input type="text" name="pub_auth_field_email" value="{$params.pub_auth_field_email}" size="64"><br>
		<i>example: mail</i><br>
		<br>
		
		<b>First name (given name) field:</b> (optional)<br>
		<input type="text" name="pub_auth_field_firstname" value="{$params.pub_auth_field_firstname}" size="64"><br>
		<i>example: givenName</i><br>
		<br>
		
		<b>Last name (surname) field:</b> (optional)<br>
		<input type="text" name="pub_auth_field_lastname" value="{$params.pub_auth_field_lastname}" size="64"><br>
		<i>example: sn</i><br>
		<br>
	</fieldset>
	
	<br clear="all">
	
	<div class="status"></div>
	<button type="button" class="submit"><span class="cerb-sprite2 sprite-tick-circle"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>	
</form>

<script type="text/javascript">
$('#frmSetupLdap BUTTON.submit')
	.click(function(e) {
		genericAjaxPost('frmSetupLdap','',null,function(json) {
			$o = $.parseJSON(json);
			if(false == $o || false == $o.status) {
				Devblocks.showError('#frmSetupLdap div.status',$o.error);
			} else {
				Devblocks.showSuccess('#frmSetupLdap div.status',$o.message);
			}
		});
	})
;
</script>
{/if}