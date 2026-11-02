export type HubSpotRegion = 'na1' | 'eu1';

export interface HubSpotAttributes extends Record< string, unknown > {
  heading: string;
  region: HubSpotRegion;
  portalId: string;
  formId: string;
  fallbackText: string;
}

const decimalIdPattern = /^\d+$/;
const uuidPattern =
  /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

export function isHubSpotConfigurationValid(
  attributes: HubSpotAttributes
): boolean {
  return (
    ( attributes.region === 'na1' || attributes.region === 'eu1' ) &&
    decimalIdPattern.test( attributes.portalId ) &&
    uuidPattern.test( attributes.formId )
  );
}
