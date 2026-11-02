/**
 * CELINA accepted one documented exception on 2026-09-23: the brand citrus
 * (#ff6c0d) is the only orange used across the site, including small uppercase
 * section eyebrows on light grounds, where it measures about 2.6-2.8:1 instead
 * of the 4.5:1 WCAG AA asks for. See docs/design-feedback-rework.md.
 *
 * The exception is deliberately narrow: only `color-contrast` findings whose
 * foreground is exactly that brand colour are dropped. Every other serious or
 * critical finding - including any other contrast pair - still fails the suite.
 */
const ACCEPTED_FOREGROUND = '#ff6c0d';

interface AxeCheck {
  id?: string;
  data?: { fgColor?: string } | null;
}

interface AxeNode {
  any?: AxeCheck[];
  all?: AxeCheck[];
  none?: AxeCheck[];
}

interface AxeViolation {
  id?: string;
  impact?: string | null;
  nodes: AxeNode[];
}

interface AxeAnalysis {
  violations: AxeViolation[];
}

function isAcceptedBrandContrast( node: AxeNode ): boolean {
  const checks = [
    ...( node.any ?? [] ),
    ...( node.all ?? [] ),
    ...( node.none ?? [] ),
  ];

  return checks.some(
    ( check ) =>
      check.id === 'color-contrast' &&
      check.data?.fgColor?.toLowerCase() === ACCEPTED_FOREGROUND
  );
}

export function severeAxeViolations< T extends AxeAnalysis >(
  results: T
): T[ 'violations' ] {
  return results.violations
    .filter( ( violation ) =>
      [ 'critical', 'serious' ].includes( violation.impact ?? '' )
    )
    .map( ( violation ) =>
      violation.id === 'color-contrast'
        ? {
            ...violation,
            nodes: violation.nodes.filter(
              ( node ) => ! isAcceptedBrandContrast( node )
            ),
          }
        : violation
    )
    .filter( ( violation ) => violation.nodes.length > 0 );
}
