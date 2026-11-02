import {
  isSafeLinkUrl,
  normalizeLink,
  type LinkAttributes,
} from '../../shared/link';

export { isSafeLinkUrl };

export interface CtaBandAttributes extends Record< string, unknown > {
  eyebrow: string;
  heading: string;
  description: string;
  primaryLabel: string;
  primaryUrl: string;
  primaryNewTab: boolean;
  secondaryLabel: string;
  secondaryUrl: string;
  secondaryNewTab: boolean;
}

export interface CtaLink extends LinkAttributes {
  variant: 'primary' | 'secondary';
}

export function getRenderableCtaLinks(
  attributes: CtaBandAttributes
): CtaLink[] {
  const primary = normalizeLink(
    attributes.primaryLabel,
    attributes.primaryUrl,
    attributes.primaryNewTab
  );
  const secondary = normalizeLink(
    attributes.secondaryLabel,
    attributes.secondaryUrl,
    attributes.secondaryNewTab
  );

  return [
    ...( primary ? [ { ...primary, variant: 'primary' as const } ] : [] ),
    ...( secondary ? [ { ...secondary, variant: 'secondary' as const } ] : [] ),
  ];
}
