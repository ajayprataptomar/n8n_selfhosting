<?php
  
namespace App\Enums\System;

use App\Enums\EnumTrait;
use Illuminate\Support\Arr;

enum ContactImportStatusEnum :String {

     use EnumTrait;

     case PENDING    = 'pending';
     case PROCESSING = 'processing';
     case COMPLETED  = 'completed';
     case FAILED     = 'failed';
     
     /**
      * values
      *
      * @return string
      */
     public function values(): string
     {
          return match($this) {
               self::PENDING       => 'Pending',
               self::PROCESSING    => 'Processing',
               self::COMPLETED     => 'completed',
               self::FAILED        => 'failed',
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
               self::PENDING     => 'primary-soft',
               self::PROCESSING  => 'warning-soft',
               self::COMPLETED   => 'success-soft',
               self::FAILED      => 'danger-soft',
          };

          echo "<span class='i-badge dot pill {$color}'>{$this->values()}</span>";
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