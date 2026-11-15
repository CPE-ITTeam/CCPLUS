<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CC‑PLUS iDev</title>

    <script>
      window.ccplusConfig = {
        default_locale: @json(\App\Models\GlobalSetting::where('name', 'default_locale')->value('value') ?? 'en'),
      };
    </script>

    <!-- 
      All typography layouts, theme maps, icon vectors, and style sheets 
      compile smoothly out of this singular application entry pipeline hook.
    -->
    {{ Vite::useBuildDirectory('')->withEntryPoints(['resources/js/app.js']) }}
</head>
<body>
    <div id="app"></div>
</body>
</html>
