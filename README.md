# tb-auth-php
A library that helps integrating your PHP webservices with Tela Botanica's SSO

## install
```
composer install telabotanica/tb-auth-php
```

## usage
```php
$config = array(
	"annuaireURL" => "https://www.tela-botanica.org/uri-of-sso-service",
	"admins" => array(
		"john@example.org",
		"mary@othersite.org"
	),
	"adminRoles" => array(
		"tb_my-application_admin"
	)
);
$auth = new AuthTB($config);
$userData = $auth->getUser();
```

## config parameters
### mandatory
- __annuaireURL__ : URL of Tela Botanica SSO service

### optional
- __ignoreSSLIssues__ : if `true`, curl will be lazy on SSL host verification, and prevent errors with old versions of libssl
- __headerName__ : expected header to read the token from (defaults to "Authorization")
- __admins__ : a list of email addresses of people who will be considered as "admins", ie `isAdmin()` will return `true`
- __adminRoles__ : a list of roles whose members will be considered as "admins", ie `isAdmin()` will return `true`
- __authorizedIPs__ : a list of IP addresses for the which `hasAuthorizedIP()` will return `true`