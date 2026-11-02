export type AchievementsVariant = 'facts' | 'timeline' | 'stats';

export interface AchievementsTimelineAttributes
  extends Record< string, unknown > {
  eyebrow: string;
  heading: string;
  introduction: string;
  variant: AchievementsVariant;
}

export const ALLOWED_ACHIEVEMENT_BLOCKS: readonly string[] = [
  'celina/achievement',
];

export const ACHIEVEMENT_TEMPLATE: ReadonlyArray<
  readonly [ string, Record< string, never > ]
> = [
  [ 'celina/achievement', {} ],
  [ 'celina/achievement', {} ],
  [ 'celina/achievement', {} ],
];

export function normalizeAchievementsVariant(
  value: unknown
): AchievementsVariant {
  return value === 'timeline' || value === 'stats' ? value : 'facts';
}
