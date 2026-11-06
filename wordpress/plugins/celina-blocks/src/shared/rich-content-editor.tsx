import {
  BlockEditorProvider,
  store as blockEditorStore,
  BlockList,
  BlockToolbar,
  BlockTools,
  RichText,
  WritingFlow,
} from '@wordpress/block-editor';
import {
  createBlock,
  rawHandler,
  serialize,
  switchToBlockType,
} from '@wordpress/blocks';
import {
  Button,
  Modal,
  Popover,
  SlotFillProvider,
} from '@wordpress/components';
import { useReducer, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import type { KeyboardEvent } from 'react';
import { normalizeLegacyLists } from './normalize-legacy-lists';
import './rich-content-editor.scss';

const ALLOWED_BLOCKS = [
  'core/paragraph',
  'core/list',
  'core/list-item',
  'core/heading',
  'core/quote',
];
const SETTINGS = {
  allowedBlockTypes: ALLOWED_BLOCKS,
  hasFixedToolbar: true,
  hasReducedUI: false,
  templateLock: false,
  canLockBlocks: false,
};

type Blocks = ReturnType< typeof rawHandler >;

function TextFormatControls() {
  const selected = useSelect( ( select ) => {
    const editor = select( blockEditorStore );
    const block = editor.getSelectedBlock();
    return block?.name === 'core/list-item'
      ? editor.getBlock( editor.getBlockRootClientId( block.clientId ) )
      : block;
  }, [] );
  const { replaceBlocks, updateBlockAttributes } =
    useDispatch( blockEditorStore );
  const changeFormat = ( format: 'paragraph' | 'bullets' | 'numbers' ) => {
    if ( ! selected ) {
      return;
    }
    if ( selected.name === 'core/list' && format !== 'paragraph' ) {
      updateBlockAttributes( selected.clientId, {
        ordered: format === 'numbers',
      } );
      return;
    }
    const converted = switchToBlockType(
      selected,
      format === 'paragraph' ? 'core/paragraph' : 'core/list'
    );
    if ( converted ) {
      if ( format !== 'paragraph' ) {
        converted.forEach( ( block ) => {
          block.attributes.ordered = format === 'numbers';
        } );
      }
      replaceBlocks( selected.clientId, converted );
    }
  };
  return (
    <div
      className="celina-rich-content-modal__formats"
      role="group"
      aria-label="Text format"
    >
      <Button
        variant="secondary"
        disabled={ ! selected }
        onClick={ () => changeFormat( 'paragraph' ) }
      >
        Paragraph
      </Button>
      <Button
        variant="secondary"
        disabled={ ! selected }
        onClick={ () => changeFormat( 'bullets' ) }
      >
        Bulleted list
      </Button>
      <Button
        variant="secondary"
        disabled={ ! selected }
        onClick={ () => changeFormat( 'numbers' ) }
      >
        Numbered list
      </Button>
    </div>
  );
}

interface History {
  past: Blocks[];
  present: Blocks;
  future: Blocks[];
}

type HistoryAction =
  | { type: 'input' | 'commit'; blocks: Blocks }
  | { type: 'undo' | 'redo' };

function historyReducer( state: History, action: HistoryAction ): History {
  if ( action.type === 'undo' ) {
    const previous = state.past.at( -1 );
    return previous
      ? {
          past: state.past.slice( 0, -1 ),
          present: previous,
          future: [ state.present, ...state.future ],
        }
      : state;
  }
  if ( action.type === 'redo' ) {
    const next = state.future[ 0 ];
    return next
      ? {
          past: [ ...state.past, state.present ],
          present: next,
          future: state.future.slice( 1 ),
        }
      : state;
  }
  if ( 'blocks' in action ) {
    // BlockEditorProvider may echo normalized copies when a controlled undo
    // value is received. That acknowledgement must not erase the redo stack.
    if (
      action.blocks === state.present ||
      serialize( action.blocks ) === serialize( state.present )
    ) {
      return state;
    }
    return {
      past:
        action.type === 'commit'
          ? [ ...state.past, state.present ]
          : state.past,
      present: action.blocks,
      future: [],
    };
  }
  return state;
}

// Keep the existing HTML attribute/save contract. The nested editor's block
// comments belong only to its temporary model, not the surrounding page.
function toHTML( blocks: Blocks ): string {
  const document = new DOMParser().parseFromString(
    serialize( blocks ),
    'text/html'
  );
  const walker = document.createTreeWalker(
    document.body,
    NodeFilter.SHOW_COMMENT
  );
  const comments: Node[] = [];
  while ( walker.nextNode() ) {
    if ( /^\s*\/?wp:/.test( walker.currentNode.textContent ?? '' ) ) {
      comments.push( walker.currentNode );
    }
  }
  comments.forEach( ( comment ) => comment.parentNode?.removeChild( comment ) );
  return document.body.textContent?.trim()
    ? document.body.innerHTML.trim()
    : '';
}

function isSupported( blocks: Blocks ): boolean {
  return blocks.every(
    ( block ) =>
      ALLOWED_BLOCKS.includes( block.name ) &&
      block.isValid !== false &&
      isSupported( block.innerBlocks )
  );
}

interface Props {
  className?: string;
  value: string;
  onChange: ( value: string ) => void;
  placeholder: string;
}

function ContentDialog( {
  value,
  onChange,
  placeholder,
  onClose,
}: Props & { onClose: () => void } ) {
  const [ initial ] = useState( () => {
    const blocks = rawHandler( { HTML: normalizeLegacyLists( value ) } );
    return blocks.length ? blocks : [ createBlock( 'core/paragraph' ) ];
  } );
  const [ history, dispatch ] = useReducer( historyReducer, {
    past: [],
    present: initial,
    future: [],
  } );
  const blocks = history.present;
  const [ initialHTML ] = useState( () => toHTML( initial ) );
  const supported = isSupported( blocks );
  const handleHistoryKey = ( event: KeyboardEvent ) => {
    if ( ! ( event.metaKey || event.ctrlKey ) || event.altKey ) {
      return;
    }
    const key = event.key.toLowerCase();
    if ( key === 'z' || key === 'y' ) {
      event.preventDefault();
      event.stopPropagation();
      dispatch( { type: key === 'y' || event.shiftKey ? 'redo' : 'undo' } );
    }
  };

  return (
    <Modal
      title={ `Edit ${ placeholder }` }
      onRequestClose={ onClose }
      className="celina-rich-content-modal"
      onKeyDown={ handleHistoryKey }
    >
      <p>
        Use Paragraph and List blocks to format your text. Apply your changes
        here, then save the page.
      </p>
      <div onKeyDownCapture={ handleHistoryKey }>
        <div className="celina-rich-content-modal__history">
          <Button
            variant="secondary"
            disabled={ ! history.past.length }
            onClick={ () => dispatch( { type: 'undo' } ) }
          >
            Undo
          </Button>
          <Button
            variant="secondary"
            disabled={ ! history.future.length }
            onClick={ () => dispatch( { type: 'redo' } ) }
          >
            Redo
          </Button>
        </div>
        <SlotFillProvider>
          <BlockEditorProvider
            value={ blocks }
            onInput={ ( next ) => dispatch( { type: 'input', blocks: next } ) }
            onChange={ ( next ) =>
              dispatch( { type: 'commit', blocks: next } )
            }
            settings={ SETTINGS }
          >
            <TextFormatControls />
            <div className="celina-rich-content-modal__toolbar">
              <BlockToolbar />
            </div>
            <BlockTools>
              <div className="editor-styles-wrapper celina-rich-content-modal__canvas">
                <WritingFlow>
                  <BlockList />
                </WritingFlow>
              </div>
            </BlockTools>
            <Popover.Slot />
          </BlockEditorProvider>
        </SlotFillProvider>
      </div>
      { ! supported && (
        <p role="alert">
          This content includes an unsupported block. Cancel to keep the
          original text unchanged.
        </p>
      ) }
      <div className="celina-rich-content-modal__actions">
        <Button variant="secondary" onClick={ onClose }>
          Cancel
        </Button>
        <Button
          variant="primary"
          disabled={ ! supported }
          onClick={ () => {
            const html = toHTML( blocks );
            if ( html !== initialHTML ) {
              onChange( html );
            }
            onClose();
          } }
        >
          Apply changes
        </Button>
      </div>
    </Modal>
  );
}

export default function RichContentEditor( props: Props ) {
  const [ isOpen, setIsOpen ] = useState( false );
  return (
    <div className="celina-rich-content-editor">
      { props.value ? (
        <RichText.Content
          tagName="div"
          className={ props.className }
          value={ props.value }
        />
      ) : (
        <p className="celina-rich-content-editor__placeholder">
          { props.placeholder }
        </p>
      ) }
      <Button variant="secondary" onClick={ () => setIsOpen( true ) }>
        Edit text
      </Button>
      { isOpen && (
        <ContentDialog { ...props } onClose={ () => setIsOpen( false ) } />
      ) }
    </div>
  );
}
