#!markdown

# LulaSafe API

This tutorial shows how to use the LulaSafe GraphQL API with PHP.

## Import required libraries

``` PHP
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client as GuzzleHttpClient;
use GraphQL\Client as GraphQLClient;
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

$client = new GuzzleHttpClient();
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
$response = $client->request('POST', $this->baseUrl . "v1/login/submit?flow={$flowId}", $authRequestOptions);
$content = $response->getBody()->getContents();
$responseParam = json_decode($content);
$token = $responseParam->session_token; // Use as Bearer
```

### 4. Start a session

``` PHP
$requestOptions = [
    'headers' => [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $sessionToken,
    ],
    'allow_redirects' => true
];
$url = $this->baseUrl . $this->lulaSafeBase . $this->lulaSafeVersion . "/sessions";
$response = $client->request('POST', $url, $requestOptions);
$content = $response->getBody()->getContents();
$session = json_decode($content);
$sessionId = $session->sessionId
```

## Sending GraphQL

### Prepare a client instance

``` PHP
$client = new GraphQLClient(
    $this->baseUrl . $this->lulaSafeBase . "/graphql",
    ['session-id' => $sessionId]
);
```

### Driver Assessment

Collect driver data and request an assessment for that driver

> **Important**
>
> Store the returned assessment Id, to later retrieve the results of the assessment

``` PHP
$input = [
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
            checkInsurance (assessee: $assessee, address: $address) { policies { started } }
            requestVehicles (assessee: $assessee, address: $address) { started }
        }
    }';

$results = $client->runRawQuery($gql, false, $input);
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

## Handle non-success status codes

Catch `ApiException` and get body as a corresponding type

An example of this can be found in `LulaSafeService.php` in the example code.
