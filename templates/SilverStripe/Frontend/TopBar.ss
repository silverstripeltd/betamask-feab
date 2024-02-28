<!DOCTYPE html>
<html class="betamask-html" lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <% require css("silverstripe/admin: client/dist/styles/bundle.css") %>
  <% require css("silverstripeltd/betamask-bao: client/dist/betamask-ui.css") %>
  <title>{$Title}</title>
<%--  <link rel="icon" type="image/png" href="{$Favicon}"/>--%>
</head>
<body class="betamaskui">
<div class="betamaskui__app" id="betamaskui">
  <top-bar class="betamaskui__topbar"
           logo="{$resourceURL('silverstripeltd/betamask-bao: client/dist/images/silverstripe-logo.svg')}"
           logo-url="{$LogoUrl}"
           app-name="{$AppName}"
           app-version="v{$CMSVersionNumber}"
           app-env="{$AppEnv}"
           :items="{$ItemsAsJson}"
  ></top-bar>
  <div class="betamaskui__site">
    <iframe
        class="betamaskui__frame"
        width="100%"
        height="100%"
        src="{$FrameURL}"
        loading="eager"
        referrerpolicy="no-referrer"
        sandbox="allow-scripts allow-same-origin">
    </iframe>
  </div>
</div>
<script type="module" src="{$resourceURL('silverstripeltd/betamask-bao: client/dist/betamask-ui.js')}"></script>
</body>
</html>
