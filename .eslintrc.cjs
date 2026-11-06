module.exports = {
  extends: [ require.resolve( '@wordpress/scripts/config/.eslintrc.js' ) ],
  overrides: [
    {
      files: [ '**/*.ts', '**/*.tsx' ],
      parserOptions: { project: './tsconfig.json', tsconfigRootDir: __dirname },
      rules: {
        '@typescript-eslint/no-explicit-any': 'error',
        '@typescript-eslint/no-unsafe-assignment': 'error',
        '@typescript-eslint/no-unsafe-argument': 'error',
        '@typescript-eslint/no-unsafe-call': 'error',
        '@typescript-eslint/no-unsafe-member-access': 'error',
        '@typescript-eslint/no-unsafe-return': 'error',
      },
    },
  ],
};
