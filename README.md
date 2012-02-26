## About

Sonic Framework is a blazing fast PHP 5 MVC framework.

### Version
1.1 beta

### System Requirements
PHP 5.3+

### Recommended Server Requirements
MySQL 5, Memcached

### Recommended PHP Extensions
Memcache, APC, PDO

### Simple Installation
```sh
wget http://sonicframework.com/sonic.tar.gz
mkdir sonic && tar xzvf sonic.tar.gz -C sonic && cd sonic
./install /path/to/install/to AppName
```
Follow instructions in ``/path/to/install/to/SETUP`` file

### Advanced Installation

1.  Download latest source from: [http://www.sonicframework.com/sonic.tar.gz]
2.  Create a directory somewhere where you want the application to live
3.  Within that directory add the sonic library from step 1 and some other
    directories and files that the app will need.

You want your application structure to look like this:

```text
[] = directory
* = file

[] application_name
    [] configs
        * app.ini
        * routes.php
    [] controllers
        * main.php
    [] libs
        [] Sonic (downloaded in step 1)
        [] MyApp
    [] public_html
        * .htaccess
        [] assets
            [] css
            [] img
            [] js
        * index.php
    [] views
        [] main
            * index.phtml
            * error.phtml
```

4.  In ``/public_html/.htaccess`` add the following:

```apache
SetEnv ENVIRONMENT development
RewriteEngine On
RewriteRule ^.htaccess$ - [F,L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule \.*$ /index.php
```
5.  In ``/public_html/index.php`` add the following:

```php
<?php
set_include_path(str_replace('/public_html', '/libs', $_SERVER['DOCUMENT_ROOT']));
include 'Sonic/Core.php';
use \Sonic\App;
$app = App::getInstance();

// if you would like to use an App Delegate uncomment this line
// $app->setDelegate('{MyApp}\App\Delegate');

$app->start();
```
6.  Setup an apache vhost to point to your /public_html directory with
    DirectoryIndex set to index.php and add the server name to your /etc/hosts.
7.  That's all there is to it, but your app won't work until you add some
    controllers and views.  For more tutorials visit: 
    http://www.sonicframework.com/tutorials
