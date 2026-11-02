// @vitest-environment jsdom
import { describe, expect, it } from 'vitest';
import { normalizeLegacyLists } from './normalize-legacy-lists';

describe( 'legacy list containers', () => {
  it( 'groups orphan items while preserving inline formatting and links', () => {
    expect(
      normalizeLegacyLists(
        '<p>Intro</p><li><strong>One</strong></li><li><a href="/about/">Two</a></li>'
      )
    ).toBe(
      '<p>Intro</p><ul><li><strong>One</strong></li><li><a href="/about/">Two</a></li></ul>'
    );
  } );

  it( 'keeps separate lists separated by paragraph content', () => {
    expect(
      normalizeLegacyLists( '<li>One</li><p>Between</p><li>Two</li>' )
    ).toBe( '<ul><li>One</li></ul><p>Between</p><ul><li>Two</li></ul>' );
  } );

  it( 'groups whitespace and comments without dropping their content', () => {
    expect(
      normalizeLegacyLists( '<li>One</li>\n<!-- note --><li>Two</li>' )
    ).toBe( '<ul><li>One</li>\n<!-- note --><li>Two</li></ul>' );
  } );

  it( 'preserves existing ordered and nested lists exactly', () => {
    const html = '<ol start="3"><li>A<ul><li>B</li></ul></li></ol>';
    expect( normalizeLegacyLists( html ) ).toBe( html );
  } );

  it( 'normalizes orphan items within wrappers without flattening nested lists', () => {
    expect(
      normalizeLegacyLists(
        '<div><li>A<ul><li>B</li></ul></li><li>C</li></div>'
      )
    ).toBe( '<div><ul><li>A<ul><li>B</li></ul></li><li>C</li></ul></div>' );
  } );

  it( 'leaves unsupported content and non-list HTML untouched', () => {
    const html = '<table><tr><td>Keep me</td></tr></table><p>A &amp; B</p>';
    expect( normalizeLegacyLists( html ) ).toBe( html );
  } );
} );
