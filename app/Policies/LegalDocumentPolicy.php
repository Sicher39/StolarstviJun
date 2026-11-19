<?php

namespace App\Policies;

use App\Models\LegalDocument;
use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;

/**
 * Authorization of the legal documents, following the naming Shield generates for a resource
 * (`ViewAny:LegalDocument`, `Update:LegalDocument`, ...).
 *
 * Shield is configured with `super_admin.define_via_gate = false`, so the super admin role is not
 * intercepted by the gate and needs an explicit permission. The permission rows only exist after
 * `php artisan shield:generate --all`, therefore the role fallback keeps the resource reachable for
 * the administrator in the meantime - the same convention as the ContactSettings page uses.
 *
 * Deleting is denied on purpose: a legal text that was once published must stay reproducible.
 */
class LegalDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'ViewAny:LegalDocument');
    }

    public function view(User $user, LegalDocument $document): bool
    {
        return $this->allows($user, 'View:LegalDocument');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'Create:LegalDocument');
    }

    public function update(User $user, LegalDocument $document): bool
    {
        return $this->allows($user, 'Update:LegalDocument');
    }

    public function delete(User $user, LegalDocument $document): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, LegalDocument $document): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, LegalDocument $document): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, LegalDocument $document): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    private function allows(User $user, string $permission): bool
    {
        return $user->can($permission) || $user->hasRole(Utils::getSuperAdminName());
    }
}
