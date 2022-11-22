
#!markdown

  

# LulaSafe API

This tutorial will guide you step by step how to use the API having the client code generated from OpenAPI specification

  

## Client code generation

  

##### Install NPM package
Using the [package.json](../samples/TypeScript/LulaSafe/package.json), install
  

```
npm install 
```
and generate the client

```
npm run generate-client
```
## Session Generator
[DefaultServiceSession](../samples/TypeScript/LulaSafe/src/DefaultServiceSession.ts) class has two functions implemented with the generated types that is necessary to set up LulaSafeAPI session.
**Note**
[Index](../samples/TypeScript/LulaSafe/src/index.ts) has a function demonstrating example from end to end. This can be run using the command:

```
npm start
```

## Generated client files import

``` 
import  type { Address} from  "./client";
import  type { Assessee } from  "./client";
import  type { DriverAssessmentRequest } from  "./client";
import  type { DrivingLicense } from  "./client";
import  type { ProblemDetails } from  "./client";
import  type { ValidationProblemDetails } from  "./client";

import { ApiError } from  "./client";
import { DefaultService } from  "./client";
import { DefaultServiceSession } from  "./DefaultServiceSession";
import { OpenAPI } from  "./client/core/OpenAPI"; 
```

## Authentication

>  **Warning**
>
> Until we add support for OpenID Connect client credentials flow, we need to perform some custom token retrieving actions

  

### 1. Initiate session
  
``` 
const initiateFlowSessionResponse = await DefaultServiceSession.intitiateFlowSession();
const flowId = initiateFlowSessionResponse.id; // save flow id
```
  
### 2. Get session token used as bearer

**Important**
Create and set your credentials into [`appsettings.json`](../appsettings.json) in the repo root.
```
{
    "Login": "< Your Lula login >",
    "Password": "< Your Lula password >"
}
```
``` 
import LulaSafeConfig from "../appsettings.json";

const flowSessionRequest = {
    method: "password",
    password: LulaSafeConfig.Password,
    password_identifier: LulaSafeConfig.Login
}
const flowSessionResponse = await DefaultServiceSession.createFlowSessionRequest(flowId, flowSessionRequest);
const bearerToken = flowSessionResponse.session_token; // save bearer Token
```

## Client usage

  

### Prepare client instances
We need to assign the bearer token to the imported `OpenAPI` instance.
Similarly, we need to append the base URL of the  `OpenAPI` instance with its version.
``` 
//Setup base url by appending version to the imported OpenAPI const
OpenAPI.BASE = OpenAPI.BASE+"/v"+OpenAPI.VERSION;
// Assign bearerToken to the imported OpenAPI const for the subsequent calls
OpenAPI.TOKEN = bearerToken; 
```


## Session concept
As long as API must also be usable from client side application (i.e. from browser) first you establish a short leaved session from a back-end. Then you can pass it to front-end and do not worry about it's disclosure. Or use it from back-end too.

So after you got a session Id, use it for later calls.

### Establishing a session

``` 
const createSessionResponse = await DefaultService.createSession();
const sessionId = createSessionResponse.sessionId; // Save SessionId
```

### Driver Assessment

>  **Important**

>

> Store assessment Id on your back-end to later retrieve the result again

  

>  **Note**

>

> In the next update it will be returned from the driver assessment request below

Collect driver data and request an assessment for that driver

``` 
const assesseeRequest :Assessee = {
    firstName: "DAVID",
    lastName: "HOWARD",
    dateOfBirth: "1990-02-02",
    middleName: "Stuard",
    phone: "+1- 206-266-1000",
    email: "newtest@gmail.com"
}
const drivingLicenseRequest: DrivingLicense = {
    id: "U1234591",
    expiryDate: "2024-01-01",
    issuerState: "CA"
}
const addressRequest: Address = {
    state: "WA",
    zipCode: "98109",
    country: "US",
    city: "Seattle",
    line1: "440 Terry Ave N",
    line2: "4053"
}
const driverAssessmentRequest: DriverAssessmentRequest = {
    assessee: assesseeRequest,
    drivingLicense: drivingLicenseRequest,
    address: addressRequest
}
const driverAssessmentResponse = await DefaultService.requestDriverAssessment(sessionId,driverAssessmentRequest);
const driverAssessmentId = JSON.parse(JSON.stringify(driverAssessmentResponse))["Assessment"].value.id;  // save driver assessment id

```
 
### Document and selfie verification

To use document and selfie on a front-end you need it's credentials. Here they are

``` TypeScript
const identityVerificationCredentialsResponse = await DefaultService.getStripeIdentityVerificationCredentials(sessionId, driverAssessmentId);
const stripeIdentityPublishableKey = identityVerificationCredentialsResponse.StripeIdentityPublishableKey;
```

### Getting assessment results later

Get any previous assessment results by assessment Id

``` 
const driverAssessmentByIdResponse = await DefaultService.getDriverAssessmentById(driverAssessmentId);
```

## Handle non success status codes

Catch `ApiException` and get body as a corresponding type
```
try {
    const driverAssessmentRequest: DriverAssessmentRequest = {
        assessee: assesseeRequest,
        drivingLicense: drivingLicenseRequest,
        address: addressRequest
    }
    const driverAssessmentResponse = await DefaultService.requestDriverAssessment(sessionId,driverAssessmentRequest);
} catch (error) {
    if (error instanceof ApiError) {
        switch(error.status)
        {
            //Bad Reqyest
            case  400: {
                let problemDetails = error.body as ProblemDetails;
                console.log(problemDetails);
                break;
            }
            // SessionNotFound, no body
            case  404: {
                let problemDetails = error.body as ProblemDetails;
                console.log(problemDetails);
                break;
            }
            // SessionExpired
            case  410: {
                let problemDetails = error.body as ProblemDetails;
                console.log(problemDetails);
                break;
            }
            // Incorrect Parameters Supplied
            case  422: {
                let validationProblemDetails = error.body as ValidationProblemDetails;
                console.log(ValidationProblemDetails.errors)
                break;
            }
        }
    } else {
        console.log(error);
    }
}
```
