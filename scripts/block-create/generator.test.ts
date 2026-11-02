import {
  mkdtemp,
  mkdir,
  readFile,
  readdir,
  realpath,
  rename,
  rm,
  symlink,
  writeFile,
} from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { afterEach, describe, expect, it } from 'vitest';
import { generateBlock } from './generator.ts';

const roots: string[] = [];

function registry( slugs: string[] ): string {
  return `${ JSON.stringify( slugs, null, 2 ) }\n`;
}

function deferred(): { promise: Promise< void >; resolve: () => void } {
  let resolve = () => {};
  const promise = new Promise< void >( ( done ) => {
    resolve = done;
  } );
  return { promise, resolve };
}

async function project( parents = true ): Promise< string > {
  const root = await mkdtemp( join( tmpdir(), 'celina-block-' ) );
  roots.push( root );
  if ( parents ) {
    await mkdir( join( root, 'src/blocks' ), { recursive: true } );
  }
  await writeFile( join( root, 'blocks.json' ), '[]\n' );
  return root;
}

async function expectNoArtifacts( root: string ): Promise< void > {
  const rootArtifacts = ( await readdir( root ) ).filter(
    ( name ) =>
      name.startsWith( '.celina-block-create' ) ||
      name.startsWith( '.blocks.json.' )
  );
  expect( rootArtifacts ).toEqual( [] );
  const blockArtifacts = ( await readdir( join( root, 'src/blocks' ) ) ).filter(
    ( name ) => name.startsWith( '.' )
  );
  expect( blockArtifacts ).toEqual( [] );
}

afterEach( async () => {
  await Promise.all(
    roots
      .splice( 0 )
      .map( ( root ) => rm( root, { recursive: true, force: true } ) )
  );
} );

describe( 'generateBlock', () => {
  it( 'creates the exact static file set and a sorted registry', async () => {
    const root = await project();

    await generateBlock( {
      root,
      slug: 'quote-card',
      title: 'Quote Card',
      dynamic: false,
    } );
    await generateBlock( {
      root,
      slug: 'alert',
      title: 'Alert',
      dynamic: false,
    } );

    await expect(
      readFile( join( root, 'blocks.json' ), 'utf8' )
    ).resolves.toBe( registry( [ 'alert', 'quote-card' ] ) );
    await expect(
      readdir( join( root, 'src/blocks/quote-card' ) ).then( ( names ) =>
        names.sort()
      )
    ).resolves.toEqual( [
      'block.json',
      'edit.tsx',
      'editor.scss',
      'index.test.ts',
      'index.tsx',
      'save.tsx',
      'style.scss',
    ] );
    const metadata = JSON.parse(
      await readFile( join( root, 'src/blocks/quote-card/block.json' ), 'utf8' )
    ) as Record< string, unknown >;
    expect( metadata ).toMatchObject( {
      apiVersion: 2,
      name: 'celina/quote-card',
      title: 'Quote Card',
      category: 'celina',
      editorScript: 'file:./index.js',
      style: 'file:./style-index.css',
    } );
    expect( metadata ).not.toHaveProperty( 'render' );
    await expect(
      readFile( join( root, 'src/blocks/quote-card/save.tsx' ), 'utf8' )
    ).resolves.toContain( 'RichText.Content' );
    await expectNoArtifacts( root );
  } );

  it( 'creates the exact dynamic file set without a static save file', async () => {
    const root = await project();

    await generateBlock( {
      root,
      slug: 'latest-items',
      title: 'Latest Items',
      dynamic: true,
    } );

    await expect(
      readdir( join( root, 'src/blocks/latest-items' ) ).then( ( names ) =>
        names.sort()
      )
    ).resolves.toEqual( [
      'block.json',
      'edit.tsx',
      'editor.scss',
      'index.test.ts',
      'index.tsx',
      'render.php',
      'style.scss',
    ] );
    const metadata = JSON.parse(
      await readFile(
        join( root, 'src/blocks/latest-items/block.json' ),
        'utf8'
      )
    ) as Record< string, unknown >;
    expect( metadata.render ).toBe( 'file:./render.php' );
    await expect(
      readFile( join( root, 'src/blocks/latest-items/render.php' ), 'utf8' )
    ).resolves.toContain( 'get_block_wrapper_attributes' );
    await expect(
      readFile( join( root, 'src/blocks/latest-items/save.tsx' ), 'utf8' )
    ).rejects.toMatchObject( { code: 'ENOENT' } );
    await expectNoArtifacts( root );
  } );

  it( 'escapes a hostile title without changing block metadata', async () => {
    const root = await project();
    const title = "Reader's C:\\Guides\nNext\rLine";

    await generateBlock( {
      root,
      slug: 'escaped-title',
      title,
      dynamic: false,
    } );

    const metadata = JSON.parse(
      await readFile(
        join( root, 'src/blocks/escaped-title/block.json' ),
        'utf8'
      )
    ) as { title: string };
    expect( metadata.title ).toBe( title );
    await expect(
      readFile( join( root, 'src/blocks/escaped-title/edit.tsx' ), 'utf8' )
    ).resolves.toContain(
      "placeholder={ 'Reader\\'s C:\\\\Guides\\nNext\\rLine' }"
    );
  } );

  it( 'creates missing source parents for an empty plugin scaffold', async () => {
    const root = await project( false );

    await generateBlock( {
      root,
      slug: 'first-block',
      title: 'First Block',
      dynamic: false,
    } );

    await expect(
      readFile( join( root, 'src/blocks/first-block/block.json' ), 'utf8' )
    ).resolves.toContain( 'celina/first-block' );
    await expectNoArtifacts( root );
  } );

  it( 'creates stylesheet indentation accepted by the WordPress lint rules', async () => {
    const root = await project();

    await generateBlock( {
      root,
      slug: 'lint-ready',
      title: 'Lint Ready',
      dynamic: false,
    } );

    await expect(
      readFile( join( root, 'src/blocks/lint-ready/editor.scss' ), 'utf8' )
    ).resolves.toContain( '\n\toutline:' );
    await expect(
      readFile( join( root, 'src/blocks/lint-ready/style.scss' ), 'utf8' )
    ).resolves.toContain( '\n\tcolor:' );
  } );

  it.each( [
    { slug: 'Hero', title: 'Hero', message: 'invalid block slug' },
    { slug: 'two words', title: 'Two Words', message: 'invalid block slug' },
    { slug: '../escape', title: 'Escape', message: 'invalid block slug' },
    { slug: 'core', title: 'Core', message: 'invalid block slug' },
    { slug: 'valid-slug', title: '   ', message: 'block title is required' },
  ] )(
    'rejects invalid input $slug without touching the project',
    async ( { slug, title, message } ) => {
      const root = await project();

      await expect(
        generateBlock( { root, slug, title, dynamic: false } )
      ).rejects.toThrow( message );

      await expect(
        readFile( join( root, 'blocks.json' ), 'utf8' )
      ).resolves.toBe( '[]\n' );
      await expect( readdir( join( root, 'src/blocks' ) ) ).resolves.toEqual(
        []
      );
      await expectNoArtifacts( root );
    }
  );

  it( 'rejects a collision without modifying existing files or registry', async () => {
    const root = await project();
    const input = {
      root,
      slug: 'notice',
      title: 'Notice',
      dynamic: false,
    };
    await generateBlock( input );
    const registryBefore = await readFile(
      join( root, 'blocks.json' ),
      'utf8'
    );
    const savePath = join( root, 'src/blocks/notice/save.tsx' );
    const saveBefore = await readFile( savePath, 'utf8' );

    await expect( generateBlock( input ) ).rejects.toThrow( 'already exists' );

    await expect(
      readFile( join( root, 'blocks.json' ), 'utf8' )
    ).resolves.toBe( registryBefore );
    await expect( readFile( savePath, 'utf8' ) ).resolves.toBe( saveBefore );
    await expectNoArtifacts( root );
  } );

  it.each( [ 'missing', 'invalid-json', 'wrong-shape' ] )(
    'creates no parents when the registry is %s',
    async ( state ) => {
      const root = await project( false );
      if ( state === 'missing' ) {
        await rm( join( root, 'blocks.json' ) );
      } else if ( state === 'invalid-json' ) {
        await writeFile( join( root, 'blocks.json' ), '{ not JSON' );
      } else {
        await writeFile( join( root, 'blocks.json' ), '{}\n' );
      }

      await expect(
        generateBlock( {
          root,
          slug: 'unwritten',
          title: 'Unwritten',
          dynamic: false,
        } )
      ).rejects.toThrow();

      await expect( readdir( join( root, 'src' ) ) ).rejects.toMatchObject( {
        code: 'ENOENT',
      } );
    }
  );

  it( 'rejects a source symlink that resolves outside the plugin root', async () => {
    const sandbox = await mkdtemp( join( tmpdir(), 'celina-block-' ) );
    roots.push( sandbox );
    const root = join( sandbox, 'plugin' );
    const outside = join( sandbox, 'outside' );
    await mkdir( root );
    await mkdir( outside );
    await writeFile( join( root, 'blocks.json' ), '[]\n' );
    await symlink( outside, join( root, 'src' ) );

    await expect(
      generateBlock( {
        root,
        slug: 'outside-write',
        title: 'Outside Write',
        dynamic: false,
      } )
    ).rejects.toThrow( 'outside plugin root' );

    await expect( readdir( outside ) ).resolves.toEqual( [] );
    await expect(
      readFile( join( root, 'blocks.json' ), 'utf8' )
    ).resolves.toBe( '[]\n' );
  } );

  it( 'serializes same-process generators before either reads stale registry state', async () => {
    const root = await project();
    const registryPath = await realpath( join( root, 'blocks.json' ) );
    const firstReachedRegistry = deferred();
    const releaseFirst = deferred();
    const secondContended = deferred();

    const first = generateBlock(
      { root, slug: 'alpha', title: 'Alpha', dynamic: false },
      {
        rename: async ( source, destination ) => {
          if ( destination === registryPath ) {
            firstReachedRegistry.resolve();
            await releaseFirst.promise;
          }
          await rename( source, destination );
        },
      }
    );
    await firstReachedRegistry.promise;
    const second = generateBlock(
      { root, slug: 'beta', title: 'Beta', dynamic: false },
      { onLockContention: secondContended.resolve }
    );

    await secondContended.promise;
    releaseFirst.resolve();
    await Promise.all( [ first, second ] );

    await expect( readFile( registryPath, 'utf8' ) ).resolves.toBe(
      registry( [ 'alpha', 'beta' ] )
    );
    await expect( readdir( join( root, 'src/blocks' ) ) ).resolves.toEqual( [
      'alpha',
      'beta',
    ] );
    await expectNoArtifacts( root );
  } );
} );
