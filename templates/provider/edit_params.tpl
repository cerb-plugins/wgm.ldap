<fieldset class="peek black">
	<b>Host:</b><br>
	<input type="text" name="params[host]" value="{$params.host}" size="50" spellcheck="false" placeholder="ldap.example.com"><br>
	<br>
	
	<b>Port:</b><br>
	<input type="text" name="params[port]" value="{$params.port}" size="6" spellcheck="false" placeholder="389"><br>
	<br>
	
	<b>Bind DN:</b><br>
	<input type="text" name="params[bind_dn]" value="{$params.bind_dn}" size="45" spellcheck="false" placeholder="cn=read-only-admin,dc=example,dc=com"><br>
	<br>
	
	<b>Bind Password:</b><br>
	<input type="password" name="params[bind_password]" value="{$params.bind_password}" size="45" spellcheck="false"><br>
	<br>
</fieldset>