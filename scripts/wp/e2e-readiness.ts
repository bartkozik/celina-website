import { unlinkSync, writeFileSync } from 'node:fs';

export const e2eReadinessMarker = new URL(
  '../../fixtures/.celina-e2e-ready',
  import.meta.url
);

export function clearE2EReadiness(): void {
  try {
    unlinkSync( e2eReadinessMarker );
  } catch ( error ) {
    if ( ( error as NodeJS.ErrnoException ).code !== 'ENOENT' ) {
      throw error;
    }
  }
}

export function markE2EReadiness(): void {
  writeFileSync( e2eReadinessMarker, 'ready\n', 'utf8' );
}
