<?php

namespace App\Services;

use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;


class LulaSafeService
{
    protected string $baseUrl = 'https://api.staging-lula.is';
    protected string $version = "/v1";
    protected string $lulaSafeBase = "/risk";
    protected string $lulaSafeVersion = "/v0.1-beta1";

    public function run(): void
    {
        $secrets_file = file_get_contents('../../../../appsecrets.json');
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

        try {
            echo 'Prepare driver assessment request:'. PHP_EOL;
            $driverAssessmentRequest = new DriverAssessmentRequest([
                'assessee' => new Assessee(
                    [
                        'first_name' => 'Antonio',
                        'middle_name' => '',
                        'last_name' => 'Bernette',
                        'date_of_birth' => Carbon::create(1982, 11, 17),
                        'phone' => '270-555-7152',
                        'email' => 'antonio@email.com',
                    ]
                ),
                'driving_license' => new DrivingLicense(
                    [
                        'id' => '111119615',
                        'expiry_date' => Carbon::create(2024, 10, 20),
                        'issuer_state' => 'KY',
                    ]
                ),
                'address' => new Address(
                    [
                        'line1' => '7104 Cadillac Boulevard',
                        //'line2' => '',
                        'city' => 'Arlington',
                        'state' => 'TX',
                        'country' => 'US',
                        'zip_code' => '76016',
                    ]
                ),
            ]);
            echo $driverAssessmentRequest . PHP_EOL . PHP_EOL;

            /**
             * @var DriverAssessmentRequestStatuses $assessment
             **/
            $assessment = $this->requestDriverAssessment($session->getSessionId(), $driverAssessmentRequest);
            echo 'Assessment status:' . PHP_EOL;
            echo $assessment . PHP_EOL . PHP_EOL;
        }

        // ================ Unsuccessful error codes handling ================

        catch (ApiException $e) {
            switch ($e->getCode())
            {
                // Bad request
                case 400:
                    /**
                     * @var ProblemDetails $problemDetails
                     */
                    echo 'Invalid assessment request:'. PHP_EOL;
                    // Use ProblemDetails as per https://www.rfc-editor.org/rfc/rfc7807
                    $problemDetails = $e->getResponseObject(); //'ProblemDetails';
                    echo $problemDetails. PHP_EOL. PHP_EOL;
                    break;

                // SessionNotFound, no body
                case 404: break;

                // SessionExpired, no body
                case 410: break;

                // Incorrect paramaters supplied
                case 422:
                    echo 'Invalid assessment data:'. PHP_EOL;
                    /**
                     * @var ValidationProblemDetails $validationProblemDetails
                     */
                    // Extended ProblemDetails with validation errors
                    $validationProblemDetails = $e->getResponseObject(); // 'ValidationProblem';
                    echo $validationProblemDetails. PHP_EOL. PHP_EOL;

                    // Get error list per each invalid field
                    $errorsPerField = $validationProblemDetails->getErrors();
                    break;
            }
        }

        // Getting credentials for document and selfie verification initiated from browser
        try {
            /**
             * @var StripeIdentityVerificationCredentials $stripeVerification
            */
            $stripeVerification = $this->getStripeIdentityVerificationCredentials($session->getSessionId());
            echo 'Verification credentials received:'. PHP_EOL;
            echo $stripeVerification. PHP_EOL. PHP_EOL;
        }
        catch (ApiException $e) {
            echo $e->getMessage();
        }

        // Do not call immediately as some data takes time to be received
        echo 'Waiting 10 seconds to get all responses'. PHP_EOL. PHP_EOL;
        sleep(10);

        // Getting assessment result later
        try {
            /**
             * @var DriverAssessmentResults $driverAssessmentResults
             */
            $driverAssessmentResults = $this->getDriverAssessmentById($session->getDriverAssessmentId());
            echo 'Assessment results received by id:'. PHP_EOL;
            echo $driverAssessmentResults. PHP_EOL. PHP_EOL;
        }
        catch (ApiException $e) {
            echo $e->getMessage();
        }
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

    protected function getHttpClient(): Client
    {
        if (!isset($this->httpClient)) {
            $this->httpClient = new Client();
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
