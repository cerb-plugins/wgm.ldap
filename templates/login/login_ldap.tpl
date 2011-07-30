{if !empty($error)}
<div class="ui-widget">
	<div class="ui-state-error ui-corner-all" style="padding: 0 .7em; margin: 0.2em; "> 
		<p>
		<span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span> 
		<strong>{$error}</strong><br>
		</p>
	</div>
</div>
{/if}

<form action="{devblocks_url}c=login&m=authenticate{/devblocks_url}" method="post" id="loginLDAP">
<input type="hidden" name="original_path" value="{$original_path}">

<fieldset>
	<legend>Sign on using LDAP</legend>

	<table cellpadding="0" cellspacing="2">
	<tr>
		<td align="right" valign="middle">{'common.email'|devblocks_translate|capitalize}:</td>
		<td><input type="text" name="email" size="45" class="input_email"></td>
	</tr>
	<tr>
		<td align="right" valign="middle">{'common.password'|devblocks_translate|capitalize}:</td>
		<td nowrap="nowrap">
			<input type="password" name="password" size="16" autocomplete="off">
		</td>
	</tr>
	</table>
	<button type="submit">{$translate->_('header.signon')|capitalize}</button>
</fieldset>
</form>

{include file="devblocks:cerberusweb.core::login/switcher.tpl"}

<script type="text/javascript">
	$(function() {
		$('#loginLDAP input:text').first().focus().select();
	});
</script>