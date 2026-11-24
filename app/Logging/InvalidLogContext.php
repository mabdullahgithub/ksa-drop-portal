<?php

namespace App\Logging;

use LogicException;

/** Thrown in local/testing when an event is emitted with context its spec does not allow. */
class InvalidLogContext extends LogicException {}
