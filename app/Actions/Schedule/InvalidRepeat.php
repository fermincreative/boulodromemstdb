<?php

namespace App\Actions\Schedule;

use InvalidArgumentException;

/**
 * The repeat settings of an event are not possible (end date too early or too far ahead, …).
 */
class InvalidRepeat extends InvalidArgumentException {}
