export interface EditorBlock {
  clientId: string;
  name: string;
  isValid?: boolean;
  attributes: Record< string, unknown > & {
    className?: string;
    content?: string;
    id?: number;
    caption?: string;
  };
  innerBlocks: EditorBlock[];
}

interface BlockEditorSelectors {
  getBlocks(): EditorBlock[];
  canInsertBlockType( name: string, rootClientId?: string ): boolean;
}

interface BlockEditorActions {
  resetBlocks( blocks: EditorBlock[] ): void;
  selectBlock( clientId: string ): void;
  updateBlockAttributes( clientId: string, attributes: Record< string, unknown > ): void;
  insertBlocks( blocks: EditorBlock | EditorBlock[], index?: number, rootClientId?: string ): void;
  moveBlocksToPosition( clientIds: string[], fromRootClientId: string, toRootClientId: string, index: number ): void;
  removeBlocks( clientIds: string[], selectPrevious?: boolean ): void;
}

interface PostEditorActions {
  editPost( attributes: { title?: string; status?: string } ): void;
  savePost(): Promise< void >;
}

interface WordPressBrowser {
  blocks: {
    parse( content: string ): EditorBlock[];
    serialize( blocks: EditorBlock[] ): string;
    createBlock( name: string, attributes?: Record< string, unknown >, innerBlocks?: EditorBlock[] ): EditorBlock;
  };
  data: {
    select( store: 'core/block-editor' ): BlockEditorSelectors;
    dispatch( store: 'core/block-editor' ): BlockEditorActions;
    dispatch( store: 'core/editor' ): PostEditorActions;
  };
  apiFetch( options: { path: '/wp/v2/block-patterns/patterns' } ): Promise< Array< { name: string; content: string } > >;
}

declare global {
  interface Window {
    wp: WordPressBrowser;
  }
}
