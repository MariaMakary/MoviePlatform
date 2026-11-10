# packages/common

Technical code shared by the 6 Lumen services and the Gateway. Read `README.md` in this folder first: it lists what
is here and how projects load it.

- **Ask the user before changing anything here.** One change affects all 7 projects.
- Only technical code (helpers, middleware, event plumbing, health check). Never business logic, models or database
  access (golden rule 9).
- It runs in **Lumen 10 and Laravel 12**: only use features that exist in both Illuminate 10 and 12. When the two
  frameworks differ (registering middleware, loading config), detect Lumen in `CommonServiceProvider` the way the
  `jwt` middleware does.
- After a change, run **both** test suites: `composer test` here (Laravel 12) and `vendor/bin/phpunit` in a Lumen
  service that uses the change (e.g. Identity).
- No comments or docblocks in the code.
