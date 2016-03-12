## Installation ##

The recommended way to install this bundle is to rely on [Composer](http://getcomposer.org):

```sh
composer require eidsonator/bootstrap-reports-bundle
```


## Register the bundle

The second step is to register this bundle in the `AppKernel` class:

``` php
public function registerBundles()
{
    $bundles = array(
        // ...
        new Eidsonator\BootstrapReportsBundle\BootstrapReportsBundle(),
    );

    // ...
}
```

## Configuration

The next step is to add configuration settings in your `app/config/config.yml` file.


``` yaml
# in app/config/config.yml
bootstrap_reports:
    # report_directory and dashboard_directory root is the 'web' directory
    report_directory    : "sample_reports/"
	dashboard_directory	: "sample_dashboards"
    default_file_extension_mapping:
        sql :   'Mysql'
        php :   'Php'
        js  :   'Mongo'
        ado :   'Ado'
    environments:
        main:
            mysql:
                host:   %database_host%
                user:   %database_user%
                pass:   %database_password%
                database: %database_name%
    report_formats:
        csv         : 'csv'
        xlsx        : 'Download Excel 2007'
        xls         : 'Download Excel 97-2003'
        text        : 'Text'
        table       : 'Simple table'
        json        : 'JSON'
        xml         : 'XML'
        sql         : 'SQL INSERT command'
        debug       : 'Debug information'
        raw         : 'Raw report dump'
    mail_settings:
        enabled : true
        from    : %mailer_user%
        method  : %mailer_transport%
        server  : %mailer_host%
```

