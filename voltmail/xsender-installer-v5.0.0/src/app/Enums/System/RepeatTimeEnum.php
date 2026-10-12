<?php

namespace App\Enums\System;

use App\Enums\EnumTrait;

enum RepeatTimeEnum: string
{
     use EnumTrait;

     case NONE      = 'none';
     case DAILY     = 'daily';
     case WEEKLY    = 'weekly';
     case MONTHLY   = 'monthly';
     // case QUARTERLY = 'quarterly';
     case YEARLY    = 'yearly';

     /**
      * values
      *
      * @return string
      */
     public function values(): string
     {
          return match($this) {
               self::NONE        => 'None',
               self::DAILY       => 'Daily',
               self::WEEKLY      => 'Weekly',
               self::MONTHLY     => 'Monthly',
               // self::QUARTERLY   => 'Quarterly',
               self::YEARLY      => 'Yearly',
          };
     }

     /**
      * badge
      *
      * @return void
      */
     /**
      * badge
      *
      * @return void
      */
     public function badge(): void
     {
          $color = match($this) {
               self::NONE          => 'secondary',
               self::DAILY         => 'info',
               self::WEEKLY        => 'success',
               self::MONTHLY       => 'primary',
               // self::QUARTERLY     => 'warning',
               self::YEARLY        => 'danger',
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