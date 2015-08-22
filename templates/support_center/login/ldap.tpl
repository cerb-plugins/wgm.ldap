<form action="{devblocks_url}c=login&a=authenticate{/devblocks_url}" method="post" id="loginLDAP">
<input type="hidden" name="_csrf_token" value="{$session->csrf_token}">

{if !empty($error)}
<div class="error">{$error}</div>
{/if}

<fieldset>
	<legend>Sign on using LDAP</legend>
	
	<b>Email:</b><br>
	<input type="text" name="email" size="45"><br>
	
	<b>Password:</b><br>
	<input type="password" name="password" size="45" autocomplete="off"><br>
	
	<br>
	<button type="submit">{'header.signon'|devblocks_translate|capitalize}</button>
</fieldset>
</form>

{*<a href="{devblocks_url}c=login&a=register{/devblocks_url}">Don't have an account? Create one for free with your OpenID.</a><br>*}
{*<a href="{devblocks_url}c=login&a=forgot{/devblocks_url}">Lost your OpenID? Click here to recover your account.</a><br>*}

{include file="devblocks:cerberusweb.support_center::support_center/login/switcher.tpl"}

<script type="text/javascript">
	$(function() {
		$('#loginLDAP input:text').first().focus().select();
	});
</script>
