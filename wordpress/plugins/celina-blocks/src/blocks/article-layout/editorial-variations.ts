import type { BlockVariation } from '@wordpress/blocks';

const sectionAttributes = (
  name: string,
  className: string,
  background: string
) => ( {
  metadata: { name },
  className,
  layout: { type: 'default' },
  style: {
    color: { background, text: '#1d2523' },
    spacing: {
      padding: {
        top: '1.5rem',
        right: '1.5rem',
        bottom: '1.5rem',
        left: '1.5rem',
      },
      margin: { top: '2rem', bottom: '2rem' },
      blockGap: '1rem',
    },
  },
} );

export const EDITORIAL_VARIATIONS: BlockVariation[] = [
  {
    name: 'celina-pro-tip',
    title: 'CELINA Pro tip',
    description:
      'An editable tip box. Change its background and text colors in Styles.',
    category: 'celina',
    icon: 'lightbulb',
    keywords: [ 'tip', 'callout' ],
    scope: [ 'inserter' ],
    attributes: sectionAttributes(
      'Pro tip',
      'celina-editorial-tip',
      '#fff1e7'
    ),
    innerBlocks: [
      [ 'core/heading', { level: 3, content: 'Pro tip' } ],
      [
        'core/paragraph',
        { placeholder: 'Share a useful tip with the reader.' },
      ],
    ],
  },
  {
    name: 'celina-inline-cta',
    title: 'CELINA Inline CTA',
    description:
      'An inline call to action with editable copy and a native WordPress button.',
    category: 'celina',
    icon: 'megaphone',
    keywords: [ 'button', 'cta', 'inline' ],
    scope: [ 'inserter' ],
    attributes: sectionAttributes(
      'Article call to action',
      'celina-editorial-cta',
      '#f2f3ef'
    ),
    innerBlocks: [
      [
        'core/heading',
        { level: 3, placeholder: 'What should the reader do next?' },
      ],
      [ 'core/paragraph', { placeholder: 'Explain the next step.' } ],
      [
        'core/buttons',
        {},
        [ [ 'core/button', { text: 'Contact CELINA', url: '/contact/' } ] ],
      ],
    ],
  },
  {
    name: 'celina-attributed-quote',
    title: 'CELINA Attributed quote',
    description:
      'A quote with an optional photo, source name and title. Replace the sample attribution.',
    category: 'celina',
    icon: 'format-quote',
    keywords: [ 'quote', 'testimonial' ],
    scope: [ 'inserter' ],
    attributes: sectionAttributes(
      'Attributed quote',
      'celina-editorial-quote',
      '#f2f3ef'
    ),
    innerBlocks: [
      [
        'core/quote',
        {},
        [ [ 'core/paragraph', { placeholder: 'Write the quotation.' } ] ],
      ],
      [
        'core/columns',
        {},
        [
          [
            'core/column',
            { width: '100px' },
            [ [ 'core/image', { sizeSlug: 'thumbnail' } ] ],
          ],
          [
            'core/column',
            {},
            [
              [
                'core/paragraph',
                {
                  placeholder: 'Source name',
                  style: { typography: { fontWeight: '700' } },
                },
              ],
              [
                'core/paragraph',
                { placeholder: 'Source title or organization' },
              ],
            ],
          ],
        ],
      ],
    ],
  },
  {
    name: 'celina-author-bio',
    title: 'CELINA Author bio',
    description:
      'An editable biography with an optional photo. It is independent of the post author profile.',
    category: 'celina',
    icon: 'admin-users',
    keywords: [ 'author', 'biography' ],
    scope: [ 'inserter' ],
    attributes: sectionAttributes(
      'Author biography',
      'celina-editorial-bio',
      '#f2f3ef'
    ),
    innerBlocks: [
      [ 'core/heading', { level: 3, content: 'About the author' } ],
      [
        'core/columns',
        {},
        [
          [
            'core/column',
            { width: '120px' },
            [ [ 'core/image', { sizeSlug: 'thumbnail' } ] ],
          ],
          [
            'core/column',
            {},
            [
              [
                'core/paragraph',
                {
                  placeholder: 'Author name',
                  style: { typography: { fontWeight: '700' } },
                },
              ],
              [ 'core/paragraph', { placeholder: 'Job title' } ],
              [
                'core/paragraph',
                { placeholder: 'Write a short author biography.' },
              ],
            ],
          ],
        ],
      ],
    ],
  },
];
