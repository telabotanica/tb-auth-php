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
	)
);
$auth = new AuthTB($config);
$userData = $auth->getUser();
```