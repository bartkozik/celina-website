export interface PaycorAttributes extends Record< string, unknown > {
  heading: string;
  clientId: string;
  fallbackText: string;
  jobsLinkText: string;
}

const paycorClientIdPattern = /^[a-f0-9]{32}$/i;

export function isPaycorClientIdValid( clientId: string ): boolean {
  return paycorClientIdPattern.test( clientId );
}
