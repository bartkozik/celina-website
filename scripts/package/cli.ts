import { access, mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { runPnpm, type Runner } from '../wp/commands.ts';
import { createDeterministicArchive } from './archive.ts';
import { createSha256Manifest, type ReleaseArtifact } from './manifest.ts';

export interface PackageOperations {
  root?: string;
  run?: Runner;
  writeSuccess?: ( message: string ) => void;
}

export async function createReleasePackages(
  operations: PackageOperations = {}
): Promise< void > {
  const root = resolve( operations.root ?? process.cwd() );
  const output = resolve( root, 'dist' );
  const pluginRoot = resolve( root, 'wordpress/plugins/celina-blocks' );
  const themeRoot = resolve( root, 'wordpress/themes/celina-child' );
  const run = operations.run ?? runPnpm;

  run( [ 'build' ] );
  await access( resolve( pluginRoot, 'build/blocks' ) );
  await mkdir( output, { recursive: true } );

  const artifacts: ReleaseArtifact[] = [
    {
      bytes: await createDeterministicArchive( {
        includes: [ 'blocks.json', 'build', 'celina-blocks.php', 'includes' ],
        root: pluginRoot,
        topLevel: 'celina-blocks',
      } ),
      fileName: 'celina-blocks.zip',
    },
    {
      bytes: await createDeterministicArchive( {
        includes: [ '.' ],
        root: themeRoot,
        topLevel: 'celina-child',
      } ),
      fileName: 'celina-child.zip',
    },
  ];

  for ( const artifact of artifacts ) {
    await writeFile( resolve( output, artifact.fileName ), artifact.bytes );
  }
  const manifest = createSha256Manifest( artifacts );
  await writeFile( resolve( output, 'SHA256SUMS' ), manifest, 'utf8' );

  ( operations.writeSuccess ?? process.stdout.write.bind( process.stdout ) )(
    `Created deterministic release packages in ${ output }\n${ manifest }`
  );
}

if (
  process.argv[ 1 ] &&
  resolve( process.argv[ 1 ] ) === fileURLToPath( import.meta.url )
) {
  await createReleasePackages();
}
