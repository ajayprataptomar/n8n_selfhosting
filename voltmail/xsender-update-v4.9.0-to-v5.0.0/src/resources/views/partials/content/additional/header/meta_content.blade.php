<meta name="description" content="{{ site_settings("meta_description") }}"> 
<meta name="keywords" content="{{ implode(',', (array) json_decode(site_settings('meta_keywords', '[]'), true)) }}">
<meta property="og:title" content="{{ site_settings('site_name') }}">
<meta property="og:description" content="{{ site_settings("meta_description") }}">
<meta property="og:image" content="{{showImage(config('setting.file_path.meta_image.path').'/'.site_settings('meta_image'),config('setting.file_path.meta_image.size'))}}">
<meta property="og:url" content="{{ url('/') }}">
<meta name="twitter:card" content="{{showImage(config('setting.file_path.meta_image.path').'/'.site_settings('meta_image'),config('setting.file_path.meta_image.size'))}}">
<meta name="twitter:title" content="{{ site_settings('site_name') }}">
<meta name="twitter:description" content="{{ site_settings("meta_description") }}">
<meta name="twitter:image" content="{{showImage(config('setting.file_path.meta_image.path').'/'.site_settings('meta_image'),config('setting.file_path.meta_image.size'))}}">