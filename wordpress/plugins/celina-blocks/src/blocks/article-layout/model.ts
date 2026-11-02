export const ALLOWED_ARTICLE_BLOCKS = [
  'core/paragraph',
  'core/heading',
  'core/image',
  'core/gallery',
  'core/list',
  'core/pullquote',
  'core/quote',
  'core/table',
  'core/buttons',
  'core/separator',
  'core/embed',
  'core/group',
  'core/columns',
  'core/media-text',
  'celina/media-contact',
] as const;

type ArticleTemplateAttributes = Readonly< Record< string, unknown > >;
type ArticleTemplateItem = readonly [ string, ArticleTemplateAttributes ];

export const ARTICLE_TEMPLATE: readonly ArticleTemplateItem[] = [
  [
    'core/paragraph',
    {
      className: 'celina-article-layout__lead',
      placeholder: 'Summarize the announcement and why it matters.',
    },
  ],
  [
    'core/heading',
    {
      level: 2,
      placeholder: 'Article section heading',
    },
  ],
  [
    'core/paragraph',
    {
      placeholder: 'Develop the story with the essential details.',
    },
  ],
  [
    'core/image',
    {
      caption: 'Image caption.',
      sizeSlug: 'large',
    },
  ],
  [
    'core/pullquote',
    {
      value: 'Pull quote.',
      citation: 'Quote source',
    },
  ],
  [
    'core/paragraph',
    {
      placeholder: 'Conclude the article with the next step or key takeaway.',
    },
  ],
  [ 'celina/media-contact', {} ],
];
