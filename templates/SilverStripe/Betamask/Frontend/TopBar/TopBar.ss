<!DOCTYPE html>
<html class="betamask-html" lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex">
  <link rel="stylesheet" href="{$resourceURL('silverstripe/admin: client/dist/styles/bundle.css')}">
  <link rel="stylesheet" href="{$resourceURL('silverstripeltd/betamask-bao: client/dist/betamask-ui.css')}">
  <title>{$Title}</title>
</head>
<body class="betamaskui">
<div class="betamaskui__app" id="betamaskui-topbar" data-config="{$ConfigJson}"></div>
<script type="module" src="{$resourceURL('silverstripeltd/betamask-bao: client/dist/betamask-ui.js')}"></script>
</body>
</html>
