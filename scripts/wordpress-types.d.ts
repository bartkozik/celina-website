declare module '@wordpress/block-editor' {
  import type { CSSProperties, ComponentType, ReactNode } from 'react';
  type EditorBlocks = ReturnType< typeof import('@wordpress/blocks').rawHandler >;

  interface RichTextProps {
    allowedFormats?: readonly string[];
    className?: string;
    onChange?: ( content: string ) => void;
    placeholder?: string;
    tagName?: string;
    value?: string;
  }

  interface InnerBlocksProps {
    allowedBlocks?: readonly string[];
    renderAppender?: ComponentType;
    template?: readonly ( readonly [ string, Record< string, unknown > ] )[];
    templateLock?: boolean | 'all' | 'insert' | 'contentOnly';
  }

  interface BlockProps {
    className?: string;
    style?: CSSProperties;
  }

  interface MediaUploadProps {
    allowedTypes?: string[];
    onSelect?: ( media: { id: number; url: string } ) => void;
    render: ( props: { open: () => void } ) => ReactNode;
    value?: number;
  }

  export const RichText: ComponentType< RichTextProps > & {
    Content: ComponentType< Omit< RichTextProps, 'onChange' | 'placeholder' > >;
  };
  export const InnerBlocks: ComponentType< InnerBlocksProps > & {
    ButtonBlockAppender: ComponentType;
    Content: ComponentType;
  };
  export const InspectorControls: ComponentType< { children?: ReactNode } >;
  export const BlockEditorProvider: ComponentType< {
    children?: ReactNode;
    value: EditorBlocks;
    onInput: ( blocks: EditorBlocks ) => void;
    onChange: ( blocks: EditorBlocks ) => void;
    settings: {
      allowedBlockTypes: string[];
      hasFixedToolbar: boolean;
      hasReducedUI: boolean;
      templateLock: boolean;
      canLockBlocks: boolean;
    };
  } >;
  export const BlockList: ComponentType;
  export const BlockToolbar: ComponentType;
  export const BlockTools: ComponentType< { children?: ReactNode } >;
  export const WritingFlow: ComponentType< { children?: ReactNode } >;
  export const MediaUpload: ComponentType< MediaUploadProps >;
  export const MediaUploadCheck: ComponentType< { children?: ReactNode } >;
  export const URLInputButton: ComponentType< {
    onChange: ( url: string ) => void;
    url: string;
  } >;
  export function useBlockProps( props?: BlockProps ): BlockProps;
  export namespace useBlockProps {
    function save( props?: BlockProps ): BlockProps;
  }
}

declare module 'equivalent-key-map' {
  export default class EquivalentKeyMap< Key, Value > extends Map<
    Key,
    Value
  > {}
}
