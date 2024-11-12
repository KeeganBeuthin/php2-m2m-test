<?php
if (realpath(__FILE__) == realpath($_SERVER['SCRIPT_FILENAME'])) {
    require (__DIR__.'/../error_accessdenied.php');
    exit();
}

require_once (__DIR__.'/../vendor/autoload.php');

use Kinde\KindeSDK\KindeClientSDK;
use Kinde\KindeSDK\Configuration;
use Kinde\KindeSDK\Model\UpdateUserRequest;
use Kinde\KindeSDK\Api\UsersApi;

// For manual api requests for where helper functions are missing in Kinde SDK
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;

class kindeApi {

	private $domain;
    private $kindeClient;
	private $kindeConfig;
	private $kindeApi;

	public function __construct() {
		$this->init_client();
	}

	private function init_client() {
		// Load the configuration file
		$config = require(__DIR__ . '/../config/prod/default.inc.php');
		$kindeConfig = $config['kinde'];

		// Debug: Log the session state before M2M initialization
		error_log('Session before M2M init: ' . json_encode($_SESSION));

		$hostname = rtrim('http://localhost:8888', '/');
		$this->domain = $kindeConfig['HOST'];

		$this->kindeClient = new KindeClientSDK(
			$this->domain,
			$kindeConfig['REDIRECT_URL'],
			$kindeConfig['M2M_CLIENT_ID'],
			$kindeConfig['M2M_CLIENT_SECRET'],
			'client_credentials',
			$kindeConfig['LOGOUT_REDIRECT_URL'],
			'',
			['audience' => $this->domain . '/api'],
			'https'
		);

		// Debug: Log the session state after M2M initialization
		error_log('Session after M2M init: ' . json_encode($_SESSION));

		$this->kindeConfig = new Configuration();
		$this->kindeConfig->setHost($this->domain);

		$token = $this->kindeClient->login();
        $this->kindeConfig->setAccessToken($token->access_token);
        $this->kindeApi = new UsersApi(
            new Client(),
            $this->kindeConfig
        );
	}

	// Manual api request (no helper functions yet)
    public function createOrganization($name) {

		$orgCode = null;

		try {
			$client = new Client();
			$body = json_encode(array(
				'name' => $name
			));

			$request = new Request(
				'POST',
				$this->domain . '/api/v1/organization',
				[
					'Accept' => 'application/json',
					'Content-Type' => 'application/json',
					'Authorization' => 'Bearer ' . $this->kindeConfig->getAccessToken()
				],
				$body
			);

			$response = $client->send($request);
			$responseBody = $response->getBody()->getContents();
			$data = json_decode($responseBody, true);

			$orgCode = $data['organization']['code'] ?? null;

		} catch (ClientException $e) {
			trigger_error('Unable to create Kinde organisation "'.$name.'": ' . $e->getCode() . '<br>' . $e->getMessage());
			return false;
		}

		return $orgCode;
	}

	public function addOrgUser($userId, $orgCode) {

		try {
			$client = new Client();

			$body = json_encode([
				'users' => [
					[
						'id' => $userId
					]
				]
			]);

			$request = new Request(
				'POST',
				$this->domain . '/api/v1/organizations/' . $orgCode . '/users',
				[
					'Accept' => 'application/json',
					'Content-Type' => 'application/json',
					'Authorization' => 'Bearer ' . $this->kindeConfig->getAccessToken()
				],
				$body
			);

			$response = $client->send($request);
			$responseBody = $response->getBody()->getContents();

		} catch (ClientException $e) {
			trigger_error('Unable to add user with ID "'.$userId.'" to Kinde organisation "' . $orgCode . '": ' . $e->getCode() . '<br>' . $e->getMessage());
			return false;
		}

		return $responseBody;
	}

	public function renameOrg($orgCode, $name) {

		try {
			$client = new Client();
			$body = json_encode(array(
				'name' => substr($name, 0, 64)
			));

			$request = new Request(
				'PATCH',
				$this->domain . '/api/v1/organization/'.$orgCode,
				[
					'Accept' => 'application/json',
					'Content-Type' => 'application/json',
					'Authorization' => 'Bearer ' . $this->kindeConfig->getAccessToken()
				],
				$body
			);

			$response = $client->send($request);
			$responseBody = $response->getBody()->getContents();

		} catch (ClientException $e) {
			trigger_error('Unable to update organisation "' . $orgCode . '" with new name "' . $name . '": ' . $e->getCode() . '<br>' . $e->getMessage());
			return false;
		}

		return $responseBody;
	}

	public function removeOrgUser($userId, $orgCode) {

		try {
			$client = new Client();

			$request = new Request(
				'DELETE',
				$this->domain . '/api/v1/organizations/' . $orgCode . '/users/'.$userId,
				[
					'Accept' => 'application/json',
					'Content-Type' => 'application/json',
					'Authorization' => 'Bearer ' . $this->kindeConfig->getAccessToken()
				]
			);

			$response = $client->send($request);
			$responseBody = $response->getBody()->getContents();

		} catch (ClientException $e) {
			trigger_error('Unable to remove user with ID "'.$userId.'" from Kinde organisation "' . $orgCode . '": ' . $e->getCode() . '<br>' . $e->getMessage());
			return false;
		}

		return $responseBody;
	}

	public function createUser($givenName, $surname, $email) {

		$kindeUserId = null;

		try {
			$client = new Client();

			$body = json_encode([
				'profile' => [
					'given_name' => $givenName,
					'family_name' => $surname
				],
				'organization_code' => $_SESSION['kinde-oauth2']['user']['orgCode'] ?? null,
				'identities' => [
					[
						'type' => 'email',
						'details' => [
							'email' => $email
						]
					]
				]
			]);

			$request = new Request(
				'POST',
				$this->domain . '/api/v1/user',
				[
					'Accept' => 'application/json',
					'Content-Type' => 'application/json',
					'Authorization' => 'Bearer ' . $this->kindeConfig->getAccessToken()
				],
				$body
			);

			$response = $client->send($request);
			$responseBody = $response->getBody()->getContents();
			$data = json_decode($responseBody, true);

			$kindeUserId = $data['id'] ?? null;

		} catch (ClientException $e) {
			trigger_error('Unable to create user with email address "' . $email . '": ' . $e->getCode() . '<br>' . $e->getMessage());
			return false;
		}

		return $kindeUserId;
	}

	public function updateUser($userId, $givenName, $surname) {

		$update_user_request = new UpdateUserRequest([
			'given_name' => $givenName,
			'family_name' => $surname
		]);

		$result = $this->kindeApi->updateUser($update_user_request, $userId);
		return $result;
	}

	public function deleteUser($kindeUserId) {
		$this->kindeApi->deleteUser($kindeUserId);
	}

	public function getUserIdByEmailAddress($emailAddress) {

		$kindeUser = null;

		try {
			$client = new Client();

			$uri = new Uri($this->domain . '/api/v1/users');
			$uri = $uri->withQuery(http_build_query([
				'email' => $emailAddress
			]));

			$request = new Request(
				'GET',
				$uri,
				[
					'Accept' => 'application/json',
					'Content-Type' => 'application/json',
					'Authorization' => 'Bearer ' . $this->kindeConfig->getAccessToken()
				]
			);

			$response = $client->send($request);
			$responseBody = $response->getBody()->getContents();
			$data = json_decode($responseBody, true);

			$kindeUser = $data['users'][0] ?? null;

		} catch (ClientException $e) {
			trigger_error('Unable to validate email address '.$emailAddress.' with Kinde: ' . $e->getCode() . '<br>' . $e->getMessage());
			return false;
		}

		return $kindeUser;
	}

	public function getUsersByEmailAddresses(array $emailAddresses = []) {

		$kindeUsers = [];

		try {
			$client = new Client();

			// Chunk the email addresses array into smaller arrays if required (Kinde API will throw 403 errors if query length is too long)
			$chunks = [];
			$chunkSizeLimit = 1500;
			$chunk = [];
			$currentLength = 0;

			for ($i = 0, $count = count($emailAddresses); $i < $count; $i++) {

				$email = $emailAddresses[$i];
				$emailLength = strlen($email) + 1; // Add 1 for comma

				if ($currentLength + $emailLength > $chunkSizeLimit && !empty($chunk)) {
					$chunks[] = $chunk;
					$chunk = [];
					$currentLength = 0;
				}

				$chunk[] = $email;
				$currentLength += $emailLength;
			}

			if (!empty($chunk)) {
				$chunks[] = $chunk;
			}

			// Make API requests for each chunk of email addresses
			foreach ($chunks as $chunk) {

				$uri = new Uri($this->domain . '/api/v1/users');
				$uri = $uri->withQuery(http_build_query([
					'email' => implode(',', $chunk)
				]));

				$request = new Request(
					'GET',
					$uri,
					[
						'Accept' => 'application/json',
						'Content-Type' => 'application/json',
						'Authorization' => 'Bearer ' . $this->kindeConfig->getAccessToken()
					]
				);

				$response = $client->send($request);
				$responseBody = $response->getBody()->getContents();
				$data = json_decode($responseBody, true);

				$kindeUsers = array_merge($kindeUsers, $data['users'] ?? []);
			}

		} catch (ClientException $e) {
			trigger_error('Unable to validate email addresses with Kinde: ' . $e->getCode() . '<br>' . $e->getMessage());
			return false;
		}

		return $kindeUsers;
	}
}
?>