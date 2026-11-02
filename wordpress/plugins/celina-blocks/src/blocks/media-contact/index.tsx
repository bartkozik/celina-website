import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import type { MediaContactAttributes } from './model';
import './editor.scss';
import './style.scss';
import save from './save';
registerBlockType< MediaContactAttributes >( metadata, { edit: Edit, save } );
