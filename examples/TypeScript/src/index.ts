import 'cross-fetch/polyfill';
import * as fs from "fs";
import * as path from 'path';
import LulaSafeConfig from "../../../appsecrets.json";
import { createClient,  defaultExchanges } from '@urql/core';

import { FlowSessionRequest, Assessee, Address, DrivingLicense } from "./models"
import { SessionService } from "./sessionService";
import { Constants } from "./constants";

(async () => {
    let driverAssessmentId: string = "";

    // Step 1. Intiatiate session
    const flowId = await SessionService.initiateFlowSession()
    console.info(`Flow Id: '${flowId}'`)

    // Step 2. Get bearer token for the session
    const flowSessionRequest: FlowSessionRequest = {
        method: "password",
        identifier: LulaSafeConfig.ClientId,
        password: LulaSafeConfig.ClientSecret
    }
    const sessionToken = await SessionService.createFlowSessionRequest(flowId, flowSessionRequest)
    console.info(`Session Token: '${sessionToken}'`)

    // Step 3. Create a driver assessment session
    const sessionId = await SessionService.createSession(sessionToken);
    console.info(`SessionId: '${sessionId}'`)

    // Create GraphQL client
    const client = createClient({
        url: Constants.LulaSafeGraphQL,

        exchanges: defaultExchanges,
        fetch: fetch,                                       // Set custom fetch function for Node.js
        fetchOptions: () => {
            const token = sessionId;
            return {
                headers: { "session-id": token },
            };
        },
    });
    const queriesPath = path.resolve(__dirname, "../../../graphql")

    const assessMutation = fs.readFileSync(path.join(queriesPath, "CheckInsuranceAndRequestVehicles.gql"), 'utf8')
    const assessmentQuery = fs.readFileSync(path.join(queriesPath, "RetrieveInsuranceAndVehiclesResult.gql"), 'utf8')

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
    //Step 4. Сall mutation to send data to the server
    try {
        const result =
            await client
                .mutation(assessMutation, { assessee: assesseeRequest, address: addressRequest })
                .toPromise();

        if (result.error?.message) {
            console.log(JSON.stringify(result?.error?.message));
            return;
        }

        driverAssessmentId = result.data.assess.id as string;
        console.info(`Assessment query ID returned from server: '${driverAssessmentId}'`)
    }
    catch (error) {
        console.error(error);
    }

    //Step 5. Check assessment results later after 1 second
    setTimeout(async () => {
        const result =
            await client
                .query(assessmentQuery, { id: driverAssessmentId })
                .toPromise();
        const output = JSON.stringify(result.data, null, 2)
        console.info(`Assessment Results: \n${output}`);
    }, 1000);
})()
