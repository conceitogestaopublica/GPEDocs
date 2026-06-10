<?php

namespace App\Services\Landlord;


use App\Jobs\TenantAwareJob;

class MassInsertUserService extends TenantAwareJob
{
    public function __construct() {
        parent::__construct();
    }
}
