import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import type { NewsAttributes } from './model';
import './editor.scss';
import './style.scss';
registerBlockType< NewsAttributes >( metadata, {
  edit: Edit,
  save: () => null,
} );
