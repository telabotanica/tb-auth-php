<?php
/**
 * Authentication and user management using Tela Botanica's SSO
 */
class AuthTB {

	/**
	 * Configuration format : an array containing the following indices
	 * - annuaireURL : root URL of the "annuaire" SSO service
	 * - admins : a list of user emails who will be granted administrator rights
	 * - ignoreSSLIssues : if true, will ignore self-signed SSL certificates issues
	 * - headerName : if set, the SSO token will be searched for in this header,
	 *		rather than in "Authorization"
	 */
	protected $config;

	/** SSO token data representing a user */
	protected $user;

	/** Groups the user belongs to (currently unused) */
	protected $groups = array();

	/** Permissions (roles names) the user has */
	protected $permissions = array();

	/** Header to search for the token; by default "Authorization" */
	protected $headerName;

	public function __construct($config) {
		$this->config = $config;
		// defaults and overriding
		$this->headerName = "Authorization";
		if (! empty($this->config['headerName'])) {
			$this->headerName = $this->config['headerName'];
		}
		// reading user infos from SSO token
		$this->user = $this->getUserFromToken();
		// copying permissions
		if (isset($this->user['permissions']) && is_array($this->user['permissions'])) {
			$this->permissions = $this->user['permissions'];
		}
		// copying groups
		if (isset($this->user['groupes']) && is_array($this->user['groupes'])) {
			$this->groups = $this->user['groupes'];
		}
	}

	/**
	 * Returns the current user's data
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * Returns the user's numeric ID
	 */
	public function getUserId() {
		return $this->user['id'];
	}

	/**
	 * Returns the user's email address
	 */
	public function getUserEmail() {
		return $this->user['sub'];
	}

	/**
	 * Returns the user's complete name / nickname
	 */
	public function getUserFullName() {
		return $this->user['intitule'];
	}

	/**
	 * Returns the list of groups the user belongs to
	 */
	public function getUserGroups() {
		return $this->groups;
	}

	/**
	 * Returns the list of permissions (roles names) the user has
	 */
	public function getUserPermissions() {
		return $this->permissions;
	}

	/**
	 * Returns true if :
	 *  - the user's email address is in the "admins" list (in config)
	 *  OR
	 *  - the user has one of the roles listed in the "adminRoles" list (in config)
	 */
	public function isAdmin() {
		$admins = array();
		$adminRoles = array();
		if (isset($this->config['admins']) && is_array($this->config['admins'])) {
			$admins = $this->config['admins'];
		}
		if (isset($this->config['adminRoles']) && is_array($this->config['adminRoles'])) {
			$adminRoles = $this->config['adminRoles'];
		}

		$isAdmin = in_array($this->user['sub'], $admins);

		$hasAdminRole = false;
		$commonRoles = array_intersect($this->permissions, $adminRoles);
		$hasAdminRole = ! empty($commonRoles);

		return ($isAdmin || $hasAdminRole);
	}

	/**
	 * Returns true if the client's IP address is in the "authorizedIPs" list (in config)
	 */
	public function hasAuthorizedIP() {
		$authorizedIPs = array();
		if (isset($this->config['authorizedIPs']) && is_array($this->config['authorizedIPs'])) {
			$authorizedIPs = $this->config['authorizedIPs'];
		}
		return in_array($_SERVER['REMOTE_ADDR'], $authorizedIPs);
	}

	/**
	 * Searches for a JWT SSO token in the $this->headerName HTTP header, validates
	 * this token's authenticity against the "annuaire" SSO service and if
	 * successful, decodes the user information and places them into $this->user
	 */
	protected function getUserFromToken() {
		// unknown user, by default
		$user = $this->getUnknownUser();
		// read token
		$token = $this->readTokenFromHeader();
		//echo "Token : $token\n";
		if ($token != null) {
			// validate token
			$valid = $this->verifyToken($token);
			if ($valid === true) {
				// decode user's email address from token
				$tokenData = $this->decodeToken($token);
				if ($tokenData != null && $tokenData["sub"] != "") {
					$user = $tokenData;
				}
			}
		}
		return $user;
	}

	/**
	 * Defines as current user an unknown pseudo-user
	 */
	protected function getUnknownUser() {
		$user = array(
			'sub' => null,
			'id' => null, // @TODO replace with a session ID ?
			'permissions' => array()
		);
		return $user;
	}

	/**
	 * Tries to find a non empty JWT token in the $this->headerName HTTP header
	 */
	protected function readTokenFromHeader() {
		$jwt = null;
		$headers = apache_request_headers();
		if (isset($headers[$this->headerName]) && ($headers[$this->headerName] != "")) {
			$jwt = $headers[$this->headerName];
		}
		return $jwt;
	}

	/**
	 * Verifies the authenticity of a token using the "annuaire" SSO service
	 */
	protected function verifyToken($token) {
		$verificationServiceURL = $this->config['annuaireURL'];
		$verificationServiceURL = trim($verificationServiceURL, '/') . "/verifytoken";
		$verificationServiceURL .= "?token=" . $token;

		$ch = curl_init();
		$timeout = 5;
		curl_setopt($ch, CURLOPT_URL, $verificationServiceURL);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

		// equivalent of "-k", ignores SSL self-signed certificate issues
		// (for local testing only)
		if (! empty($this->config['ignoreSSLIssues']) && $this->config['ignoreSSLIssues'] === true) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		}

		$data = curl_exec($ch);
		curl_close($ch);
		$info = $data;

		$info = json_decode($info, true);

		return ($info === true);
	}

	/**
	 * Decodes a formerly validated JWT token and returns the data it contains
	 * (payload / claims)
	 */
	protected function decodeToken($token) {
		$parts = explode('.', $token);
		$payload = $parts[1];
		$payload = $this->urlsafeB64Decode($payload);
		$payload = json_decode($payload, true);

		return $payload;
	}

	/**
	 * Method compatible with "urlsafe" base64 encoding used by JWT lib
	 */
	protected function urlsafeB64Decode($input) {
		$remainder = strlen($input) % 4;
		if ($remainder) {
			$padlen = 4 - $remainder;
			$input .= str_repeat('=', $padlen);
		}
		return base64_decode(strtr($input, '-_', '+/'));
	}
}

/**
 * Compatibility with nginx / some Apache versions
 * thanks http://php.net/manual/fr/function.getallheaders.php
 */
if (! function_exists('apache_request_headers')) {
	function apache_request_headers() {
		$headers = array();
		foreach ($_SERVER as $name => $value) {
			if (substr($name, 0, 5) == 'HTTP_') {
				$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
			}
		}
		return $headers;
	}
}
