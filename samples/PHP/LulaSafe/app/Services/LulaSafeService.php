<?php

namespace App\Services;

use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client as GuzzleHttpClient;
use GraphQL\Client as GraphQLClient;
use GraphQL\Exception\QueryError;


class LulaSafeService
{
    protected string $baseUrl = 'https://api.staging-lula.is';
    protected string $version = "/v1";
    protected string $lulaSafeBase = "/risk";
    protected string $lulaSafeVersion = "/v0.1-beta1";

    public function run(): void
    {
        $secrets_file = file_get_contents('../../../appsecrets.json');
        $secrets_json = json_decode($secrets_file, false);

        $clientId = $secrets_json->ClientId;
        $clientSecret = $secrets_json->ClientSecret;

        // ================ Authentication ================

        // Login
        $flowId = $this->logIn();

        // Get token
        $sessionToken = $this->getSessionToken($flowId, $clientId, $clientSecret);

        // Start a session
        $session = $this->createSession($sessionToken);
        $sessionId = $session->sessionId;

        // =============== Primary usage ===============

        $client = new GraphQLClient(
            $this->baseUrl . $this->lulaSafeBase . "/graphql",
            ['session-id' => $sessionId]
        );

        $variables = [
            'assessee' => [
                'firstName' => 'Antonio',
                'middleName' => '',
                'lastName' => 'Bernette',
                'dateOfBirth' => (Carbon::create(1982, 11, 17))->format('Y-m-d'),
                'phone' => '270-555-7152',
                'email' => 'antonio@email.com',
            ],
            'address' => [
                'line1' => '7104 Cadillac Boulevard',
                'line2' => '',
                'city' => 'Arlington',
                'state' => 'TX',
                'country' => 'US',
                'zipCode' => '76016',
            ]
        ];

        $gql = '
            mutation CheckInsuranceAndRequestVehicles ($address: InputAddress!, $assessee: InputAssessee!) {
                assess {
                    id
                    checkInsurance (assessee: $assessee, address: $address) {
                        policies {
                            started
                        }
                    }
                    requestVehicles (assessee: $assessee, address: $address) {
                        started
                    }
                }
            }
        ';

        // Run query to get results
        try {
            $results = $client->runRawQuery($gql, false, $variables);
        }
        catch (QueryError $exception) {
            // Catch query error and display error details
            $errorDetails = $exception->getErrorDetails();

            if (isset($errorDetails['extensions'])) {
                $code = $errorDetails['extensions']['statusCode'];
                switch ($code)
                {
                    case 400:
                        echo 'Return code 400: Invalid assessment request.' . PHP_EOL;
                        break;
                    case 404:
                        echo 'Return code 404: SessionNotFound.' . PHP_EOL;
                        break;
                    case 410:
                        echo 'Return code 410: SessionExpired.' . PHP_EOL;
                        break;
                    case 422:
                        echo 'Return code 422: Incorrect parameters supplied.' . PHP_EOL;
                        break;
                }
            }

            print_r($errorDetails);

            exit;
        }

        // Display original response from endpoint
        var_dump($results->getResponseObject());

        // Reformat the results to an array and get the results of part of the array
        $results->reformatResults(true);
        print_r($results->getData());
    }


    // =============== Session Management Functions ===============

    protected function logIn()
    {
        $client = $this->getHttpClient();
        $response = $client->request('GET', $this->baseUrl . $this->version . '/login/initialize');
        $content = $response->getBody()->getContents();
        $responseJson = json_decode($content);
        $id = $responseJson->id;
        echo 'Flow Id: ' . $id . PHP_EOL . PHP_EOL;
        return $id;
    }

    protected function getSessionToken($flowId, $clientId, $clientSecret)
    {
        $url = $this->baseUrl . $this->version . "/login/submit?flow={$flowId}";
        $requestOptions = [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'method' => 'password',
                'identifier' => $clientId,
                'password' => $clientSecret,
            ]
        ];

        $response = $this->callHttpClient($url, $requestOptions);

        $content = $response->getBody()->getContents();
        $responseJson = json_decode($content);
        $token = $responseJson->session_token;
        echo 'Token: ' . $token . PHP_EOL . PHP_EOL;
        return $token;
    }

    protected function createSession($sessionToken)
    {
        $url = $this->baseUrl . $this->lulaSafeBase . $this->lulaSafeVersion . "/sessions";
        $requestOptions = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $sessionToken,
            ],
            'allow_redirects' => true
        ];

        $response = $this->callHttpClient($url, $requestOptions);

        $content = $response->getBody()->getContents();
        echo 'Session creation returned:' . PHP_EOL . $content . PHP_EOL . PHP_EOL;
        $responseJson = json_decode($content);
        return $responseJson;
    }

    protected function handleJsonRequestWithSession($sessionId, $url, $requestJson)
    {
        $requestOptions = [
            'headers' => [
                'Content-Type' => 'application/json',
                'session-id' => $sessionId,
            ],
            'json' => $requestJson
        ];
        echo 'Sending POST to URL: ' . $url . PHP_EOL;

        $response = $this->callHttpClient($url, $requestOptions);
        $statuscode = $response->getStatusCode();
        echo 'Response code: ' . $statuscode . PHP_EOL;

        $content = $response->getBody()->getContents();
        $responseJson = json_decode($content);
        return $responseJson;
    }


    // =============== HTTP Client Utility Functions ===============

    protected function getHttpClient(): GuzzleHttpClient
    {
        if (!isset($this->httpClient)) {
            $this->httpClient = new GuzzleHttpClient();
        }
        return $this->httpClient;
    }

    protected function callHttpClient($url, $requestOptions): \GuzzleHttp\Psr7\Response
    {
        $client = $this->getHttpClient();

        echo 'Sending POST to URL: ' . $url . PHP_EOL;

        if (method_exists($client, 'createRequest')) {
            $request = $client->createRequest("POST", $url, $requestOptions);
            $response = $client->send($request);
        } else {
            $response = $client->request('POST', $url, $requestOptions);
        }
        return $response;
    }
}
