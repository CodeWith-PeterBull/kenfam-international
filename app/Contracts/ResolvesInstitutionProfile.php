<?php

namespace App\Contracts;

use App\Data\InstitutionProfileData;

interface ResolvesInstitutionProfile
{
    public function current(): InstitutionProfileData;

    public function forget(): void;
}
