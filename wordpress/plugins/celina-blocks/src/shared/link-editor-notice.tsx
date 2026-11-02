import { isSafeLinkUrl } from './link';

export default function LinkEditorNotice( {
  label,
  url,
}: {
  label: string;
  url: string;
} ) {
  if ( ! label.trim() && ! url.trim() ) {
    return null;
  }

  if ( label.trim() && isSafeLinkUrl( url ) ) {
    return null;
  }

  return (
    <p className="components-base-control__help" role="status">
      Add both a link label and a valid URL, such as https://example.com or
      /contact/, to finish this link.
    </p>
  );
}
