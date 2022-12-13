import {
    Address,
    Assessee,
    DrivingLicense,
    DefaultService,
    DriverAssessmentRequest,
    ApiError,
    ProblemDetails,
    ValidationProblemDetails,
    CandidateInformation,
    CheckByDriverInformation
} from "./client"

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
        password: LulaSafeConfig.ClientId,
        password_identifier: LulaSafeConfig.ClientSecret
    }
    const flowSessionResponse = await DefaultServiceSession.createFlowSessionRequest(flowId, flowSessionRequest);
    const bearerToken = flowSessionResponse.session_token;

    // Setup base url by appending version
    OpenAPI.BASE = OpenAPI.BASE + "/v" + OpenAPI.VERSION;
    // Assign bearer token to the OpenAPIConfig
    OpenAPI.TOKEN = bearerToken;

    // Step 3. Create a driver assessment session
    const createSessionResponse = await DefaultService.createSession();
    const sessionId = createSessionResponse.sessionId;

    const assesseeRequest: Assessee = {
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
            switch (error.status) {
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

    // Step 5. Check assessment results later after 10 seconds
    setTimeout(async () => {
        const driverAssessmentByIdResponse = await DefaultService.getDriverAssessmentById(driverAssessmentId);
    }, 10000);

    // Step 6. Provide candidate information to check insurance status
    const mapDriverAssessmentToCandidate = (assessee: Assessee, drivingLicense: DrivingLicense, address: Address, lp?: string, vin?: string): CandidateInformation => ({
        firstName: assessee.firstName,
        middleName: assessee.middleName,
        lastName: assessee.lastName,
        dob: assessee.dateOfBirth,
        email: assessee.email,
        phone: assessee.phone!,
        postalAddress: {
            addressLine1: address.line1!,
            addressLine2: address.line2!,
            city: address.city!,
            country: address.country,
            state: address.state!,
            zipCode: address.zipCode!
        },
        license: {
            expiry: drivingLicense.expiryDate,
            number: drivingLicense.id,
            state: drivingLicense.issuerState
        },
        vehicle: {
            licensePlate: lp,
            vin: vin
        }
    })
    const candidateInfoWithPlateNumberAndVin = mapDriverAssessmentToCandidate.bind(null, assesseeRequest, drivingLicenseRequest, addressRequest)
    const wrapCandidateInfo = (candidateInfo: CandidateInformation): CheckByDriverInformation => ({
        candidate: candidateInfo
    })
    const insuranceRequestData = wrapCandidateInfo(
        candidateInfoWithPlateNumberAndVin("HRB386", "1234567AB0C1234567"))

    const insuranceResponse = await DefaultService.requestCheckByDriverInformation(driverAssessmentId, insuranceRequestData)

    console.table(insuranceResponse.currentInsuranceDetails)
    insuranceResponse.historicalCoverageDetails?.forEach((oldCoverageDetails, index) => {
        console.log(`Coverage Details Entry #${index}:`)
        console.table(oldCoverageDetails)
    })
    insuranceResponse.claims?.forEach((claim, index) => {
        console.log(`Claims Entry #${index}:`)
        console.table(claim)
    })
    insuranceResponse.cancellations?.forEach((cancellation, index) => {
        console.log(`Cancellation Entry #${index}:`)
        console.table(cancellation)
    })
    insuranceResponse.vehicleDetails?.forEach((vehicleDetails, index) => {
        console.log(`Vehicle Entry #${index}:`)
        console.table(vehicleDetails)
    })
})()
