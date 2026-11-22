<?php

namespace App\Policies;

use App\Models\ServiceOrder;
use App\Models\User;

class ServiceOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && in_array($user->role, User::ROLES, true);
    }

    public function view(User $user, ServiceOrder $order): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isClient()) {
            return $user->client_company_id !== null
                && (int) $user->client_company_id === (int) $order->client_company_id;
        }

        return $user->hasRole(
            User::ROLE_KEPALA_GUDANG,
            User::ROLE_KEUANGAN,
            User::ROLE_OWNER,
        );
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->isClient() && $user->client_company_id !== null;
    }

    public function inspectGate(User $user, ServiceOrder $order): bool
    {
        return false;
    }

    public function manageWarehouse(User $user, ServiceOrder $order): bool
    {
        return $user->is_active && $user->hasRole(User::ROLE_KEPALA_GUDANG);
    }

    public function viewAttachments(User $user, ServiceOrder $order): bool
    {
        return $this->view($user, $order);
    }

    public function issueDeliveryOrder(User $user, ServiceOrder $order): bool
    {
        return $user->is_active
            && $user->hasRole(User::ROLE_KEPALA_GUDANG);
    }

    public function manageBilling(User $user, ServiceOrder $order): bool
    {
        return $user->is_active && $user->hasRole(User::ROLE_KEUANGAN);
    }

    public function delete(User $user, ServiceOrder $order): bool
    {
        return false;
    }
}
