# Get started

If you're on Windows, check out [ReadMe.Windows.md](README.WINDOWS.md)

## Install dependencies

If you're on MacOS, you may need to install composer.  Use Homebrew:

``` CMD
brew install composer
```

In project
``` CMD
composer install
```

## Run example

### Set your credentials

The example code looks for a file called [appsecrets.json](../../appsecrets.json) in the root of this repository, next to the [appsettings.json](../../appsettings.json) file that's already there.  You'll need to create this file and populate it with a `ClientId` and `ClientSecret`, like so:

``` JSON
{
    "ClientId": "< Your Lula login >",
    "ClientSecret": "< Your Lula password >"
}
```

### Run

``` CMD
php run.php
```