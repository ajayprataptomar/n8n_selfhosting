<?php

namespace App\Enums\System;

use App\Enums\EnumTrait;

enum PriorityEnum: string
{
     use EnumTrait;

     case LOW       = 'low';
     case MEDIUM    = 'medium';
     case HIGH      = 'high';

     /**
      * values
      *
      * @return string
      */
     public function values(): string
     {
          return match($this) {
               self::LOW      => 'Low',
               self::MEDIUM   => 'Medium',
               self::HIGH     => 'High',
          };
     }

     /**
      * badge
      *
      * @return void
      */
     public function badge(): void
     {
         $color = match($this) {
             self::LOW        => 'info',
             self::MEDIUM     => 'success',
             self::HIGH       => 'danger',
         };
 
         echo "<span class='i-badge {$color}'>{$this->values()}</span>";
     }

     /**
      * getValues
      *
      * @return array
      */
     public static function getValues(): array
     {
          return array_map(fn($case) => $case->value, self::cases());
     }
}