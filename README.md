# WP API Plugin

For more information and documentation, please visit:

[https://mspecs.github.io/wp-api-plugin/](https://mspecs.github.io/wp-api-plugin/)


## Compatibility

- **WordPress:** Tested and supported on **WordPress 6.9**
- **PHP:** Tested with **PHP 8.2.23**

> ⚠️ Outside of the versions listed above, we do **not** provide any stability or compatibility guarantees.

## Tests

Run `php tests/run.php` with PHP 8.2. The regression tests exercise sold-deal
pagination, sync queuing and file cleanup with offline HTTP, WordPress storage
and media substitutes. Isolated tests use the real settings hooks and background
queue classes to verify resync ordering, and sync 20,000 sold objects under a
128 MB memory limit. No API credentials or WordPress database are required.
