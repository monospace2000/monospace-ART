// rollup.config.js
export default {
  input: 'js/main.js',       // entry point
  output: {
    file: 'dist/dsl.bundle.js', // output bundle
    format: 'es',             // immediately-invoked function for browsers
    sourcemap: true             // generate source map
  }
};
