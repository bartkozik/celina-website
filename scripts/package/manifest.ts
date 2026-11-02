import { createHash } from 'node:crypto';

export interface ReleaseArtifact {
  bytes: Uint8Array;
  fileName: string;
}

function compareNames( left: ReleaseArtifact, right: ReleaseArtifact ): number {
  return Buffer.from( left.fileName ).compare( Buffer.from( right.fileName ) );
}

export function createSha256Manifest( artifacts: ReleaseArtifact[] ): string {
  const names = new Set< string >();

  return (
    [ ...artifacts ]
      .sort( compareNames )
      .map( ( artifact ) => {
        if (
          ! /^[a-z0-9][a-z0-9.-]*\.zip$/.test( artifact.fileName ) ||
          names.has( artifact.fileName )
        ) {
          throw new Error(
            `Invalid or duplicate artifact name: ${ artifact.fileName }`
          );
        }
        names.add( artifact.fileName );
        const hash = createHash( 'sha256' )
          .update( artifact.bytes )
          .digest( 'hex' )
          .toLowerCase();
        return `${ hash }  ${ artifact.fileName }`;
      } )
      .join( '\n' ) + '\n'
  );
}
