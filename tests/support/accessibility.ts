/**
 * Accepted contrast exception: ignore only color-contrast findings for brand orange
 * (#ff6c0d, about 2.6-2.8:1 on light backgrounds). All other findings remain checked.
 * See docs/design-feedback-rework.md.
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
