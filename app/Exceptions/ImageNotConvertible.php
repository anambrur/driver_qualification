<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An uploaded image that can't become a PDF (unreadable or too many pixels). Retrying won't help.
 */
class ImageNotConvertible extends RuntimeException {}
