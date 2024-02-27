/**
 * The build is run with laravel-mix, see docs here: https://laravel-mix.com
 */
/* eslint-disable import/no-extraneous-dependencies */
const mix = require("laravel-mix");
const webpack = require("webpack");
const { resolve } = require('path');
const globImporter = require('node-sass-glob-importer');

// parse environment variables from .env
require("dotenv").config();

// define paths
const srcFolder = `./client/src`;
const distFolder = `./client/dist`;
const publicFolder = `/_resources/vendor/silverstripeltd/betamask-bao/client/dist`; // TODO remove hardcode

const sassOptions = {
  sassOptions: {
    // Allow SCSS import wildcards
    importer: globImporter(),
    // Include cache-buster on urls
    processCssUrls: true,
  }
};

// Do the mix!
mix
  .vue({ version: 3 });

// Custom CMS 5 refresh
mix
  .js(`${srcFolder}/js/betamask-ui.js`, "/")
  .js(`${srcFolder}/js/betamask-inline.js`, "/")
  .sass(`${srcFolder}/scss/betamask-ui.scss`, "/", sassOptions);

mix.copyDirectory(`${srcFolder}/images`, `${distFolder}/images`);
mix.copyDirectory(`${srcFolder}/icons`, `${distFolder}/icons`);

// Places images processed in scss into dist folder
mix.setPublicPath(distFolder);
mix.setResourceRoot(publicFolder); // Prefixes urls in processed css with _resources/dist

mix.alias = {
  'vue': resolve(`./node_modules/vue`),
};

/**
 * Setup vue correctly
 */
mix.webpackConfig({
  plugins: [
    new webpack.DefinePlugin({
      __VUE_OPTIONS_API__: true,
      __VUE_PROD_DEVTOOLS__: false,
    }),
  ],
});



/**
 * Development specific
 */
if (process.env.NODE_ENV === "development") {
  // Add style lint
  // eslint-disable-next-line global-require
  const StyleLintPlugin = require("stylelint-webpack-plugin");
  mix.webpackConfig({
    plugins: [
      new StyleLintPlugin({
        context: srcFolder,
        files: ["**/*.{scss,vue}"],
      }),
    ],
  });

  // Add eslint
  // eslint-disable-next-line global-require
  const ESLintPlugin = require('eslint-webpack-plugin');
  mix.webpackConfig({
    plugins: [
      new ESLintPlugin({
        context: srcFolder,
        files: ["**/*.{js,vue}"],
      }),
    ],
  });

  // This allows you to proxy your site while watching, meaning when you change
  // your css/scss the file will get injected rather than requiring a reload
  if (process.env.MIX_BROWSERSYNC === 'true') {
    mix.browserSync({
      proxy: process.env.HOSTNAME,
      files: [`${distFolder}/**.*`],
    });
  }

  // Add sourcemaps in depending on the scenario you might want to
  // use these in prod too if the unminified code is fine to share
  // as it can make solving bugs easier
  mix.sourceMaps();
  mix.webpackConfig({ devtool: "inline-source-map" });
}
