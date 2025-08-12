<?php

namespace App\Enums;

enum MedicineTimeEnum: string
{
    case BEFORE_EATING      = 'before_eating';
    case AFTER_EATING       = 'after_eating';
    case ON_EMPTY_STOMACH   = 'on_empty_stomach';
    case WITH_FOOD          = 'with_food';        
    case SLEEP_TIME         = 'sleep_time';
    case GET_UP_TIME        = 'get_up_time';
    case AS_NEEDED          = 'as_needed';        
    
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
