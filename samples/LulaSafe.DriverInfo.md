
## Example #1

| Object | C# / F# | Python | PHP |
---- | ---- | ---- | ----
| Assessee        | FirstName = "DAVID",<br/>MiddleName = "Stuard",<br/>LastName = "HOWARD",<br/>DateOfBirth = new DateTime(1990, 02, 02),<br/>Phone = "+1 206-266-1000",<br/>Email = "newtest@gmail.com" | first_name="DAVID",<br/>middle_name="Stuard",<br/>last_name="HOWARD",<br/>date_of_birth=datetime(1990,2,2).date(),<br/>phone="+1 206-266-1000",<br/>email=Email("newtest@gmail.com") | 'first_name' => 'DAVID',<br/>'middle_name' => 'Stuard',<br/>'last_name' => 'HOWARD',<br/>'date_of_birth' => Carbon::create(1990, 02, 02),<br/>'phone' => '+1 206-266-1000',<br/>'email' => 'newtest@gmail.com' |z
| DrivingLicense  | Id = "U1234591",<br/>ExpiryDate = new DateTime(2024, 01, 01),<br/>IssuerState = "CA" | driving_license_id = DrivingLicenseId("U1234591"),<br/>expiry_date=datetime(2024,1,1).date(),<br/>issuer_state=IssuerState("CA") |  'id' => 'U1234591',<br/>'expiry_date' => Carbon::create(2024, 01, 01),<br/>'issuer_state' => 'CA' |
| Address         | Line1 = "",<br/>Line2 = "",<br/>ZipCode = "",<br/>Country = "US",<br/>State = "WA" | line1 = "",<br/>line2 = "",<br/>zip_сode = "",<br/>country = "US",<br/>state = "WA" | 'line1' => '',<br/>'line2' => '',<br/>'zip_code' => '',<br/>'country' => 'United States',<br/>'state' => 'WA' |

## Example #2

| Object | C# / F# | Python | PHP |
---- | ---- | ---- | ----
| Assessee        | FirstName = "Nicole",<br/>MiddleName = "",<br/>LastName = "WILLAMSON",<br/>DateOfBirth = new DateTime(1980, 10, 20),<br/>Phone = "8572044606",<br/>Email = "nicole@gmail.com" | first_name="Nicole",<br/>middle_name="",<br/>last_name="WILLAMSON",<br/>date_of_birth=datetime(1980,10,20).date(),<br/>phone="8572044606",<br/>email=Email("nicole@gmail.com") | 'first_name' => 'Nicole',<br/>'middle_name' => '',<br/>'last_name' => 'WILLAMSON',<br/>'date_of_birth' => Carbon::create(1980, 10, 20),<br/>'phone' => '8572044606',<br/>'email' => 'nicole@gmail.com' |z
| DrivingLicense  | Id = "111119685",<br/>ExpiryDate = new DateTime(2026, 04, 02),<br/>IssuerState = "KY" | driving_license_id = DrivingLicenseId("111119685"),<br/>expiry_date=datetime(2026,4,2).date(),<br/>issuer_state=IssuerState("KY") |  'id' => '111119685',<br/>'expiry_date' => Carbon::create(2026, 04, 02),<br/>'issuer_state' => 'KY' |
| Address         | Line1 = "",<br/>Line2 = "",<br/>ZipCode = "11766",<br/>Country = "US",<br/>State = "" | line1 = "",<br/>line2 = "",<br/>zip_сode = "11766",<br/>country = "US",<br/>state = "" | 'line1' => '',<br/>'line2' => '',<br/>'zip_code' => '11766',<br/>'country' => 'United States',<br/>'state' => '' |


## Example #3

| Object | C# / F# | Python | PHP |
---- | ---- | ---- | ----
| Assessee        | FirstName = "GEORGE",<br/>MiddleName = "",<br/>LastName = "BANKS",<br/>DateOfBirth = new DateTime(1992, 01, 01),<br/>Phone = "8322044544",<br/>Email = "george@gmail.com" | first_name="GEORGE",<br/>middle_name="",<br/>last_name="BANKS",<br/>date_of_birth=datetime(1992,1,1).date(),<br/>phone="8322044544",<br/>email=Email("george@gmail.com") | 'first_name' => 'GEORGE',<br/>'middle_name' => '',<br/>'last_name' => 'BANKS',<br/>'date_of_birth' => Carbon::create(1992, 01, 01),<br/>'phone' => '8322044544',<br/>'email' => 'george@gmail.com' |z
| DrivingLicense  | Id = "111111508",<br/>ExpiryDate = new DateTime(2027, 06, 10),<br/>IssuerState = "LA" | driving_license_id = DrivingLicenseId("111111508"),<br/>expiry_date=datetime(2027,6,10).date(),<br/>issuer_state=IssuerState("LA") |  'id' => '111111508',<br/>'expiry_date' => Carbon::create(2027, 06, 10),<br/>'issuer_state' => 'LA' |
| Address         | Line1 = "8 Westcliff dr",<br/>Line2 = "",<br/>ZipCode = "",<br/>Country = "US",<br/>State = "" | line1 = "8 Westcliff dr",<br/>line2 = "",<br/>zip_сode = "",<br/>country = "US",<br/>state = "" | 'line1' => '8 Westcliff dr',<br/>'line2' => '',<br/>'zip_code' => '',<br/>'country' => 'United States',<br/>'state' => '' |
