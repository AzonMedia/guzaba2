# Guzaba Framework v2

## Overview

Guzaba2 is a research framework. It explores the use nested transactions and automatic (partial) rollback, different design patterns and implementations.
It is not intended to be used in production (and may never be), it is not yet documented.
The testbed for Guzaba2 is [Guzaba Platform](https://github.com/AzonMedia/guzaba-platform).
The framework has certain traits (very) similar with other frameworks (it borrows code from [Slim](https://www.slimframework.com/)) and it is very different in other spects.
If you would like to discuss certain feature or design decition in the framework please open a [Discussion](https://github.com/AzonMedia/guzaba2/discussions).

Guzaba2 (and Guzaba1) were created because there was no other framework providing:
- nested transactions - supports partial transaction rollback and the transaction can continue and commit
- automatic transaction rollback on abandoning the scope be it becase of an exception or a return ([SBRM](https://en.wikipedia.org/wiki/Resource_acquisition_is_initialization))
- callbacks on various transaction events - you can add conditional block if the current (be it the master or nested) transaction commits or rollbacks
- rollback reasons - in your callback block you can check why the transaction was rolled back - explicitly or implicitly due an exception or a return
- you can also obtain the exception which rolled back your transaction no matter was the exception caught or not
- ActiveRecord objects transaction - the objects will have their properties updated automatically if the transaction is rolled back (and you can still save them if needed!)
- the transaction and transaction manager can be used to implement custom transactions (like filesystem transactions)

Guzaba2 improves on and adds support for: 
- [Swoole](http://swoole.com/) based
- database connections pool
- automatic deallocation of obtained resources/connections (SBRM)
- shared memory for the ORM objects - the ActiveRecord objects are just pointers to shared array with data between all coroutines
- better ACL support compared to Guzaba1 - both for objects and classes/static methods
- many speed optimizations (made possible by the persistent memory model of swoole) - everything is kept in local memory as a native object! No need of serialization and unserialization!
- parallel async queries & operations (thanks to swoole coroutines)
- it can return response in less than 1 msec! ([GuzabaPlatform](https://github.com/AzonMedia/guzaba-platform) with the [request-caching component](https://github.com/AzonMedia/component-request-caching)). This is not a time based/expiration cache but cache based on the actual business logic (update times of various objects/records and what can and cannot be cached)!
- has debugger (over telnet)

And some commonly found functionality:
- PSR-7, PSR-11, PSR-15, PSR-3 (support for PSR-14, PSR-16 and PSR-17 coming)
- ActiveRecord with temporal records and logging, multiple backend stores
- ActiveRecord propety hooks (setting, getting, validation) and method hooks (before save, after save etc)
- ACL permissions
- events
- routing
- registry
- dependency injection
- caching (in memory, redis, memcached)

And some specifics:
- uses typed properties (PHP 7.4), union types, named parameters, attributes ([PHP 8.0](https://www.php.net/releases/8.0/en.php))
- Guzaba2 currently supports only MySQL and Redis because these are the only database drivers for which Swoole supports coroutines
- PostgreSQL support will be added as Swoole has a separate (less supported) driver for it
- The MySQL store for ActiveRecord objects internally works with IDs for better performance while for API access it supports UUIDs
- the back-end store functionality supports replacing the store of the ActiveRecord objects but currently there are SQL specifics that will prevent it to work with NoSQL DBs. This will be corrected in future.
- currently there is no database migrations support, but will be aded in future
- everything is an ActiveRecord - the models, the log entries, the controllers, the permissions
- permissions can be granted on objects (records) and classes (static methods)
- tries to avoid factories when possible
- use of magic methods and references
- uses static code for configuration. Classes with injected configuration constant are geenrated at startup.

And what does not support (and probably never will):
- the framework does not aim to replace SQL thus there is no support for creating SQL queries with a replacement language or creating queries with a query builder.
It supports ActiveRecord but not a complete ORM implementation like Doctrine.
The reasoning is that type of projects (large, complex depending on DB specifics) can not avoid the manual writing and optimization of (very) large queries and the SQL language is good at what it does.
DB migration from one vendor to another is not supported (since the software depends on the specifics of the DB).
- there is no automatic schema generation based on PHP classes/structures. The reverse logic is in place - the framework configures/generates the classes based on the DB schema.

The overall reasoning is that the framework is not to blurr or hide the backend storage details but instead to make it easier to work with it and to reduce the human errors related to its use.
Guzaba2 is adding to this excellent speed and very high concurrency support by being based on Swoole and by making good use of its persistent memory model and coroutines.

## Requirements
- PHP 8.0+
- Swoole 4.5+
- MySQL 8.0+

## Installation

## Documentation

Documentation is available [here](https://github.com/AzonMedia/guzaba2-docs).

## Software using Guzaba 2

- [GuzabaPlatofm](https://github.com/AzonMedia/guzaba-platform)
- [Glog](https://github.com/AzonMedia/glog)