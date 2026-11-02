import { registerBlockType, registerBlockVariation } from '@wordpress/blocks';
import { EDITORIAL_VARIATIONS } from './editorial-variations';
import metadata from './block.json';
import Edit from './edit';
import './editor.scss';
import './style.scss';
import save from './save';

registerBlockType( metadata, { edit: Edit, save } );

EDITORIAL_VARIATIONS.forEach( ( variation ) =>
  registerBlockVariation( 'core/group', variation )
);
