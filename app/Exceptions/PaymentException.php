<?php

namespace App\Exceptions;

use Exception;

/**
 * Raised for any problem that prevents a payment from being created or
 * confirmed. Controllers catch it and show the user a friendly message
 * while the real cause is written to the log.
 */
class PaymentException extends Exception {}
