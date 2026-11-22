<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->hasRole(
            User::ROLE_CLIENT,
            User::ROLE_KEUANGAN,
            User::ROLE_OWNER,
        );
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isClient()) {
            return $user->client_company_id !== null
                && (int) $user->client_company_id === (int) $invoice->client_company_id;
        }

        return $user->hasRole(User::ROLE_KEUANGAN, User::ROLE_OWNER);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->hasRole(User::ROLE_KEUANGAN);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->is_active && $user->hasRole(User::ROLE_KEUANGAN);
    }

    public function submitPaymentProof(User $user, Invoice $invoice): bool
    {
        return $user->is_active
            && $user->isClient()
            && $user->client_company_id !== null
            && (int) $user->client_company_id === (int) $invoice->client_company_id;
    }

    public function verifyPayment(User $user, Invoice $invoice): bool
    {
        return $user->is_active && $user->hasRole(User::ROLE_KEUANGAN);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return false;
    }
}
