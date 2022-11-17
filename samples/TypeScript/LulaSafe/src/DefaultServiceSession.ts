import deepcopy from "deepcopy";

import { CancelablePromise } from "./client/core/CancelablePromise"; 
import { OpenAPI } from './client/core/OpenAPI';
import { request as __request } from './client/core/request';



export class DefaultServiceSession {
    
    /**
     * intiate api session
     * @returns flow id
     * @throws ApiError
     */
    public static intitiateFlowSession(): CancelablePromise<any> {
        //Creating a deep copy so we don't mutate the BASE for other calls
        const OPENAPICopy = deepcopy(OpenAPI);
        OPENAPICopy.BASE = OPENAPICopy.BASE.replace("/risk","");
        return __request(OPENAPICopy, {
            method: 'GET',
            url: '/v1/login/initialize',
            errors: {
                500: `Unexpected server error`,
            },
        });
    }

    /**
    * Fetch Bearer Token
    * @returns session-token
    * @throws ApiError
    */
    public static createFlowSessionRequest(
        flowId: string, 
        flowSessionRequest: any): CancelablePromise<any> {
        //Creating a deep copy so we don't mutate the BASE for other calls
        const OPENAPICopy = deepcopy(OpenAPI);
        OPENAPICopy.BASE = OPENAPICopy.BASE.replace("/risk","");
        return __request(OPENAPICopy, {
            method: 'POST',
            url: '/v1/login/submit?flow={flow-id}',
            path: {
                'flow-id': flowId
            },
            body: flowSessionRequest,
            mediaType: 'application/json',
            errors: {
                500: `Unexpected server error`,
            },
        });
    }

}