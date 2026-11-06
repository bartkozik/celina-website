import '@wordpress/data';

type EditorBlocks = ReturnType< typeof import('@wordpress/blocks').rawHandler >;

declare module '@wordpress/data' {
  export function useDispatch( store: 'core/block-editor' ): {
    replaceBlocks( clientId: string, blocks: EditorBlocks ): void;
    updateBlockAttributes( clientId: string, attributes: Record< string, unknown > ): void;
  };
}
