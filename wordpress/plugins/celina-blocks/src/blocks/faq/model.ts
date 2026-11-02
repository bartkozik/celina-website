export const ALLOWED_FAQ_BLOCKS: readonly string[] = [ 'celina/faq-item' ];

export interface FaqAttributes extends Record< string, unknown > {
  eyebrow: string;
  heading: string;
}

export const FAQ_TEMPLATE: readonly [
  readonly [ string, Record< string, never > ],
] = [ [ 'celina/faq-item', {} ] ];
