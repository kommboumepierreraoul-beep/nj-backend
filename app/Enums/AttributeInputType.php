<?php

namespace App\Enums;

enum AttributeInputType: string
{
    case TEXT = 'TEXT';
    case NUMBER = 'NUMBER';
    case SELECT = 'SELECT';
    case BOOLEAN = 'BOOLEAN';
    case COLOR = 'COLOR';
}
