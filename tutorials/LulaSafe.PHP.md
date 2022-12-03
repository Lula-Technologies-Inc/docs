#!markdown

# LulaSafe API
This tutorial will guide you step by step how to use the API having the client code generated from OpenAPI specification

## Client code generation

Install NPM package

```
npm install @openapitools/openapi-generator-cli
```

and generate the client
```
npx openapi-generator-cli generate -g php -i "../../../openapi/lulasafe.yaml" -o ".api-lulasafe"
```

## Package restore

```
cd .api-lulasafe
composer update
```

> **Note**
>
> In `.api-lulasafe` you will see a ReadMe.md with API documentation. Including all types

## Import generated client files


``` PHP
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

```

## Authentication
> **Warning**
>
> Until we add support for OpenID Connect client credentials flow, we need to perform some custom token retrieving actions

### 1. Read your credentials
> **Important**
>
> Create [`appsettings.json`](../appsettings.json) in the repo root and set your credentials into it
> 
> ``` JSON
> {
>     "ClientId": "< Your Lula login >",
>     "ClientSecret": "< Your Lula password >"
> }
> ```

``` PHP
$lulaSafeConfig = json_decode(file_get_contents('../appsettings.json'), true);
```

### 2. Initiate session

``` PHP
protected string $baseUrl = 'https://api.staging-lula.is/';
protected string $apiVersion = 'v0.1-beta1';

...

$client = $this->getHttpClient();
$response = $client->request('GET', $this->baseUrl . 'v1/login/initialize');
$content = $response->getBody()->getContents();
$responseParam = json_decode($content);
$flowId = $responseParam->id;
```

### 3. Get session token used as bearer
``` PHP
$client = $this->getHttpClient();

$authRequestOptions = [
    'json' => [
        'method' => 'password',
        'password_identifier' => $lulaSafeConfig['ClientId'],
        'password' => $lulaSafeConfig['ClientSecret'],
    ]
];

if (method_exists($client, 'createRequest')) {
    $request = $client->createRequest("POST", $this->baseUrl . "v1/login/submit?flow={$flowId}", $authRequestOptions);
    $response = $client->send($request);
} else {
    $response = $client->request('POST', $this->baseUrl . "v1/login/submit?flow={$flowId}", $authRequestOptions);
}

$content = $response->getBody()->getContents();
$responseParam = json_decode($content);
$bearerToken = $responseParam->session_token; // Use as Bearer
```

## Client usage

### Prepare client instances

``` PHP
protected DefaultApi $authApiInstance;
protected DefaultApi $apiInstance;

...

$bearerToken = '<token from the call above>';

$host = $this->baseUrl.'risk/'.$this->apiVersion.'/';

$config = Configuration::getDefaultConfiguration()->setHost($host);
// Use for calls with a session id
$this->apiInstance = new DefaultApi(new Client(), $config);

// Configure Bearer authorization
$config = Configuration::getDefaultConfiguration()
    ->setHost($host)
    ->setAccessToken($bearerToken);
// Use to create a session and get completed assessments at any time
$this->authApiInstance = new DefaultApi(new Client(), $config);
```

## Session concept
As long as API must also be usable from client side application (i.e. from browser) first you establish a short leaved session from a back-end. Then you can pass it to front-end and do not worry about it's disclosure. Or use it from back-end too.
So after you got a session Id, use it for later calls.

### Establishing a session

``` PHP
/**
* @var Session $session
**/
$session = $this->authApiInstance->createSession();
```

### Driver Assessment
> **Important**
>
> Store assessment Id on your back-end to later retrieve the result again

Collect driver data and request an assessment for that driver

``` PHP
$driverAssessmentRequest = new DriverAssessmentRequest([
    'assesee' => new Assessee(
        [
            'first_name' => 'DAVID',
            'middle_name' => 'DAVID',
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
            'line1' => '',
            'line2' => '',
            'zip_code' => '',
            'country' => '',
            'state' => '',
        ]
    ),
]);

/**
* @var DriverAssessmentRequestStatuses $assessment
**/
$assessment = $this->requestAssessment($session->getSessionId(), $driverAssessmentRequest);
```

### Document and selfie verification

To use document and selfie on a front-end you need it's credentials. Here they are

``` PHP
/**
    * @var StripeIdentityVerificationCredentials $stripeVerification
*/
$stripeVerification = $this->getStripeIdentityVerificationCredentials($session->getSessionId(), $assessment->id);
$content = $stripeVerification->getBody()->getContents();
$responseParam = json_decode($content);
$stripeIdentityPublishableKey = $responseParam->stripe_identity_publishable_key;
```

### Getting assessment results later
Get any previous assessment results by assessment Id

``` PHP
/**
* @var DriverAssessmentResults $driverAssessmentResults
*/
$driverAssessmentResults = $this->getDriverAssessmentById($assessment->id);
$content = $stripeVerification->getBody()->getContents();
$responseParam = json_decode($content);

$riskConclusion = $responseParam->lula_safe_conclusion->risk;

$criminal_check_status = $responseParam->criminal_check->status;
$document_check_status = $responseParam->document_check->status;
$identity_check_status = $responseParam->identity_check->status;
$mvr_check_status = $responseParam->mvr_check->status;
```

## Handle non success status codes
Catch `ApiException` and get body as a corresponding type

``` PHP
try {
/**
* @var DriverAssessmentRequestStatuses $assessment
**/
$assessment = $this->requestAssessment($session->getSessionId(), $driverAssessmentRequest);
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
            // Use ProblemDetails as per https://www.rfc-editor.org/rfc/rfc7807
            $problemDetails = $e->getResponseObject(); //'ProblemDetails';
            break;

        // SessionNotFound, no body
        case 404: break;

        // SessionExpired, no body
        case 410: break;

        // Incorrect paramaters supplied
        case 422:
            /**
            * @var ValidationProblemDetails $validationProblemDetails
            */
            // Extended ProblemDetails with validation errors
            $validationProblemDetails = $e->getResponseObject(); // 'ValidationProblem';

            // Get error list per each invalid field
            $errorsPerField = $validationProblemDetails->getErrors();
            break;
    }
}
```
