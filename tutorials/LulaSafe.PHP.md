#!markdown

# LulaSafe API

This tutorial will show you how to use the API in PHP.

## Import required libraries

``` PHP
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
```

## Authentication
> **Warning**
>
> Until we add support for OpenID Connect client credentials flow, we need to perform some custom token retrieving actions

### 1. Setting credentials

One example way of storing the credentials is using a JSON file.  The following code reads a JSON file in and gets values for `ClientId` and `ClientSecret`.

``` JSON
{
    "ClientId": "< Your Lula login >",
    "ClientSecret": "< Your Lula password >"
}
```

``` PHP
$secrets_file = file_get_contents('../appsecrets.json');
$secrets_json = json_decode($secrets_file, false);

$clientId = $secrets_json->ClientId;
$clientSecret = $secrets_json->ClientSecret;
```

### 2. Initiate a session

``` PHP
protected string $baseUrl = 'https://api.lula.is';
protected string $version = "/v1";

...

$client = new Client();
$response = $client->request('GET', $this->baseUrl . $this->version . '/login/initialize');
$content = $response->getBody()->getContents();
$responseParam = json_decode($content);
$flowId = $responseParam->id;
```

### 3. Get a session token used as bearer

``` PHP
$authRequestOptions = [
    'json' => [
        'method' => 'password',
        'identifier' => $clientId,
        'password' => $clientSecret,
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

$criminal_check_status = $responseParam->criminal_check->status;
$document_check_status = $responseParam->document_check->status;
$identity_check_status = $responseParam->identity_check->status;
$mvr_check_status = $responseParam->mvr_check->status;

$riskConclusion = $responseParam->lula_safe_conclusion->risk;
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
