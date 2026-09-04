<?php

namespace App\Domain\Strategies;

enum MethodCategory: string
{
    case Primary = 'primary';
    case Alternative = 'alternative';
}
