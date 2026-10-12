<?php

return [
     'default_theme' => 'default',
     
     'paths' => [
          'views'          => 'frontend.themes',
          'assets'         => 'themes',
          'screenshots'    => 'file/theme',
     ],
     
     'required_files' => [
          'layouts/main.blade.php',
          'pages/home.blade.php',
     ],
     
     'available_themes' => [
          'default' => [
               'slug' => 'default',
               'name' => 'Default Theme',
               'description' => 'Clean and professional design with modern aesthetics and responsive layout',
               'features' => [
                    'Responsive Design',
                    'Modern Components'
               ],
               'screenshots' => [
                    'default-theme-snapshot-1.png',
                    'default-theme-snapshot-2.png',
                    'default-theme-snapshot-3.png',
                    'default-theme-snapshot-4.png'
               ],
               'version' => '1.0.0',
               'status' => 'active'
          ],
          'denim' => [
               'slug' => 'denim',
               'name' => 'Theme Denim',
               'description' => 'A marketing solution theme with responsive design, modern components, RTL support, and dark mode, optimized for versatile use.',
               'features' => [
                    'Responsive Design',
                    'Modern Components',
                    'RTL Support',
                    'Dark Mode support'
               ],
               'screenshots' => [
                    'theme-denim-snapshot-1.png',
                    'theme-denim-snapshot-2.png',
                    'theme-denim-snapshot-3.png',
                    'theme-denim-snapshot-4.png',
                    'theme-denim-snapshot-5.png'
               ],
               'version' => '1.0.0',
               'status' => 'active'
          ],
     ],
     
     'screenshot_settings' => [
          'default_image'     => 'default-theme-snapshot-1.png',
          'image_extensions'  => ['jpg', 'jpeg', 'png', 'webp'],
     ]
];