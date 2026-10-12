<?php

namespace App\Enums\System;

use App\Enums\EnumTrait;

enum TemplateProviderEnum: string
{
     use EnumTrait;

     case SYSTEM      = 'system';
     case BEE_FREE    = 'bee_free';
     case CK_EDITOR   = 'ck_editor';

     /**
      * values
      *
      * @return string
      */
     public function values(): string
     {
          return match($this) {
               self::SYSTEM     => 'System',
               self::BEE_FREE   => 'Bee Free',
               self::CK_EDITOR  => 'Ck Editor',
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
               self::SYSTEM      => 'info-soft',
               self::BEE_FREE    => 'warning-soft',
               self::CK_EDITOR   => 'success-soft',
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