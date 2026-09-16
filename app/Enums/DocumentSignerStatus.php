<?php

namespace App\Enums;

enum DocumentSignerStatus: string
{
    case PENDING = 'pending';
    case SIGNED = 'signed';
    case DECLINED = 'declined';
    case WAIVED = 'waived';
}
