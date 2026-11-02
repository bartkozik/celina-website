import { registerBlockType, registerBlockVariation } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './editor.scss';
import './style.scss';
registerBlockType( metadata, { edit: Edit, save: () => null } );

const sharedAttributes = {
  region: 'na1',
  portalId: 'portfolio-unconfigured',
  formId: '',
  fallbackText:
    'If the form is unavailable, email sales@example.invalid or call (202) 555-0100.',
};

[
  {
    name: 'request-callback',
    title: 'Request a callback',
    description: 'A short, low-friction follow-up request.',
    attributes: { ...sharedAttributes, heading: 'Request a Callback' },
  },
  {
    name: 'ask-question',
    title: 'Ask us a question',
    description: 'A general product or company question.',
    attributes: { ...sharedAttributes, heading: 'Ask the CELINA Team' },
  },
  {
    name: 'request-quote',
    title: 'Request a quote',
    description: 'A request containing commercial project details.',
    attributes: { ...sharedAttributes, heading: 'Request a Quote' },
  },
  {
    name: 'technical-inquiry',
    title: 'Product or technical inquiry',
    description: 'An application-specific product or technical question.',
    attributes: {
      ...sharedAttributes,
      heading: 'Ask a Product or Technical Question',
    },
  },
].forEach( ( variation ) =>
  registerBlockVariation( metadata.name, {
    ...variation,
    scope: [ 'inserter' ],
  } )
);
