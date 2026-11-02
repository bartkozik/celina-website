export interface TemplateInput {
  slug: string;
  title: string;
  dynamic: boolean;
}

export function templates( input: TemplateInput ): Record< string, string > {
  const titleLiteral = `'${ input.title
    .replaceAll( '\\', '\\\\' )
    .replaceAll( "'", "\\'" )
    .replaceAll( '\n', '\\n' )
    .replaceAll( '\r', '\\r' ) }'`;
  const metadata = {
    apiVersion: 2,
    name: `celina/${ input.slug }`,
    version: '0.1.0',
    title: input.title,
    category: 'celina',
    icon: 'layout',
    textdomain: 'celina-blocks',
    attributes: { content: { type: 'string', default: '' } },
    supports: { html: false },
    editorScript: 'file:./index.js',
    editorStyle: 'file:./index.css',
    style: 'file:./style-index.css',
    ...( input.dynamic ? { render: 'file:./render.php' } : {} ),
  };
  const edit =
    "import { RichText, useBlockProps } from '@wordpress/block-editor';\n" +
    'interface EditProps {\n' +
    '  attributes: { content: string };\n' +
    '  setAttributes: ( next: { content: string } ) => void;\n' +
    '}\n' +
    'export default function Edit( { attributes, setAttributes }: EditProps ) {\n' +
    '  return (\n' +
    '    <RichText\n' +
    '      { ...useBlockProps() }\n' +
    '      tagName="p"\n' +
    '      value={ attributes.content }\n' +
    '      onChange={ ( content: string ) => setAttributes( { content } ) }\n' +
    `      placeholder={ ${ titleLiteral } }\n` +
    '    />\n' +
    '  );\n' +
    '}\n';
  const save =
    "import { RichText, useBlockProps } from '@wordpress/block-editor';\n" +
    'interface SaveProps {\n' +
    '  attributes: { content: string };\n' +
    '}\n' +
    'export default function save( { attributes }: SaveProps ) {\n' +
    '  return (\n' +
    '    <RichText.Content\n' +
    '      { ...useBlockProps.save() }\n' +
    '      tagName="p"\n' +
    '      value={ attributes.content }\n' +
    '    />\n' +
    '  );\n' +
    '}\n';
  const index =
    "import { registerBlockType } from '@wordpress/blocks';\n" +
    "import metadata from './block.json';\n" +
    "import Edit from './edit';\n" +
    "import './editor.scss';\n" +
    "import './style.scss';\n" +
    ( input.dynamic
      ? 'registerBlockType( metadata, { edit: Edit, save: () => null } );\n'
      : "import save from './save';\nregisterBlockType( metadata, { edit: Edit, save } );\n" );
  const render =
    "<?php\ndefined( 'ABSPATH' ) || exit;\n$content = isset( $attributes['content'] ) ? sanitize_text_field( $attributes['content'] ) : '';\nif ( '' === $content ) return '';\n?>\n<div <?php echo get_block_wrapper_attributes(); ?>><?php echo esc_html( $content ); ?></div>\n";
  const files: Record< string, string > = {
    'block.json': `${ JSON.stringify( metadata, null, 2 ) }\n`,
    'index.tsx': index,
    'edit.tsx': edit,
    'editor.scss': `.wp-block-celina-${ input.slug } {\n\toutline: 1px dashed #3d4543;\n}\n`,
    'style.scss': `.wp-block-celina-${ input.slug } {\n\tcolor: var(--celina-color-text);\n}\n`,
    'index.test.ts':
      "import metadata from './block.json';\n" +
      "import { describe, expect, it } from 'vitest';\n" +
      `describe( '${ input.slug }', () => {\n` +
      "  it( 'uses CELINA namespace', () =>\n" +
      `    expect( metadata.name ).toBe( 'celina/${ input.slug }' ) );\n` +
      '} );\n',
  };

  files[ input.dynamic ? 'render.php' : 'save.tsx' ] = input.dynamic
    ? render
    : save;

  return files;
}
