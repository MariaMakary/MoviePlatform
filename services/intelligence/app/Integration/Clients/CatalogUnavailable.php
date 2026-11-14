<?php

namespace App\Integration\Clients;

use RuntimeException;

/** Catalog did not answer: thrown so the event is retried, then dead-lettered. */
class CatalogUnavailable extends RuntimeException
{
}
