<?php

namespace App\Services;

// Import generated LulaSafe client code
require_once('./.api-lulasafe/vendor/autoload.php');

use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use OpenAPI\Client\Api\DefaultApi;
use OpenAPI\Client\ApiException;
use OpenAPI\Client\Configuration;
use OpenAPI\Client\Model\Address;
use OpenAPI\Client\Model\Assessee;
use OpenAPI\Client\Model\DriverAssessmentRequest;
use OpenAPI\Client\Model\DriverAssessmentRequestStatuses;
use OpenAPI\Client\Model\DriverAssessmentResults;
use OpenAPI\Client\Model\DrivingLicense;
use OpenAPI\Client\Model\ProblemDetails;
use OpenAPI\Client\Model\Session;
use OpenAPI\Client\Model\StripeIdentityVerificationCredentials;
use OpenAPI\Client\Model\ValidationProblemDetails;

class LulaSafeService
{
    protected string $baseUrl = 'https://api.staging-lula.is/';
    protected string $apiVersion = 'v0.1-beta1';
    protected DefaultApi $authApiInstance;
    protected DefaultApi $apiInstance;

    public function run(): void
    {
        // ================ Authentication ================
        // Login
        $flowId = $this->loggin();
        echo 'Flow Id: '. $flowId . PHP_EOL. PHP_EOL;

        // Get token
        $bearerToken = $this->getToken($flowId);
        echo 'Token: '. $bearerToken. PHP_EOL. PHP_EOL;

        $host = $this->baseUrl.'risk/'.$this->apiVersion.'/';
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
            ->setAccessToken($bearerToken);

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
                        'first_name' => 'DAVID',
                        'middle_name' => 'Stuard',
                        'last_name' => 'HOWARD',
                        'date_of_birth' => Carbon::create(1990, 02, 02),
                        'phone' => '+1 206-266-1000',
                        'email' => 'newtest@gmail.com',
                    ]
                ),
                'driving_license' => new DrivingLicense(
                    [
                        'id' => 'U1234591',
                        'expiry_date' => Carbon::create(2024, 01, 01),
                        'issuer_state' => 'CA',
                    ]
                ),
                'address' => new Address(
                    [
                        //'line1' => '',
                        //'line2' => '',
                        //'zip_code' => '',
                        'country' => 'United States',
                        //'state' => '',
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

        // ================ Unsuccessfull error codes handling ================
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

    protected function loggin()
    {
        $client = $this->getHttpClient();
        $response = $client->request('GET', $this->baseUrl . 'v1/login/initialize');
        $content = $response->getBody()->getContents();
        $responseParam = json_decode($content);
        return $responseParam->id;
    }

    protected function getToken($flowId)
    {
        $client = $this->getHttpClient();

        $requestOptions = [
            'json' => [
                'method' => 'password',
                'password_identifier' => '<Your Lula login>',
                'password' => '<Your Lula password>',
            ]
        ];

        if (method_exists($client, 'createRequest')) {
            $request = $client->createRequest("POST", $this->baseUrl . "v1/login/submit?flow={$flowId}", $requestOptions);
            $response = $client->send($request);
        } else {
            $response = $client->request('POST', $this->baseUrl . "v1/login/submit?flow={$flowId}", $requestOptions);
        }

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
            $this->httpClient = new Client();
        }

        return $this->httpClient;
    }
}
