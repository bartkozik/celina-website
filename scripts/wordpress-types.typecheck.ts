import type { ComponentProps } from 'react';
import type * as BlockEditor from '@wordpress/block-editor';

type RichTextProps = ComponentProps< typeof BlockEditor.RichText >;
type RichTextContentProps = ComponentProps<
  typeof BlockEditor.RichText.Content
>;
type BlockProps = ReturnType< typeof BlockEditor.useBlockProps >;
type SaveBlockProps = ReturnType< typeof BlockEditor.useBlockProps.save >;

const validRichText: RichTextProps = {
  className: 'wp-block-celina-example',
  tagName: 'p',
  value: 'Content',
  onChange: ( content ) => content,
  placeholder: 'Placeholder',
};
const validContent: RichTextContentProps = {
  className: 'wp-block-celina-example',
  tagName: 'p',
  value: 'Content',
};
const validBlockProps: BlockProps = { className: 'editor-wrapper' };
const validSaveBlockProps: SaveBlockProps = { className: 'saved-wrapper' };

// @ts-expect-error misspelled generated block prop must not typecheck.
const misspelled: RichTextProps = { placeholer: 'Nope' };
// @ts-expect-error generated content is always a string.
const wrongValue: RichTextProps = { value: 1 };
// @ts-expect-error generated onChange receives a string.
const wrongOnChange: RichTextProps = { onChange: ( value: number ) => value };
// @ts-expect-error saved content does not receive editor callbacks.
const savedOnChange: RichTextContentProps = { onChange: () => {} };
// @ts-expect-error saved content does not receive an editor placeholder.
const savedPlaceholder: RichTextContentProps = { placeholder: 'Nope' };
// @ts-expect-error block props remain narrow enough to catch spelling mistakes.
const misspelledBlockProp: BlockProps = { classNmae: 'Nope' };

void validRichText;
void validContent;
void validBlockProps;
void validSaveBlockProps;
void misspelled;
void wrongValue;
void wrongOnChange;
void savedOnChange;
void savedPlaceholder;
void misspelledBlockProp;
