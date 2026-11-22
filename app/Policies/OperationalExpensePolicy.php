<?php

namespace App\Policies;

use App\Models\OperationalExpense;
use App\Models\User;

class OperationalExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasRole(User::ROLE_KEUANGAN, User::ROLE_OWNER);
    }

    public function view(User $user, OperationalExpense $expense): bool
    {
        return $user->is_active && $user->hasRole(User::ROLE_KEUANGAN, User::ROLE_OWNER);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->hasRole(User::ROLE_KEUANGAN);
    }

    public function update(User $user, OperationalExpense $expense): bool
    {
        return $user->is_active && $user->hasRole(User::ROLE_KEUANGAN);
    }

    public function delete(User $user, OperationalExpense $expense): bool
    {
        return false;
    }
}
