import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { runInNewContext } from 'node:vm';
import { describe, expect, it } from 'vitest';

type Listener = ( event: { key?: string } ) => void;

class FakeElement {
  attributes = new Map< string, string >();
  hidden = true;
  firstLink: FakeAnchor | null = null;
  focused = false;
  listeners = new Map< string, Listener[] >();
  classList = {
    values: new Set< string >(),
    add: ( value: string ) => this.classList.values.add( value ),
    contains: ( value: string ) => this.classList.values.has( value ),
  };

  addEventListener( name: string, listener: Listener ): void {
    this.listeners.set( name, [
      ...( this.listeners.get( name ) ?? [] ),
      listener,
    ] );
  }

  dispatch( name: string, event: { key?: string } = {} ): void {
    for ( const listener of this.listeners.get( name ) ?? [] ) {
      listener( event );
    }
  }

  focus(): void {
    this.focused = true;
  }

  getAttribute( name: string ): string | null {
    return this.attributes.get( name ) ?? null;
  }

  setAttribute( name: string, value: string ): void {
    this.attributes.set( name, value );
  }

  querySelector(): FakeAnchor | null {
    return this.firstLink;
  }
}

class FakeButton extends FakeElement {}
class FakeAnchor extends FakeElement {}

function loadNavigation( mobile = true, withElements = true ) {
  const button = new FakeButton();
  const menu = new FakeElement();
  const link = new FakeAnchor();
  const listeners = new Map< string, Listener[] >();
  const mediaListeners: Listener[] = [];
  const media = {
    matches: mobile,
    addEventListener: ( name: string, listener: Listener ) => {
      if ( 'change' === name ) {
        mediaListeners.push( listener );
      }
    },
  };
  let mediaQuery = '';
  const document = {
    documentElement: new FakeElement(),
    addEventListener: ( name: string, listener: Listener ) => {
      listeners.set( name, [ ...( listeners.get( name ) ?? [] ), listener ] );
    },
    getElementById: () => ( withElements ? menu : null ),
    querySelector: () => ( withElements ? button : null ),
  };
  menu.firstLink = link;

  runInNewContext(
    readFileSync(
      resolve( 'wordpress/themes/celina-child/assets/js/navigation.js' ),
      'utf8'
    ),
    {
      HTMLAnchorElement: FakeAnchor,
      HTMLButtonElement: FakeButton,
      HTMLElement: FakeElement,
      document,
      window: {
        matchMedia: ( query: string ) => {
          mediaQuery = query;
          return media;
        },
      },
    }
  );
  for ( const listener of listeners.get( 'DOMContentLoaded' ) ?? [] ) {
    listener( {} );
  }

  return {
    button,
    document,
    link,
    media,
    mediaListeners,
    mediaQuery,
    menu,
    listeners,
  };
}

describe( 'CELINA navigation', () => {
  it( 'enhances mobile navigation, toggles it, and restores focus on Escape', () => {
    const navigation = loadNavigation();

    expect(
      navigation.document.documentElement.classList.contains(
        'celina-navigation-ready'
      )
    ).toBe( true );
    expect( navigation.menu.hidden ).toBe( true );
    expect( navigation.mediaQuery ).toBe( '(max-width: 64rem)' );
    navigation.button.dispatch( 'click' );
    expect( navigation.menu.hidden ).toBe( false );
    expect( navigation.link.focused ).toBe( true );
    navigation.listeners.get( 'keydown' )?.[ 0 ]?.( { key: 'Escape' } );
    expect( navigation.menu.hidden ).toBe( true );
    expect( navigation.button.focused ).toBe( true );
  } );

  it( 'keeps mobile navigation reset across breakpoint changes', () => {
    const navigation = loadNavigation( false );

    navigation.button.dispatch( 'click' );
    navigation.mediaListeners[ 0 ]?.( {} );
    expect( navigation.button.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
    expect( navigation.menu.hidden ).toBe( true );
  } );

  it( 'fails safely when the expected elements are absent', () => {
    const navigation = loadNavigation( true, false );

    expect(
      navigation.document.documentElement.classList.contains(
        'celina-navigation-ready'
      )
    ).toBe( false );
  } );
} );
