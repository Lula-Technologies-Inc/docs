import type { Address} from "./client";
import type { Assessee } from "./client";
import type { DriverAssessmentRequest } from "./client";
import type { DrivingLicense } from "./client";
import type { ProblemDetails } from "./client";
import type { ValidationProblemDetails } from "./client";

import { ApiError } from "./client";
import { DefaultService } from "./client";
import { DefaultServiceSession } from "./DefaultServiceSession";
import { OpenAPI } from "./client/core/OpenAPI";
import LulaSafeConfig from "../../../../appsettings.json";

(async () => {
    let driverAssessmentId: string = "";

    // Step 1. Intiatiate session
    const initiateFlowSessionResponse = await DefaultServiceSession.intitiateFlowSession();
    const flowId = initiateFlowSessionResponse.id;

    // Step 2. Get bearer token for the session
    const flowSessionRequest = {
        method: "password",
        password: LulaSafeConfig.ClientSecret,
        password_identifier: LulaSafeConfig.ClientId
    }
    const flowSessionResponse = await DefaultServiceSession.createFlowSessionRequest(flowId, flowSessionRequest);
    const bearerToken = flowSessionResponse.session_token;

    //Setup base url by appending version
    OpenAPI.BASE = OpenAPI.BASE+"/v"+OpenAPI.VERSION;
    // Assign bearertoken to the OpenAPIConfig
    OpenAPI.TOKEN = bearerToken;
    // Step 3. Create a driver assessment session
    const createSessionResponse = await DefaultService.createSession();
    const sessionId = createSessionResponse.sessionId;

    const assesseeRequest :Assessee = {
        firstName: "Antonio",
        lastName: "Bernette",
        dateOfBirth: "1982-11-17",
        middleName: "",
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
    try {
        const driverAssessmentRequest: DriverAssessmentRequest = {
            assessee: assesseeRequest,
            drivingLicense: drivingLicenseRequest,
            address: addressRequest
        }
        const driverAssessmentResponse = await DefaultService.requestDriverAssessment(sessionId, driverAssessmentRequest);
        driverAssessmentId = driverAssessmentResponse.assessment.value?.id as string;
    } catch (error) {
        if (error instanceof ApiError) {
            switch(error.status)
            {
                //Bad Request
                case 400: {
                    let problemDetails = error.body as ProblemDetails;
                    console.log(problemDetails);
                    break;
                }
                // SessionNotFound, no body
                case 404: {
                    let problemDetails = error.body as ProblemDetails;
                    console.log(problemDetails);
                    break;
                }
                // SessionExpired
                case 410: {
                    let problemDetails = error.body as ProblemDetails;
                    console.log(problemDetails);
                    break;
                }
                // Incorrect Parameters Supplied
                case 422: {
                    let validationProblemDetails = error.body as ValidationProblemDetails;
                    console.log(validationProblemDetails.errors)
                    break;
                }
            }
        } else {
            console.log(error);
        }
    }


    // Step 4. Get credentials for document and selfie verification on the front-end
    const identityVerificationCredetialsResponse = await DefaultService.getStripeIdentityVerificationCredentials(sessionId, driverAssessmentId);

    //Step 5. Check assessment results later after 10 seconds
    setTimeout(async () =>
    {
        const driverAssessmentByIdResponse = await DefaultService.getDriverAssessmentById(driverAssessmentId);
    },10000);
})()
