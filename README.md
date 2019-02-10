# Pairs

[![packagist](https://img.shields.io/packagist/v/deemru/pairs.svg)](https://packagist.org/packages/deemru/pairs) [![php-v](https://img.shields.io/packagist/php-v/deemru/pairs.svg)](https://packagist.org/packages/deemru/pairs)  [![travis](https://img.shields.io/travis/deemru/Pairs.svg?label=travis)](https://travis-ci.org/deemru/Pairs) [![codacy](https://img.shields.io/codacy/grade/1b5145f44cdd47bb8117c6d08b013ff0.svg?label=codacy)](https://app.codacy.com/project/deemru/Pairs/dashboard) [![license](https://img.shields.io/packagist/l/deemru/pairs.svg)](https://packagist.org/packages/deemru/pairs)

[Pairs](https://github.com/deemru/Pairs) implements a simple key-value [SQLite](https://en.wikipedia.org/wiki/SQLite) storage.

- Easy to use
- Built-in cache

## Usage

```php
$pairs = new Pairs( __DIR__ . '/storage.sqlite', 'pairs', true );

$key = 1;
$value = 'Hello, World!';
$pairs->setKeyValue( $key, $value );

if( $pairs->getKey( $value ) !== $key ||
    $pairs->getValue( $key ) !== $value )
    exit( 1 );
```

## Requirements

- [PHP](http://php.net) >= 5.4
- [SQLite (PDO)](http://php.net/manual/en/ref.pdo-sqlite.php)

## Installation

Require through Composer:

```json
{
    "require": {
        "deemru/pairs": "1.0.*"
    }
}
```
