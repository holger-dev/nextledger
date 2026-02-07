const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
  app: path.join(__dirname, 'src', 'main.js'),
}

webpackConfig.resolve = Object.assign({}, webpackConfig.resolve || {}, {
  mainFields: ['module', 'browser', 'main'],
  alias: Object.assign({}, webpackConfig.resolve?.alias || {}, {
    '@nextcloud/router$': path.join(__dirname, 'src', 'shims', 'nextcloud-router.js'),
  }),
})

webpackConfig.module = Object.assign({}, webpackConfig.module || {}, {
  parser: Object.assign({}, webpackConfig.module?.parser || {}, {
    javascript: Object.assign({}, webpackConfig.module?.parser?.javascript || {}, {
      dynamicImportMode: 'eager',
    }),
  }),
})

webpackConfig.output = Object.assign({}, webpackConfig.output || {}, {
  path: path.resolve(__dirname, 'apps', 'nextledger', 'js'),
  filename: '[name].js',
})

webpackConfig.optimization = Object.assign({}, webpackConfig.optimization || {}, {
  splitChunks: false,
  runtimeChunk: false,
})

module.exports = webpackConfig
