<?php

namespace App\Enums;

enum MediaCollection: string
{
    case PROFILE = 'profile';
    case ORGANIZATION = 'organization';

    case PROPERTY_THUMB = 'property_thumbnail';
    case PROPERTY_GALLERY = 'property_gallery';

    case UNIT_THUMB = 'unit_thumbnail';
    case UNIT_GALLERY = 'unit_gallery';
}
