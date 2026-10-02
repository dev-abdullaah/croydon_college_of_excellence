<?php

namespace App\Exceptions;

/**
 * Raised when a Checkout Session cannot be opened because the customer
 * already has a paid purchase for that course.
 *
 * Split out from PaymentException because the two need opposite responses.
 * A PaymentException means "something is wrong, tell the customer to try
 * again"; this one means "there is nothing to pay for, send them to the
 * course they own". Folding them together would show somebody who has
 * already paid a message about payments being unavailable.
 */
class AlreadyPurchasedException extends PaymentException {}
