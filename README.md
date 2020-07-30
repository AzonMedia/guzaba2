# Guzaba Framework v2

## Overview

Why yet another PHP framework? Guzaba2 (and Guzaba1) were created because there was no other framework providing:
- nested transactions - supports partial transaction rollback and the transaction can continue and commit
- automatic transaction rollback on abandoning the scope be it becase of an exception or a return ([SBRM](https://en.wikipedia.org/wiki/Resource_acquisition_is_initialization))
- callbacks on various transaction events - you can add conditional block if the current (be it the master or nested) transaction commits or rollbacks
- rollback reasons - in your callback block you can check why the transaction was rolled back - explicitly or implicitly due an exception or a return
- you can also obtain the exception which rolled back your transaction no matter was the exception caught or not
- ActiveRecord objects transaction - the objects will have their properties updated automatically if the transaction is rolled back (and you can still save them if needed!)
- the transaction and transaction manager can be used to implement custom transactions (like filesystem transactions)

And Guzaba2 adds support for: 
- Swoole based
- database connection pool
- automatic deallocation of obtained resources/connections (SBRM)
- shared memory for the ORM objects - the ActiveRecord objects are just pointers to shared array with data between all coroutines
- better ACL support compared to Guzaba1 - both for objects and classes/static methods
- many speed optimizations (made possible by the persistent memory model of swoole) - everything is kept in local memory as a native object! No need of serialization and unserialization!
- parallel async queries & operations (thanks to swoole coroutines)
- it can return response in less than 1 msec! ([GuzabaPlatform](https://github.com/AzonMedia/guzaba-platform) with the [request-caching component](https://github.com/AzonMedia/component-request-caching)). This is not a time based/expiration cache but cache based on the actual business logic (update times of various objects/records and what can and cannot be cached)!

And of course the more or less standard things like:
- PSR-7, PSR-11, PSR-15, PSR-3 (support for PSR-14, PSR-16 and PSR-17 coming)
- ORM layer with temporal records and logging, multiple backend stores
- ORM objects propety hooks (setting, getting, validation) and method hooks (before save, after save etc)
- ACL permissions
- events
- routing
- registry
- dependency injection
- caching (in memory, redis, memcached)
- ...(other commonly found functionality)

And some specifics:
- Guzaba2 currently supports only MySQL and Redis because these are the only database drivers for which Swoole supports coroutines
- PostgreSQL support will be added as Swoole has a separate (less supported) driver for it
- The MySQL store for ActiveRecord objects internally works with IDs for better performance while for API access it supports UUIDs
- the back-end store functionality supports replacing the store of the ActiveRecord objects but currently there are SQL specifics that will prevent it to work with NoSQL DBs. This will be corrected in future.
- currently there is no database migrations support, but will be aded in future

And what does not support (and probably never will):
- the ORM does not aim to replace SQL thus there is no support for creating SQL queries with a replacement language or creating queries all with PHP objects/methods.
The reasoning is that type of projects (large, complex depending on DB specifics) can not avoid the manual writing and optimization of (very) large queries and DB migration from one vendor to another is not considered 9as the software depends on the specifics of the DB).
- there is no automatic schema generation based on PHP classes/structures. The reverse logic is in place - the framework configures the objects based on the DB schema.

The overall reasoning is that the framework is not to blurr or hide the backend storage details but instead to make it easier to work with it and to reduce the human errors related to its use.
Guzaba2 is adding to this excellent speed and very high concurrency support by being based on Swoole and by making good use of its persistent memory model and coroutines.


## Installation

## Documentation

Documentation is available [here](https://github.com/AzonMedia/guzaba2-docs).

## Software using Guzaba 2

- [GuzabaPlatofm](https://github.com/AzonMedia/guzaba-platform)
- [Glog](https://github.com/AzonMedia/glog)