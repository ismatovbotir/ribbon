<?php

namespace App\Exceptions;

use App\Models\User;
use RuntimeException;

/**
 * Thrown by {@see User::adminRoleOrFail()} when the authenticated user has
 * no admin-type role assigned via `role_user`. Unlike seller access (which
 * has a pending/approved/rejected lifecycle), an admin role assignment is
 * binary — a staff user either has one or doesn't — so there's no status to
 * carry, just the fact of denial.
 */
class AdminAccessDeniedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This user has no admin role assigned.');
    }
}
