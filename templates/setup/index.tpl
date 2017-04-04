<h2>{'ldap.common.ldap'|devblocks_translate}</h2>
{if !$extensions.ldap}
<b>The LDAP extension is not installed.</b>
{else}
<form action="javascript:;" method="post" id="frmSetupLdap" onsubmit="return false;">
	<input type="hidden" name="c" value="config">
	<input type="hidden" name="a" value="handleSectionAction">
	<input type="hidden" name="section" value="ldap">
	<input type="hidden" name="action" value="saveJson">
	<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">
	
	<fieldset style="float:left;width:30%;">
		<legend>Worker authentication</legend>

		<b>Connected account:</b><br>
		<button type="button" class="cerb-chooser-trigger" data-field-name="params[connected_account_id]" data-context="{CerberusContexts::CONTEXT_CONNECTED_ACCOUNT}" data-single="true" data-query="service:ldap"><span class="glyphicons glyphicons-search"></span></button>
		
		<ul class="bubbles chooser-container">
			{if $params.connected_account_id}
				{$account = DAO_ConnectedAccount::get($params.connected_account_id)}
				{if $account}
					<li><input type="hidden" name="params[connected_account_id]" value="{$account->id}"><a href="javascript:;" class="cerb-peek-trigger no-underline" data-context="{CerberusContexts::CONTEXT_CONNECTED_ACCOUNT}" data-context-id="{$account->id}">{$account->name}</a></li>
				{/if}
			{/if}
		</ul>
		<br>
		<br>
		
		<b>Search context:</b><br>
		<input type="text" name="params[context_search]" value="{$params.context_search}" size="64"><br>
		<i>example: OU=staff,DC=example,DC=com</i><br>
		<br>
		
		<b>Email field:</b><br>
		<input type="text" name="params[field_email]" value="{$params.field_email}" size="64"><br>
		<i>example: mail</i><br>
		<br>
		
		<b>First name (given name) field:</b> (optional)<br>
		<input type="text" name="params[field_firstname]" value="{$params.field_firstname}" size="64"><br>
		<i>example: givenName</i><br>
		<br>
		
		<b>Last name (surname) field:</b> (optional)<br>
		<input type="text" name="params[field_lastname]" value="{$params.field_lastname}" size="64"><br>
		<i>example: sn</i><br>
		<br>
		
	</fieldset>
	
	<br clear="all">
	
	<div class="status"></div>
	<button type="button" class="submit"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>	
</form>

<script type="text/javascript">
$(function() {
	var $frm = $('#frmSetupLdap');
	
	$frm.find('BUTTON.submit')
		.click(function(e) {
			genericAjaxPost($frm,'',null,function(json) {
				$o = $.parseJSON(json);
				if(false == $o || false == $o.status) {
					Devblocks.showError('#frmSetupLdap div.status',$o.error);
				} else {
					Devblocks.showSuccess('#frmSetupLdap div.status',$o.message);
				}
			});
		})
	;
	
	$frm.find('.cerb-peek-trigger')
		.cerbPeekTrigger()
	;

	$frm.find('.cerb-chooser-trigger')
		.cerbChooserTrigger()
	;
});
</script>
{/if}