<?php

namespace App\Policies;

use App\Models\DeliveryOrder;
use App\Models\User;

class DeliveryOrderPolicy
{
    public function view(User $user, DeliveryOrder $deliveryOrder): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isClient()) {
            return $user->client_company_id !== null
                && (int) $user->client_company_id === (int) $deliveryOrder->client_company_id;
        }

        return $user->hasRole(
            User::ROLE_KEPALA_GUDANG,
            User::ROLE_KEUANGAN,
            User::ROLE_OWNER,
        );
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->hasRole(User::ROLE_KEPALA_GUDANG);
    }

    public function delete(User $user, DeliveryOrder $deliveryOrder): bool
    {
        return false;
    }
}
