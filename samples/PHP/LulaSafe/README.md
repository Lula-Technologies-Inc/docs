# Get started

If you're on Windows, check out [ReadMe.Windows.md](README.WINDOWS.md)

## Install dependencies

If you're on MacOS, you may need to install PHP.  Use homebrew:

``` CMD
brew install php
brew install composer
```

In project
``` CMD
composer install
```

## Run sample

### Set your credentials

The example code looks for a file called `appsecrets.json` in the root of this repository, next to the `appsettings.json` file that's already there.  You'll need to create this file and populate it with a `ClientId` and `ClientSecret`, like so:

``` JSON
{
    "ClientId": "< Your Lula login >",
    "ClientSecret": "< Your Lula password >"
}
```

### Run with Artisan

``` CMD
php artisan command:run
```

> **Warning**
> 
> If you see ` invalid value for  when calling Assessee., must conform to the pattern /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/.`
> Remove that check from Assessee type
