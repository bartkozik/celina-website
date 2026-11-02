export interface CapabilityCardAttributes extends Record< string, unknown > {
  heading: string;
  content: string;
  proof: string;
}

export function hasCapabilityCardContent(
  attributes: CapabilityCardAttributes
): boolean {
  return [ attributes.heading, attributes.content, attributes.proof ].some(
    ( value ) => value.trim() !== ''
  );
}
