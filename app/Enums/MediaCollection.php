<?php

namespace App\Enums;

enum MediaCollection: string
{
    case PROFILE = 'profile';
    case ORGANIZATION = 'organization';
    case PROPERTY = 'property';
    case UNIT = 'unit';
}
