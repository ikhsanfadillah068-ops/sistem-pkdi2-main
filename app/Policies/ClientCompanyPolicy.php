<?php

namespace App\Policies;

use App\Models\ClientCompany;
use App\Models\User;

class ClientCompanyPolicy
{
    public function view(User $user, ClientCompany $company): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isClient()) {
            return $user->client_company_id !== null
                && (int) $user->client_company_id === (int) $company->id;
        }

        return true;
    }

    public function update(User $user, ClientCompany $company): bool
    {
        return $user->is_active
            && $user->isClient()
            && $user->client_company_id !== null
            && (int) $user->client_company_id === (int) $company->id;
    }
}
