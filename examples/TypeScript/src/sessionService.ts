import { Constants } from "./constants"
import { FlowSessionRequest } from "./models"

export class SessionService {

    public static async initiateFlowSession() {
        const path = "/login/initialize"
        const requestOptions: RequestInit = {
           method: "GET",
           redirect: "follow"
        }
        return fetch(`${Constants.Base}${Constants.AuthVersion}${path}`, requestOptions)
        .then(resp => resp.json())
        .then(resp => resp?.id)
        .catch((error: Error) => console.error(error))
    }

    public static async createFlowSessionRequest(flowId: string, sessionRequest: FlowSessionRequest) {
        const path = `/login/submit?flow=${flowId}`
        const headers: HeadersInit = {
            "Content-Type": "application/json"
        }
        const body = JSON.stringify(sessionRequest)
        const requestOptions: RequestInit = {
            method: "POST",
            headers: headers,
            body: body,
            redirect: 'follow'
        }
        return fetch(`${Constants.Base}${Constants.AuthVersion}${path}`, requestOptions)
        .then(resp => resp.json())
        .then((resp: Record<string, any>) => resp?.session_token)
        .catch((error: Error) => console.error(error.message))
    }
    
    public static async createSession(bearerHeaderValue: string) {
        const path = `${Constants.LulaSafeBase}${Constants.LulaSafeVersion}/sessions`
        const headers: HeadersInit = {
            "Content-Type": "application/json",
            "Authorization": `Bearer ${bearerHeaderValue}`
        }
        const requestOptions: RequestInit = {
            method: "POST",
            headers: headers,
            redirect: 'follow'
        }
        return fetch(path, requestOptions)
        .then(resp => resp.json())
        .then((resp: Record<string, any>) => resp?.sessionId)
        .catch((error: Error) => console.error(error.message))
    }
}