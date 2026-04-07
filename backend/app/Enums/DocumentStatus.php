<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Completed = 'completed';
}
