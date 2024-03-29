export type FlowSessionRequest = {
    csrf_token?: string
    method: string
    password: string
    identifier: string
}

export type Assessee = {
    firstName: string
    lastName: string
    middleName?: string
    dateOfBirth: string
    phone?: string
    email?: string
}

export type Address = {
    line1: string
    line2?: string
    zipCode: string
    country: string
    state: string
    city: string
}

export type DrivingLicense = {
    id: string
    expiryDate: string
    issuerState: string
}
