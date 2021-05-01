This is API for https://www.cvdinfo.org which can be found at. [GitHub](https://github.com/amanchik/cvd)

Download and install Docker Desktop

Then install dependencies. 
```sh
composer install
```

Migrate the database
```shell script
php artisan migrate
```

Install passort
```shell script
php artisan passport:install
```

Then use sail
```shell script
./vendor/bin/sail up
```

