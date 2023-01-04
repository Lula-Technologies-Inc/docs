import * as fs from "fs";
import * as path from "path";
import { getIntrospectionQuery } from "graphql";
import { SessionService } from "./sessionService";
import { FlowSessionRequest } from "./models";
import LulaSafeConfig from "../../../../appsettings.json";
import { getIntrospectedSchema, minifyIntrospectionQuery } from "@urql/introspection";

const introspectSchema = async (sessionId: string, graphqlRoute: string) => {
    const requestOptions: RequestInit = {
        method: "POST",
        headers: { 
            'Content-Type': 'application/json',
            'session-id': sessionId
     },
        body: JSON.stringify({ variables: {}, query: getIntrospectionQuery({ descriptions: false }) })
    }
    return fetch(graphqlRoute, requestOptions)
        .then(resp => resp.json())
        .then(resp => { console.log(resp); return resp})
        .then(({ data }) => {
            const minified = minifyIntrospectionQuery(getIntrospectedSchema(data))
            const schemaPath = path.resolve(__dirname, "../../../../graphql/lulasafe.introspection.json")
            fs.writeFile(schemaPath, JSON.stringify(minified),
                err => {
                    if (err) {
                        console.error('Writing failed:', err)
                    }
                    console.info('Schema written!')
                })})
    }

(async () => {
    
    const flowId = await SessionService.initiateFlowSession()
    const flowSessionRequest: FlowSessionRequest = {
        method: "password",
        password: LulaSafeConfig.ClientSecret,
        password_identifier: LulaSafeConfig.ClientId
    }
    const sessionToken = await SessionService.createFlowSessionRequest(flowId, flowSessionRequest)
    const sessionId = await SessionService.createSession(sessionToken);
    introspectSchema(sessionId, "https://api.staging-lula.is/risk/graphql")
})()