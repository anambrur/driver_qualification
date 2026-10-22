<?php

namespace App\Policies;

/**
 * A document's subject is its folder's binding (F18), so the same rule applies.
 */
class DocumentPolicy extends FolderPolicy {}
