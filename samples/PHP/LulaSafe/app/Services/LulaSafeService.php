<?php

namespace App\Services;

use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;


class LulaSafeService
{
    protected string $baseUrl = 'https://api.staging-lula.is';
    protected string $version = "/v1";
    protected string $lulaSafeVersion = "/v0.1-beta1";

    protected DefaultApi $authApiInstance;
    protected DefaultApi $apiInstance;

    public function run(): void
    {
        $secrets_file = file_get_contents('../../../../appsecrets.json');
        $secrets_json = json_decode($secrets_file, false);

        $clientId = $secrets_json->ClientId;
        $clientSecret = $secrets_json->ClientSecret;

        // ================ Authentication ================

        // Login
        $flowId = $this->logIn();
        echo 'Flow Id: ' . $flowId . PHP_EOL;

        // Get token
        $sessionToken = $this->getSessionToken($flowId, $clientId, $clientSecret);
        echo 'Token: ' . $sessionToken . PHP_EOL . PHP_EOL;

        $host = $this->baseUrl . "/risk" . $this->lulaSafeVersion . '/';
        $config = Configuration::getDefaultConfiguration()->setHost($host);
        // Use for calls with a session id
        $this->apiInstance = new DefaultApi(
            // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
            // This is optional, `GuzzleHttp\Client` will be used as default.
            new Client(), $config
        );

        // Configure Bearer authorization
        $config = Configuration::getDefaultConfiguration()
            ->setHost($host)
            ->setAccessToken($sessionToken);

        // Use to create a session and get completed assessments at any time
        $this->authApiInstance = new DefaultApi(
            // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
            // This is optional, `GuzzleHttp\Client` will be used as default.
            new Client(), $config
        );

        // =============== Primary use case ===============

        /**
         * @var Session $session
         **/
        $session = $this->createSession();
        echo 'Session created:'. PHP_EOL;
        echo $session. PHP_EOL. PHP_EOL;

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
            echo $driverAssessmentRequest. PHP_EOL. PHP_EOL;

            /**
             * @var DriverAssessmentRequestStatuses $assessment
             **/
            $assessment = $this->requestDriverAssessment($session->getSessionId(), $driverAssessmentRequest);
            echo 'Assessment status:'. PHP_EOL;
            echo $assessment. PHP_EOL. PHP_EOL;
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

    protected function logIn()
    {
        $client = $this->getHttpClient();
        $response = $client->request('GET', $this->baseUrl . $this->version . '/login/initialize');
        $statuscode = $response->getStatusCode();
        echo 'Login response code:' . $statuscode . PHP_EOL;
        $content = $response->getBody()->getContents();
        $responseParam = json_decode($content);
        return $responseParam->id;
    }

    protected function getSessionToken($flowId, $clientId, $clientSecret)
    {
        $client = $this->getHttpClient();

        $requestOptions = [
            'json' => [
                'method' => 'password',
                'identifier' => $clientId,
                'password' => $clientSecret,
            ]
        ];

        if (method_exists($client, 'createRequest')) {
            $request = $client->createRequest("POST", $this->baseUrl . $this->version . "/login/submit?flow={$flowId}", $requestOptions);
            $response = $client->send($request);
        } else {
            $response = $client->request('POST', $this->baseUrl . $this->version . "/login/submit?flow={$flowId}", $requestOptions);
        }
        $statuscode = $response->getStatusCode();
        echo 'Get session token response code:' . $statuscode . PHP_EOL;
        $content = $response->getBody()->getContents();
        $responseParam = json_decode($content);
        return $responseParam->session_token;
    }

    /**
     * @throws ApiException
     */
    protected function createSession()
    {
        return $this->authApiInstance->createSession();
    }

    /**
     * @throws ApiException
     * @param  string $session_id Session identifier (required)
     * @param  \OpenAPI\Client\Model\DriverAssessmentRequest $driver_assessment_request driver_assessment_request (optional)
     */
    protected function requestDriverAssessment($sessionId, $driverAssessmentRequest): DriverAssessmentRequestStatuses
    {
        return $this->apiInstance->requestDriverAssessment($sessionId, $driverAssessmentRequest);
    }

    /**
     * @throws ApiException
     * @param  string $session_id Session identifier (required)
     */
    protected function getStripeIdentityVerificationCredentials($sessionId): StripeIdentityVerificationCredentials|ProblemDetails
    {
        return $this->apiInstance->getStripeIdentityVerificationCredentials($sessionId);
    }

    /**
     * @param  string $driver_assessment_id Driver AssessmentI identifier (required)
     * @return DriverAssessmentResults|ProblemDetails
     * @throws ApiException
     */
    protected function getDriverAssessmentById($driver_assessment_id): DriverAssessmentResults|ProblemDetails
    {
        return $this->authApiInstance->getDriverAssessmentById($driver_assessment_id);
    }

    protected function getHttpClient(): Client
    {
        if (!isset($this->httpClient)) {
            # Do this if you want to handle exceptions yourself
            #$this->httpClient = new Client(['http_errors' => false]);
            $this->httpClient = new Client();
        }
        return $this->httpClient;
    }
}
