TC-ErrorHandler
===============

[![Build Status](https://travis-ci.org/ThomasCantonnet/TC-ErrorHandler.png?branch=master)](https://travis-ci.org/ThomasCantonnet/TC-ErrorHandler)

This library aims at providing a simple way to handle errors in all of your ZF2 application. When an error is triggered, whether it is in your controller, your libraries or anywhere in your views, an exception is thrown hence preserving your application from unmonitored errors. It is then possible to log them through the MvcEvent::EVENT_RENDER_ERROR event.

Installation
------------

Suggested installation method is through [composer](http://getcomposer.org/):

```php
php composer.phar require thomascantonnet/tc-errorhandler:1.*
```

Setup
-----

If you use Zend Framework 2, you can now enable this module in your application by
adding it to `config/application.config.php` as `TCErrorHandler`. Make sure to set it as the first module to be loaded, or at least before any module you want to monitor.

