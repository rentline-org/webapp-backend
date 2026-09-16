<?php

namespace App\Enums;

enum DocumentLifecycle: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case SUPERSEDED = 'superseded';
    case ARCHIVED = 'archived';
}
