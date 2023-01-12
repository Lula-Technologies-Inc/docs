# LulaSafe API

This tutorial will guide you step by step how to use the API having the client code generated from OpenAPI specification

## Client code generation

### Install NPM package

using the [package.json](../samples/TypeScript/LulaSafe/package.json)

``` CMD
npm install 
```

and generate the client

``` CMD
npm run generate-client
```

## Session Generator

[DefaultServiceSession](../samples/TypeScript/LulaSafe/src/DefaultServiceSession.ts) class has two functions implemented with the generated types that is necessary to set up LulaSafeAPI session.

> **Note**
>
> [Index](../samples/TypeScript/LulaSafe/src/index.ts) has a function demonstrating example from end to end. This can be run using the command:

``` CMD
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

> **Important**
>
> Until we add support for OpenID Connect client credentials flow, we need to perform some custom token retrieving actions

### 1. Read your credentials

> **Important**
>
> Create and set your credentials into [`appsettings.json`](../appsettings.json) in the repo root.
>
> ``` JSON
> {
>     "ClientId": "< Your Lula login >",
>     "ClientSecret": "< Your Lula password >"
> }
> ```

### 2. Initiate session
  
``` TypeScript
const initiateFlowSessionResponse = await DefaultServiceSession.intitiateFlowSession();
const flowId = initiateFlowSessionResponse.id; // save flow id
```
  
### 3. Get session token used as bearer

``` TypeScript
import LulaSafeConfig from "../appsettings.json";

const flowSessionRequest = {
    method: "password",
    password_identifier: LulaSafeConfig.ClientId
    password: LulaSafeConfig.ClientSecret,
}
const flowSessionResponse = await DefaultServiceSession.createFlowSessionRequest(flowId, flowSessionRequest);
const bearerToken = flowSessionResponse.session_token; // save bearer Token
```

## Client usage

### Prepare client instances

We need to assign the bearer token to the imported `OpenAPI` instance.
Similarly, we need to append the base URL of the  `OpenAPI` instance with its version.

``` TypeScript
//Setup base url by appending version to the imported OpenAPI const
OpenAPI.BASE = OpenAPI.BASE+"/v"+OpenAPI.VERSION;
// Assign bearerToken to the imported OpenAPI const for the subsequent calls
OpenAPI.TOKEN = bearerToken; 
```

## Session concept

As long as API must also be usable from client side application (i.e. from browser) first you establish a short leaved session from a back-end. Then you can pass it to front-end and do not worry about it's disclosure. Or use it from back-end too.

So after you got a session Id, use it for later calls.

### Establishing a session

``` TypeScript
const createSessionResponse = await DefaultService.createSession();
const sessionId = createSessionResponse.sessionId; // Save SessionId
```

### Driver Assessment

> **Important**
>
> Store assessment Id on your back-end to later retrieve the result again

Collect driver data and request an assessment for that driver

``` TypeScript
const assesseeRequest :Assessee = {
    firstName: "Antonio",
    lastName: "Bernette",
    middleName: "",
    dateOfBirth: "1982-11-17",
    phone: "270-555-7152",
    email: "antonio@email.com"
}
const drivingLicenseRequest: DrivingLicense = {
    id: "111119615",
    expiryDate: "2024-10-20",
    issuerState: "KY"
}
const addressRequest: Address = {
    line1: "7104 Cadillac Boulevard",
    line2: "",
    city: "Arlington",
    state: "TX",
    country: "US",
    zipCode: "76016"
}
const driverAssessmentRequest: DriverAssessmentRequest = {
    assessee: assesseeRequest,
    drivingLicense: drivingLicenseRequest,
    address: addressRequest
}
const driverAssessmentResponse = await DefaultService.requestDriverAssessment(sessionId, driverAssessmentRequest);
const driverAssessmentId = driverAssessmentResponse.assessment.id;  // save driver assessment id

```

### Document and selfie verification

To use document and selfie on a front-end you need it's credentials. Here they are

``` TypeScript
const identityVerificationCredentialsResponse = await DefaultService.getStripeIdentityVerificationCredentials(sessionId, driverAssessmentId);
const stripeIdentityPublishableKey = identityVerificationCredentialsResponse.StripeIdentityPublishableKey;
```

### Getting assessment results later

Get any previous assessment results by assessment Id

``` TypeScript
const driverAssessmentByIdResponse = await DefaultService.getDriverAssessmentById(driverAssessmentId);

const criminalCheckStatus = driverAssessmentByIdResponse.CriminalCheck.Status;
const documentCheckStatus = driverAssessmentByIdResponse.DocumentCheck.Status;
const identityCheckStatus = driverAssessmentByIdResponse.IdentityCheck.Status;
const mvrCheckStatus = driverAssessmentByIdResponse.MvrCheck.Status;

const riskConclusion = driverAssessmentByIdResponse.LulaSafeConclusion.Risk;
```

## Handle non success status codes

Catch `ApiException` and get body as a corresponding type

``` TypeScript
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
            //Bad Request
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
