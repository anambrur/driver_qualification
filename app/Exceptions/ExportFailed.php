<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An export that can't be built as asked (too large); its message is shown to the user.
 */
class ExportFailed extends RuntimeException {}
