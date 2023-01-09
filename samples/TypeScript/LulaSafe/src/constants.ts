import { BaseUrl } from "../../../../appsettings.json";

export class Constants {
    public static Base = BaseUrl
    public static Version = "/v1"
    public static LulaSafeBase = `${this.Base}/risk`
    public static LulaSafeVersion = "/v0.1-beta1"
    public static LulaSafeGraphQL = `${this.LulaSafeBase}/graphql` 
}