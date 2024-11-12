<?php
if (realpath(__FILE__) == realpath($_SERVER['SCRIPT_FILENAME'])) {
    require (__DIR__.'/../error_accessdenied.php');
    exit();
}

require_once (__DIR__.'/../vendor/autoload.php');

use Kinde\KindeSDK\KindeClientSDK;
use Kinde\KindeSDK\Configuration;

class KindeAuthentication {

	private $kindeClient;
	private $kindeConfig;
	private $domain;

	public function __construct() {
		if (!isset($_SESSION) ){
			$this->init_session();
		}
		$this->init_client();
	}

	private function init_session() {
		session_start();
	}

	private function clearSession() {
		if (isset($_SESSION['kinde-oauth2'])) {
			unset($_SESSION['kinde-oauth2']);
		}
	}

	private function init_client() {
		// Load the configuration file
		$config = require(__DIR__ . '/../config/prod/default.inc.php');
		$kindeConfig = $config['kinde'];

		$hostname = rtrim('http://localhost:8888', '/');
		$this->domain = $kindeConfig['HOST'];

		// Initialize the configuration first
		$this->kindeConfig = new Configuration();
		$this->kindeConfig->setHost($this->domain);

		$this->kindeClient = new KindeClientSDK(
			$this->domain,
			$kindeConfig['REDIRECT_URL'],
			$kindeConfig['CLIENT_ID'],
			$kindeConfig['CLIENT_SECRET'],
			'authorization_code',
			$kindeConfig['LOGOUT_REDIRECT_URL'],
			'openid profile email offline',
			[],
			'https'
		);

		$this->kindeClient->storage->setCookiePath('/');
	}

	public function login() {

		if ($this->kindeClient->isAuthenticated) {

			$updatedUserDetails = $this->getUserDetails();

			if (!empty($updatedUserDetails['id'])) {
				//do not overwrite 'orgCode' key, as may have been changed during onboarding to new tenant org code using Kinde MgmtAPI, which will not be updated in Kinde AuthAPi until logoff/login
				$_SESSION['kinde-oauth2']['user'] = array_filter(array_merge(($_SESSION['kinde-oauth2']['user'] ?? array()), $updatedUserDetails));

			} elseif (empty($_SESSION['kinde-oauth2']['user']['id'])) {
				$this->clearSession();
				$this->kindeClient->login();
			}

			if (empty($_SESSION['kinde-oauth2']['user']['orgCode'])) {
				$_SESSION['kinde-oauth2']['user']['orgCode'] = $this->getUserOrgCode();
			}

		} else {
			$this->clearSession();
			$this->kindeClient->login();
		}
	}

	public function register() {
		$this->clearSession();
		$this->kindeClient->register();
	}

	public function logout() {
		$this->clearSession();
		$this->kindeClient->logout();
	}

	public function callback() {

		try {
			$token = $this->kindeClient->getToken();
			$this->kindeConfig->setAccessToken($token->access_token);

			$_SESSION['kinde-oauth2']['user'] = $this->getUserDetails();
			$_SESSION['kinde-oauth2']['user']['orgCode'] = $this->getUserOrgCode();
		} catch (Exception $e) {
			$this->logout();
		}
	}

	public function getUserId() {

		if (!$this->kindeClient->isAuthenticated) {
			return null;
		}

		$userDetails = $this->kindeClient->getUserDetails();
		return $userDetails['id'];
	}

	public function getUserDetails() {

		if (!$this->kindeClient->isAuthenticated) {
			return null;
		}

		return $this->kindeClient->getUserDetails();
	}

	public function getUserOrgCode() {

		$userOrgCode = null;

		try {
			if ($orgDetails = $this->kindeClient->getOrganization()) {
				$userOrgCode = $orgDetails['orgCode'];
			}

			if (empty($userOrgCode)) {
				if ($allUserOrgs = $this->kindeClient->getUserOrganizations()) {
					$userOrgCode = $allUserOrgs['orgCodes'][0] ?? null;
				}
			}
		} catch (Exception $e) {
			trigger_error('Unable to lookup Kinde organisation: '.$e->getMessage());
		}

		return $userOrgCode;
	}
}
?>