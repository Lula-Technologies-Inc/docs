# LulaSafe API

This tutorial will guide you step by step on how to use the LulaSafe GraphQL API.

**A runnable example is located in [examples](../examples/TypeScript/src/index.ts)**

# Overview #

1. Restore packages
2. Set credentials in [appsecrets.json](../appsecrets.json)
3. Use `npm run start` to run the application.

## Restoring packages

Install NPM packages specified in [package.json](../examples/TypeScript/LulaSafe/package.json)

``` CMD
cd examples/TypeScript/LulaSafe
npm install 
```

## Setting credentials

The example code looks for a file called [appsecrets.json](../appsecrets.json) in the root of this repository, next to the [appsettings.json](../appsettings.json) file that's already there.  You'll need to create this file and populate it with a `ClientId` and `ClientSecret`, like so:

``` JSON
{
    "ClientId": "< Your Lula login >",
    "ClientSecret": "< Your Lula password >"
}
```

## Session Generator

The [SessionService](../examples/TypeScript/LulaSafe/src/sessionService.ts) class has functions implemented with the generated types necessary to start a LulaSafeAPI session.

The [Index](../examples/TypeScript/LulaSafe/src/index.ts) file has a function demonstrating this workflow from end to end. This can be run using the command:

``` CMD
npm run start
```

# Details

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
> Here the Rest client is used to generate types for GraphQL.  This can be changed to generate from an introspection file.

## Authentication

> **Important**
>
> Until we add support for the OpenID Connect client credentials flow, we need to perform some custom token retrieving actions.

### 1. Read your credentials

These can come from the [appsecrets.json](../appsecrets.json) file, decribed in [Setting credentials](#setting-credentials) above.

``` TypeScript
import LulaSafeConfig from "../appsecrets.json";
```

### 2. Initiate a session
  
``` TypeScript
const flowId = await SessionService.initiateFlowSession()
```
  
### 3. Get a session token used as bearer

``` TypeScript
const flowSessionRequest: FlowSessionRequest = {
    method: "password",
    identifier: LulaSafeConfig.ClientId,
    password: LulaSafeConfig.ClientSecret
}
const sessionToken = await SessionService.createFlowSessionRequest(flowId, flowSessionRequest)
```

## Using the client

### Establishing a session

To avoid repeated transmission of the bearer token or other authentication details, we create a session, and make all subsequent calls providing the session Id in the header.  For security purposes it's recommended to do the session creation on the back end, then pass the only the session Id to the front end (browser) if you want to make calls from there.

``` TypeScript
const sessionId = await SessionService.createSession(sessionToken);
```

### Setting Up GraphQL

Next you need to create a GraphQL client.  This code uses the [urql](https://github.com/urql-graphql/urql) library but you are free to use any other.

> **Important**
>
> You need to add a `session-id` header for the api to work correctly.  This can be done in [urql](https://github.com/urql-graphql/urql) at client creation time, via `fetchOptions`.

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

### Performing an assessment

The assessment process happens in two steps.

1. You send a GraphQL mutation containing the data to be assessed.  This returns an Assessment Id, then executes the assessment, storing the result in a database.
2. You send a GraphQL query with the Assessment Id to retrieve the results.

#### Prepare the mutation

This is the mutation you'll be sending:

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

Assemble the driver data into the following structures for an assessment request:

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

#### Сall the mutation

Call the mutation and pass the query described above to [urql](https://github.com/urql-graphql/urql), saving the returned Assessment Id:

``` TypeScript
const result =
    await client
        .mutation(Assess_Mutation, {assessee : assesseeRequest, address : addressRequest})
        .toPromise();
driverAssessmentId = result.data.assess.id as string;
```

#### Prepare the query

This is the assessment you'll be sending to fetch the results:

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

#### Сall the query

Call [urql](https://github.com/urql-graphql/urql) with the query, passing along the saved value from `driverAssessmentId`.

In order to give the server time to process, we wrap this call in a one-second timer:

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

#### Handle non-success status codes

In order to catch errors in the result there is an `error` field.  This code demonstrates how to display its content if an error occured:

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
