import { createHash } from 'node:crypto';
import { posix, win32 } from 'node:path';

export type IsolatedComposeProfile = 'content-e2e' | 'e2e';

export function isolatedComposeProjectName(
  workspace: string,
  platform: NodeJS.Platform,
  profile: IsolatedComposeProfile
): string {
  const path = platform === 'win32' ? win32 : posix;
  const absoluteWorkspace = path.resolve( workspace ).replaceAll( '\\', '/' );
  const identity =
    platform === 'win32' ? absoluteWorkspace.toLowerCase() : absoluteWorkspace;
  const digest = createHash( 'sha256' )
    .update( identity )
    .digest( 'hex' )
    .slice( 0, 12 );

  return `celina-${ digest }-${ profile }`;
}
