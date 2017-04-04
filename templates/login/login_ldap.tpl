{if !empty($error)}
<div class="error-box">
	<h1>Error</h1>
	<p>{$error}</p>
</div>
{/if}

<form action="{devblocks_url}c=login&m=authenticate{/devblocks_url}" method="post" id="loginLDAP">

<fieldset>
	<legend>Sign on using LDAP</legend>

	<table cellpadding="0" cellspacing="2">
	<tr>
		<td align="right" valign="middle">{'common.email'|devblocks_translate|capitalize}: </td>
		<td>
			{if !empty($email)}
				<b>{$email}</b>
				<input type="hidden" name="email" value="{$email}">
				 &nbsp; 
				<a href="{devblocks_url}c=login&a=reset{/devblocks_url}" tabindex="-1">use a different email</a>
			{else}
				<input type="text" name="email" size="45" class="input_email">
			{/if}
		</td>
	</tr>
	<tr>
		<td align="right" valign="middle">{'common.password'|devblocks_translate|capitalize}: </td>
		<td nowrap="nowrap">
			<input type="password" name="password" size="16" autocomplete="off">
		</td>
	</tr>
	</table>
	<button type="submit"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> {'header.signon'|devblocks_translate|capitalize}</button>
</fieldset>
</form>

<script type="text/javascript">
	$(function() {
		$('#loginLDAP input:text,#loginLDAP input:password').first().focus().select();
	});
</script>