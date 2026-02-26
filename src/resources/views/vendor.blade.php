 <!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
     <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ config('app.name') }}</title>
  @vite('resources/ts/app.ts')

    <!-- Font Awesome 6 CDN -->
      <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
        crossorigin="anonymous"
        referrerpolicy="no-referrer"
      />

    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Vendor portal for ISC Multivendor API">
    <meta name="author" content="ISC Multivendor API">
    <meta name="keywords" content="vendor, portal, ISC, multivendor, API">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="theme-color" content="#ffffff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="icon" href="https://admin.avinaq.com/logo.jpg" type="image/x-icon">

  
  </head>
  <body class="antialiased">
    <div id="app"></div>
  </body>
</html>
