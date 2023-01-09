import 'cross-fetch/polyfill';
import * as fs from "fs";
import * as path from 'path';
import LulaSafeConfig from "../../../../appsecrets.json";
import { createClient,  defaultExchanges } from '@urql/core';

import { FlowSessionRequest, Assessee, Address } from "./models"
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
        password: LulaSafeConfig.ClientSecret,
        password_identifier: LulaSafeConfig.ClientId
    }
    const sessionToken = await SessionService.createFlowSessionRequest(flowId, flowSessionRequest)
    console.info(`Session Token: '${sessionToken}'`)

    // Step 3. Create a driver assessment session
    const sessionId = await SessionService.createSession(sessionToken);
    console.info(`SessionId: '${sessionId}'`)

    // Create GraphQL client
    
    const client = createClient({
        url: Constants.LulaSafeBase + '/graphql',
        
        exchanges: defaultExchanges,
        fetch: fetch,                                       // Set custom fetch function for Node.js
        fetchOptions: () => {
            const token = sessionId;
            return {
                headers: { "session-id": token },
            };
        },
    });
    const queriesPath = path.resolve(__dirname, "../../../../graphql")

    const assessMutation = fs.readFileSync(path.join(queriesPath, "CheckInsuranceAndRequestVehicles.gql"), 'utf8')
    const assessmentQuery = fs.readFileSync(path.join(queriesPath, "RetrieveInsuranceAndVehiclesResult.gql"), 'utf8')

    const assesseeRequest: Assessee = {
        firstName: "Lauraine",
        lastName: "Daen",
        middleName: "Wilburt",
        dateOfBirth: "1986-5-5",
        phone: "",
        email: ""
    }

    const addressRequest: Address = {
        line1: "2600 piedmont road",
        line2: "Unit 12",
        zipCode: "30305",
        country: "US",
        city: "Atlanta",
        state: "Georgia"
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
