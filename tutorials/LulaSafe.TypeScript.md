# LulaSafe API

This tutorial will guide you step by step on how to use the LulaSafe GraphQL API.

**Runnable sample is located in [samples](../samples/TypeScript/LulaSafe/src/index.ts)**

**Use `npm run start` after you follow the steps below to restore packages, generate a client and set credentials to `appsecrets.json`**

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
npm run start
```

## Generated client files import

``` TyepScript
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

> **Note**
>
> Here Rest client is used to generate types for GraphQL this can be changed to generate from an introspection file 

## Authentication

> **Important**
>
> Until we add support for OpenID Connect client credentials flow, we need to perform some custom token retrieving actions

### 1. Read your credentials

> **Important**
>
> Create and set your credentials into [`appsecrets.json`](../appsecrets.json) in the repo root.
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
import LulaSafeConfig from "../appsecrets.json";

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

### Set Up GraphQL

First you need to create a GraphQL client here using the [urql](https://github.com/urql-graphql/urql) library you can use any other library

> **Important**
>
> You need to add `session-id` to the header for the api to work correctly in [urql](https://github.com/urql-graphql/urql) this can be specified at client creation time via `fetchOptions`

``` TypeScript
const client = createClient({
        url: Constants.LulaSafeBase + '/graphql',
        
        exchanges: defaultExchanges,
        fetch: fetch,                   // Set custom fetch function for Node.js
        fetchOptions: () => {
            const token = sessionId;
            return {
                headers: { "session-id": token },
            };
        },
    });
```

To show how to work with GraphQL you need to create a mutation to add Assessment to the database and a query to check that Assessment is actually added 

#### Mutation

``` TypeScript
const Assess_Mutation = `
    mutation assess ($address: InputAddress!, $assessee: InputAssessee!) {
        assess {
            id (assessee: $assessee, address: $address)
            checkInsurance (assessee: $assessee, address: $address) {
                started
            }
            requestVehicles (assessee: $assessee, address: $address) {
                started
            }
        }
    }`;
```

#### Query

``` TypeScript
const Assessments_Query = `
    query Assessments ($id: ID!) {
        assessments {
            single (id: $id) {
                address {
                    city
                    country
                    line1
                    line2
                    state
                    zipCode
                }
                assessee {
                    firstName
                    lastName
                    middleName
                }
                insuranceCheck {
                policies {
                    carrierName
                    number
                    status
                    type
                    holders {
                    firstName
                    lastName
                    middleName
                    dateOfBirth
                    }
                    inceptionDate
                    reportedDate
                }
                coverageLaps {
                    isCurrentInforceCoverage
                    hasPossibleLapse
                    coverageIntervals {
                    startDate
                    endDate
                    numberOfCoverageDays
                    numberOfLapseDays
                    }
                }
                }
                    vehicles {
                registration {
                    vehicleInfo {
                    vin
                    makeName
                    model
                    year
                    }
                    address {
                    country
                    zipCode
                    state
                    city
                    line1
                    line2
                    }
                    licensePlate {
                    number
                    }
                }
                }
            }
        }
    }`;
```

### Driver Assessment

> **Important**
>
> Store assessment Id on your back-end to later retrieve the result again

Collect driver data and request an assessment for that driver

``` TypeScript
const assesseeRequest: Assessee = {
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
```

### Сall a mutation

Next, you need to call a mutation to write the data into the database and pass the query that we described above to [urql](https://github.com/urql-graphql/urql). 

> **Important**
>
> It is important to save the id from the result for later verification, which can be found in result.data.assess.id

``` TypeScript
const result =
    await client
        .mutation(Assess_Mutation, {assessee : assesseeRequest, address : addressRequest})
        .toPromise();
driverAssessmentId = result.data.assess.id as string;
```

### Сall the query

In order to check if everything works correctly, let's call the query in a second and display the result in the console. If we see the JSON result, then everything works as it should

``` TypeScript
setTimeout(async () =>
    {
        var result =
            await client
                .query(Assessments_Query, {id : driverAssessmentId})
                .toPromise();
        console.log(JSON.stringify(result.data));
    }, 1000);
```

## Handle non success status codes

In order to catch errors in the result there is an `error` field. Let's display its content if error occured

``` TypeScript
try {
    const result =
        await client
            .mutation(Assess_Mutation, {assessee : assesseeRequest, address : addressRequest})
            .toPromise();

    if (result.error?.message) {
        console.log(JSON.stringify(result?.error?.message));
        return;
    }

    driverAssessmentId = result.data.assess.id as string;
}
catch(error) {
    console.log(error);
}
```
