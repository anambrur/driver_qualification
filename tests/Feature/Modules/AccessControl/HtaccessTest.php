<?php

/*
| ACL-06: the root .htaccess (used by the cPanel host, which serves the project root and
| rewrites to /public) sent every port-80 request to http://127.0.0.1:8000, i.e. to the
| visitor's own machine, before the HTTP→HTTPS redirect could run.
*/

function aclRootHtaccess(): string
{
    return file_get_contents(base_path('.htaccess'));
}

it('never redirects visitors to a loopback address', function () {
    expect(aclRootHtaccess())
        ->not->toContain('127.0.0.1')
        ->not->toContain('localhost')
        ->not->toContain('SERVER_PORT');
});

it('still rewrites to /public, forces HTTPS and keeps the cPanel PHP handler', function () {
    expect(aclRootHtaccess())
        ->toContain('RewriteRule ^(.*)$ /public/$1 [L,QSA]')
        ->toContain('RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]')
        ->toContain('AddHandler application/x-httpd-ea-php83 .php .php8 .phtml');
});

it('defaults APP_DEBUG to off when the variable is missing', function () {
    expect(file_get_contents(config_path('app.php')))
        ->toContain("'debug' => (bool) env('APP_DEBUG', false)");
});
