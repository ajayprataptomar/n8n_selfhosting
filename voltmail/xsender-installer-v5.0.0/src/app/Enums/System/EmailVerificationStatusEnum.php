<?php
  
namespace App\Enums\System;

use App\Enums\EnumTrait;
use Illuminate\Support\Arr;

enum EmailVerificationStatusEnum :String {

     use EnumTrait;

     case PENDING        = 'pending';
     case VERIFIED       = 'verified';
     case UNVERIFIED     = 'unverified';
     
     /**
      * values
      *
      * @return string
      */
     public function values(): string
     {
          return match($this) {
               self::PENDING       => 'Pending',
               self::VERIFIED      => 'Verified',
               self::UNVERIFIED    => 'Unverified',
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
               self::PENDING       => 'primary-soft',
               self::VERIFIED      => 'success-soft',
               self::UNVERIFIED    => 'danger-soft',
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