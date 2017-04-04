<b>Connected account:</b><br>
<button type="button" class="cerb-chooser-trigger" data-field-name="login_params[{$extension->id}][connected_account_id]" data-context="{CerberusContexts::CONTEXT_CONNECTED_ACCOUNT}" data-single="true" data-query="service:ldap"><span class="glyphicons glyphicons-search"></span></button>

<ul class="bubbles chooser-container">
	{if $login_params.connected_account_id}
		{$account = DAO_ConnectedAccount::get($login_params.connected_account_id)}
		{if $account}
			<li><input type="hidden" name="login_params[{$extension->id}][connected_account_id]" value="{$account->id}"><a href="javascript:;" class="cerb-peek-trigger no-underline" data-context="{CerberusContexts::CONTEXT_CONNECTED_ACCOUNT}" data-context-id="{$account->id}">{$account->name}</a></li>
		{/if}
	{/if}
</ul>
<br>

<br>

<b>Search context:</b><br>
<input type="text" name="login_params[{$extension->id}][context_search]" value="{$login_params.context_search}" size="64"><br>
<i>example: OU=customers,DC=example,DC=com</i><br>

<br>

<b>Email field:</b><br>
<input type="text" name="login_params[{$extension->id}][field_email]" value="{$login_params.field_email}" size="64"><br>
<i>example: mail</i><br>

<br>

<b>First name (given name) field:</b> (optional)<br>
<input type="text" name="login_params[{$extension->id}][field_firstname]" value="{$login_params.field_firstname}" size="64"><br>
<i>example: givenName</i><br>

<br>

<b>Last name (surname) field:</b> (optional)<br>
<input type="text" name="login_params[{$extension->id}][field_lastname]" value="{$login_params.field_lastname}" size="64"><br>
<i>example: sn</i><br>

<br>

<b>Login prompt:</b> (optional)<br>
<input type="text" name="login_params[{$extension->id}][login_prompt]" value="{$login_params.login_prompt}" size="64" placeholder="Log in with LDAP:"><br>
