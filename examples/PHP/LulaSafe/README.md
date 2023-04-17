# Get started

If you use Windows take a look at [ReadMe.Windows.md](README.WINDOWS.md)

## Install PHP code generator from OpenAPI specification
`npm install @openapitools/openapi-generator-cli`
## Generate PHP code
`npx openapi-generator-cli generate -g php -i "../../../openapi/lulasafe.yaml" -o ".api-lulasafe"`
## Install dependencies
In project
```
composer install
```
In generated API client
```
cd .api-lulasafe
composer install
```
## Run sample
### Set your credentials
1. Open
[LulaSafeService.php](app\Services\LulaSafeService.php)
2. Find `protected function getToken($flowId)`
3. Set `password_identifier` and `password`
### Execute
```
php artisan command:run
```

> **Warning**
> 
> If you see ` invalid value for  when calling Assessee., must conform to the pattern /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/.`
> Remove that check from Assessee type
