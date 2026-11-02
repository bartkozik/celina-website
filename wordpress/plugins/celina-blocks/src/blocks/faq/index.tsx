import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './editor.scss';
import type { FaqAttributes } from './model';
import './style.scss';
import save from './save';

registerBlockType< FaqAttributes >( metadata, { edit: Edit, save } );
