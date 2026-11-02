export type AchievementIcon =
  | ''
  | 'globe'
  | 'flag'
  | 'manufacturing'
  | 'quality'
  | 'experience';

export const ACHIEVEMENT_ICONS: readonly {
  label: string;
  value: AchievementIcon;
}[] = [
  { label: 'No icon', value: '' },
  { label: 'Globe (worldwide)', value: 'globe' },
  { label: 'US flag', value: 'flag' },
  { label: 'Manufacturing', value: 'manufacturing' },
  { label: 'Quality', value: 'quality' },
  { label: 'Experience', value: 'experience' },
];

export function normalizeAchievementIcon( value: unknown ): AchievementIcon {
  return ACHIEVEMENT_ICONS.some( ( icon ) => icon.value === value )
    ? ( value as AchievementIcon )
    : '';
}

export interface AchievementAttributes extends Record< string, unknown > {
  prefix?: string;
  year: string;
  label: string;
  icon?: AchievementIcon;
  heading: string;
  content: string;
}

export function hasAchievementContent(
  attributes: AchievementAttributes
): boolean {
  return Boolean(
    attributes.prefix ||
      attributes.year ||
      attributes.label ||
      normalizeAchievementIcon( attributes.icon ) ||
      attributes.heading ||
      attributes.content
  );
}
