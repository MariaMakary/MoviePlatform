# movieplatform/common

Code shared by the Gateway and every service. Each project loads it as a local Composer package:

```json
"repositories": [{ "type": "path", "url": "../../packages/common" }],
"require": { "movieplatform/common": "^1.0" }
```

(The Gateway uses `../packages/common`.) Composer links the folder into `vendor/movieplatform/common`, so changes here are picked up by every project without copying files.

## Contents

| | |
|---|---|
| `src/secret.php` | `secret('name', $default)`: trimmed content of `/run/secrets/<name>`, or `env('NAME')` when the file is missing or empty. Only call it from `config/*.php`. |

## Tests

```bash
cd packages/common
composer install
composer test
```

Changing something here affects every project: run the tests of the services that use it before opening the PR.
